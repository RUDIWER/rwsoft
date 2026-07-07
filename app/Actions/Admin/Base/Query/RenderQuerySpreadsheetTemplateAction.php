<?php

namespace App\Actions\Admin\Base\Query;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class RenderQuerySpreadsheetTemplateAction
{
    private const MAX_SHEET_CLONES = 200;

    /**
     * @param  array<string, mixed>  $context
     */
    public static function handle(string $templatePath, string $outputPath, array $context): void
    {
        if (! is_file($templatePath)) {
            throw new RuntimeException(__('query_builder_ui.runtime.spreadsheet_template_missing'));
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheetContexts = self::prepareSheetCloneContexts($spreadsheet, $context);
        $sheetContexts = self::enrichWorkbookContexts($spreadsheet, $sheetContexts);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetContext = $sheetContexts[spl_object_id($sheet)] ?? $context;
            self::renderRowsDirectives($sheet, $sheetContext);
            self::renderScalarPlaceholders($sheet, $sheetContext);
        }

        $writerType = self::writerTypeForPath($outputPath);
        $writer = IOFactory::createWriter($spreadsheet, $writerType);
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sheetContexts
     * @return array<int, array<string, mixed>>
     */
    private static function enrichWorkbookContexts(Spreadsheet $spreadsheet, array $sheetContexts): array
    {
        $sheets = $spreadsheet->getAllSheets();
        $sheetNames = array_values(array_map(
            static fn (Worksheet $sheet): string => (string) $sheet->getTitle(),
            $sheets,
        ));
        $sheetCount = count($sheets);

        foreach ($sheets as $index => $sheet) {
            $sheetId = spl_object_id($sheet);
            $baseContext = $sheetContexts[$sheetId] ?? [];
            $workbookMeta = [
                'sheet_index' => $index + 1,
                'sheet_count' => $sheetCount,
                'sheet_title' => (string) $sheet->getTitle(),
                'sheet_names' => $sheetNames,
            ];

            $sheetContexts[$sheetId] = array_merge($baseContext, [
                'workbook' => array_merge(
                    is_array($baseContext['workbook'] ?? null) ? $baseContext['workbook'] : [],
                    $workbookMeta,
                ),
            ]);
        }

        return $sheetContexts;
    }

    /**
     * @param  array<string, mixed>  $globalContext
     * @return array<int, array<string, mixed>>
     */
    private static function prepareSheetCloneContexts(Spreadsheet $spreadsheet, array $globalContext): array
    {
        $sheetContexts = [];
        $sheetOrders = [];
        $usedSheetNames = [];

        foreach ($spreadsheet->getAllSheets() as $existingSheet) {
            $usedSheetNames[] = mb_strtolower((string) $existingSheet->getTitle());
        }

        $sourceSheets = $spreadsheet->getAllSheets();

        foreach ($sourceSheets as $sheet) {
            $sheetId = spl_object_id($sheet);
            $cloneDirective = self::findSheetCloneDirective($sheet);

            if ($cloneDirective === null) {
                $sheetContexts[$sheetId] = $globalContext;

                if (self::shouldHideSheetByDirective($sheet, $globalContext)) {
                    $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($sheet));
                    unset($sheetContexts[$sheetId]);

                    continue;
                }

                $sheetOrders[$sheetId] = self::resolveSheetOrderDirective(
                    $sheet,
                    $spreadsheet->getIndex($sheet) + 1,
                );

                continue;
            }

            $recordsRaw = self::resolvePath($globalContext, $cloneDirective['dataset']);
            $records = [];

            if (is_iterable($recordsRaw)) {
                foreach ($recordsRaw as $item) {
                    $records[] = self::normalizeRecord($item);
                }
            }

            if (count($records) > self::MAX_SHEET_CLONES) {
                throw new RuntimeException(sprintf(
                    'sheetClone overschrijdt limiet (%d) op sheet "%s".',
                    self::MAX_SHEET_CLONES,
                    $sheet->getTitle(),
                ));
            }

            $keepTemplate = self::optionToBool($cloneDirective['options']['keepTemplate'] ?? null, false);

            if ($records === []) {
                if (! $keepTemplate) {
                    $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($sheet));
                    unset($sheetContexts[$sheetId]);

                    continue;
                }

                self::clearDirectiveCell($sheet, $cloneDirective['column_index'], $cloneDirective['row']);
                $sheetContexts[$sheetId] = $globalContext;

                continue;
            }

            $templateIndex = $spreadsheet->getIndex($sheet);
            $nameExpression = trim((string) ($cloneDirective['options']['name'] ?? ''));

            foreach ($records as $index => $record) {
                $clone = clone $sheet;
                self::clearDirectiveCell($clone, $cloneDirective['column_index'], $cloneDirective['row']);

                $sheetContext = array_merge($globalContext, ['sheet' => $record]);
                $cloneId = spl_object_id($clone);
                $sheetContexts[$cloneId] = $sheetContext;

                if (self::shouldHideSheetByDirective($clone, $sheetContext)) {
                    unset($sheetContexts[$cloneId]);

                    continue;
                }

                $nameCandidate = $nameExpression !== ''
                    ? self::scalarToString(self::resolvePath($sheetContext, $nameExpression))
                    : (string) ($sheet->getTitle().' '.($index + 1));

                $clone->setTitle(self::makeUniqueSheetName($nameCandidate, $usedSheetNames));
                $sheetOrders[$cloneId] = self::resolveSheetOrderDirective(
                    $clone,
                    ($templateIndex + 1) + (($index + 1) / 1000),
                );
                $spreadsheet->addSheet($clone, $templateIndex + $index + 1);
            }

            if ($keepTemplate) {
                self::clearDirectiveCell($sheet, $cloneDirective['column_index'], $cloneDirective['row']);
                $sheetContexts[$sheetId] = $globalContext;

                if (self::shouldHideSheetByDirective($sheet, $globalContext)) {
                    $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($sheet));
                    unset($sheetContexts[$sheetId]);

                    continue;
                }

                $sheetOrders[$sheetId] = self::resolveSheetOrderDirective(
                    $sheet,
                    $spreadsheet->getIndex($sheet) + 1,
                );

                continue;
            }

            $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($sheet));
            unset($sheetContexts[$sheetId]);
        }

        self::applySheetOrdering($spreadsheet, $sheetOrders);

        return $sheetContexts;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function renderRowsDirectives(Worksheet $sheet, array $context): void
    {
        $row = 1;
        $highestRow = $sheet->getHighestRow();

        while ($row <= $highestRow) {
            $rowsDirective = self::findRowsDirectiveInRow($sheet, $row);

            if ($rowsDirective === null) {
                $row++;

                continue;
            }

            $endRow = self::findRowsDirectiveEndRow($sheet, $row + 1, $highestRow);

            if ($endRow === null) {
                throw new RuntimeException(__('query_builder_ui.runtime.rows_directive_missing_end'));
            }

            $templateRow = $row + 1;

            if ($templateRow >= $endRow) {
                $sheet->removeRow($endRow, 1);
                $sheet->removeRow($row, 1);
                $highestRow = $sheet->getHighestRow();

                continue;
            }

            $records = self::recordsForRowsDirective($context, $rowsDirective);
            $insertedRows = 0;
            $groupRowRanges = [];

            foreach ($records as $recordIndex => $recordMeta) {
                if (($recordMeta['is_group_header'] ?? false) === true) {
                    $insertAt = $endRow + $insertedRows;
                    $sheet->insertNewRowBefore($insertAt, 1);
                    $sheet->setCellValue(
                        self::coordinate($rowsDirective['column_index'], $insertAt),
                        (string) ($recordMeta['label'] ?? ''),
                    );
                    $sheet->getStyle(self::coordinate($rowsDirective['column_index'], $insertAt))->getFont()->setBold(true);
                    $insertedRows++;

                    continue;
                }

                if (($recordMeta['is_group_footer'] ?? false) === true) {
                    $insertAt = $endRow + $insertedRows;
                    $sheet->insertNewRowBefore($insertAt, 1);

                    $groupKey = (string) ($recordMeta['group_key'] ?? '');
                    $range = $groupRowRanges[$groupKey] ?? ['first' => null, 'last' => null];
                    $label = trim((string) ($recordMeta['label'] ?? 'Subtotaal'));
                    $sheet->setCellValue(
                        self::coordinate($rowsDirective['column_index'], $insertAt),
                        $label,
                    );

                    $subtotalColumnIndex = (int) ($recordMeta['subtotal_column_index'] ?? 0);

                    if ($subtotalColumnIndex > 0 && is_int($range['first']) && is_int($range['last'])) {
                        $formula = sprintf(
                            '=SUM(%s%d:%s%d)',
                            Coordinate::stringFromColumnIndex($subtotalColumnIndex),
                            $range['first'],
                            Coordinate::stringFromColumnIndex($subtotalColumnIndex),
                            $range['last'],
                        );
                        $sheet->setCellValue(self::coordinate($subtotalColumnIndex, $insertAt), $formula);
                    }

                    $sheet->getStyle(self::coordinate($rowsDirective['column_index'], $insertAt))->getFont()->setBold(true);
                    $insertedRows++;

                    continue;
                }

                $insertAt = $endRow + $insertedRows;
                $sheet->insertNewRowBefore($insertAt, 1);
                self::copyTemplateRow($sheet, $templateRow, $insertAt);

                $groupKey = (string) ($recordMeta['group_key'] ?? '');

                if ($groupKey !== '') {
                    if (! array_key_exists($groupKey, $groupRowRanges)) {
                        $groupRowRanges[$groupKey] = ['first' => $insertAt, 'last' => $insertAt];
                    } else {
                        $groupRowRanges[$groupKey]['last'] = $insertAt;
                    }
                }

                $rowContext = array_merge($context, [
                    'row' => $recordMeta['row'],
                    'item' => $recordMeta['row'],
                    'index' => $recordMeta['index'],
                    'group' => [
                        'key' => $recordMeta['group_key'] ?? null,
                        'index' => $recordMeta['group_index'] ?? null,
                    ],
                ]);

                self::renderPlaceholdersInRow($sheet, $insertAt, $rowContext);
                $insertedRows++;
            }

            $sheet->removeRow($endRow + $insertedRows, 1);
            $sheet->removeRow($templateRow, 1);
            $sheet->removeRow($row, 1);
            $highestRow = $sheet->getHighestRow();
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function renderScalarPlaceholders(Worksheet $sheet, array $context): void
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                $coordinate = self::coordinate($columnIndex, $row);
                $cell = $sheet->getCell($coordinate);
                $cellValue = $cell->getValue();

                if (! is_string($cellValue)) {
                    continue;
                }

                if (str_starts_with($cellValue, '=')) {
                    continue;
                }

                $sheetTocValue = self::resolveSheetTocDirective($cellValue, $context);

                if ($sheetTocValue !== null) {
                    $sheet->setCellValue($coordinate, $sheetTocValue);

                    continue;
                }

                $resolvedImageGrid = self::resolveImageGridDirective($cellValue, $context);

                if ($resolvedImageGrid !== null) {
                    self::insertImageGrid($sheet, $columnIndex, $row, $resolvedImageGrid, $context);
                    $sheet->setCellValue($coordinate, '');

                    continue;
                }

                $resolvedImage = self::resolveImageDirective($cellValue, $context);

                if ($resolvedImage !== null) {
                    self::insertImage($sheet, $coordinate, $resolvedImage);
                    $sheet->setCellValue($coordinate, '');

                    continue;
                }

                $resolved = self::resolveTextPlaceholders($cellValue, $context);

                if ($resolved !== $cellValue) {
                    $sheet->setCellValue($coordinate, $resolved);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function renderPlaceholdersInRow(Worksheet $sheet, int $row, array $context): void
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $coordinate = self::coordinate($columnIndex, $row);
            $cell = $sheet->getCell($coordinate);
            $cellValue = $cell->getValue();

            if (! is_string($cellValue)) {
                continue;
            }

            if (str_starts_with($cellValue, '=')) {
                continue;
            }

            $sheetTocValue = self::resolveSheetTocDirective($cellValue, $context);

            if ($sheetTocValue !== null) {
                $sheet->setCellValue($coordinate, $sheetTocValue);

                continue;
            }

            $resolvedImageGrid = self::resolveImageGridDirective($cellValue, $context);

            if ($resolvedImageGrid !== null) {
                self::insertImageGrid($sheet, $columnIndex, $row, $resolvedImageGrid, $context);
                $sheet->setCellValue($coordinate, '');

                continue;
            }

            $resolvedImage = self::resolveImageDirective($cellValue, $context);

            if ($resolvedImage !== null) {
                self::insertImage($sheet, $coordinate, $resolvedImage);
                $sheet->setCellValue($coordinate, '');

                continue;
            }

            $resolved = self::resolveTextPlaceholders($cellValue, $context);
            $sheet->setCellValue($coordinate, $resolved);
        }
    }

    /**
     * @return array{dataset:string,options:array<string, string>,column_index:int,row:int}|null
     */
    private static function findSheetCloneDirective(Worksheet $sheet): ?array
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                $value = $sheet->getCell(self::coordinate($columnIndex, $row))->getValue();

                if (! is_string($value)) {
                    continue;
                }

                if (! preg_match('/^\{\{\s*sheetClone:([a-zA-Z0-9_.-]+)(.*?)\}\}$/', trim($value), $matches)) {
                    continue;
                }

                return [
                    'dataset' => (string) $matches[1],
                    'options' => self::parseDirectiveOptions((string) ($matches[2] ?? '')),
                    'column_index' => $columnIndex,
                    'row' => $row,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{dataset:string,column_index:int,row:int}|null
     */
    private static function findSheetHideIfEmptyDirective(Worksheet $sheet): ?array
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                $value = $sheet->getCell(self::coordinate($columnIndex, $row))->getValue();

                if (! is_string($value)) {
                    continue;
                }

                if (! preg_match('/^\{\{\s*sheetHideIfEmpty:([a-zA-Z0-9_.-]+)\s*\}\}$/', trim($value), $matches)) {
                    continue;
                }

                return [
                    'dataset' => (string) $matches[1],
                    'column_index' => $columnIndex,
                    'row' => $row,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{order:float,column_index:int,row:int}|null
     */
    private static function findSheetOrderDirective(Worksheet $sheet): ?array
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                $value = $sheet->getCell(self::coordinate($columnIndex, $row))->getValue();

                if (! is_string($value)) {
                    continue;
                }

                if (! preg_match('/^\{\{\s*sheetOrder:([0-9]+(?:\.[0-9]+)?)\s*\}\}$/', trim($value), $matches)) {
                    continue;
                }

                return [
                    'order' => (float) $matches[1],
                    'column_index' => $columnIndex,
                    'row' => $row,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function shouldHideSheetByDirective(Worksheet $sheet, array $context): bool
    {
        $directive = self::findSheetHideIfEmptyDirective($sheet);

        if ($directive === null) {
            return false;
        }

        self::clearDirectiveCell($sheet, $directive['column_index'], $directive['row']);
        $resolved = self::resolvePath($context, $directive['dataset']);

        if ($resolved === null) {
            return true;
        }

        if (is_string($resolved)) {
            return trim($resolved) === '';
        }

        if (is_bool($resolved)) {
            return $resolved === false;
        }

        if (is_iterable($resolved)) {
            foreach ($resolved as $_item) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function resolveSheetOrderDirective(Worksheet $sheet, float $defaultOrder): float
    {
        $directive = self::findSheetOrderDirective($sheet);

        if ($directive === null) {
            return $defaultOrder;
        }

        self::clearDirectiveCell($sheet, $directive['column_index'], $directive['row']);

        return $directive['order'];
    }

    /**
     * @param  array<int, float>  $sheetOrders
     */
    private static function applySheetOrdering(Spreadsheet $spreadsheet, array $sheetOrders): void
    {
        $entries = [];

        foreach ($spreadsheet->getAllSheets() as $index => $sheet) {
            $sheetId = spl_object_id($sheet);
            $entries[] = [
                'sheet' => $sheet,
                'index' => $index,
                'order' => $sheetOrders[$sheetId] ?? ($index + 1),
            ];
        }

        usort($entries, static function (array $left, array $right): int {
            if ($left['order'] === $right['order']) {
                return $left['index'] <=> $right['index'];
            }

            return $left['order'] <=> $right['order'];
        });

        foreach ($entries as $targetIndex => $entry) {
            $sheet = $entry['sheet'];
            $currentIndex = $spreadsheet->getIndex($sheet);

            if ($currentIndex !== $targetIndex) {
                $spreadsheet->setIndexByName($sheet->getTitle(), $targetIndex);
            }
        }
    }

    private static function clearDirectiveCell(Worksheet $sheet, int $columnIndex, int $row): void
    {
        $sheet->setCellValue(self::coordinate($columnIndex, $row), '');
    }

    /**
     * @param  array<int, string>  $usedNames
     */
    private static function makeUniqueSheetName(string $candidate, array &$usedNames): string
    {
        $base = self::sanitizeSheetName($candidate);
        $lower = mb_strtolower($base);

        if (! in_array($lower, $usedNames, true)) {
            $usedNames[] = $lower;

            return $base;
        }

        $counter = 2;

        while ($counter <= 9999) {
            $suffix = ' ('.$counter.')';
            $maxBaseLength = max(1, 31 - mb_strlen($suffix));
            $name = mb_substr($base, 0, $maxBaseLength).$suffix;
            $nameLower = mb_strtolower($name);

            if (! in_array($nameLower, $usedNames, true)) {
                $usedNames[] = $nameLower;

                return $name;
            }

            $counter++;
        }

        $fallback = 'Sheet';
        $fallbackLower = mb_strtolower($fallback);

        if (! in_array($fallbackLower, $usedNames, true)) {
            $usedNames[] = $fallbackLower;

            return $fallback;
        }

        return $fallback.' '.count($usedNames);
    }

    private static function sanitizeSheetName(string $name): string
    {
        $normalized = trim($name);

        if ($normalized === '') {
            $normalized = 'Sheet';
        }

        $normalized = preg_replace('/[\[\]:*?\/\\\\]+/', '-', $normalized) ?? $normalized;
        $normalized = str_replace("\n", ' ', $normalized);
        $normalized = trim($normalized, " '\"");

        if ($normalized === '') {
            $normalized = 'Sheet';
        }

        if (mb_strlen($normalized) > 31) {
            $normalized = mb_substr($normalized, 0, 31);
        }

        return $normalized;
    }

    private static function copyTemplateRow(Worksheet $sheet, int $templateRow, int $targetRow): void
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $sourceCoordinate = self::coordinate($columnIndex, $templateRow);
            $targetCoordinate = self::coordinate($columnIndex, $targetRow);
            $sourceCell = $sheet->getCell($sourceCoordinate);
            $sourceValue = $sourceCell->getValue();

            $sheet->duplicateStyle($sheet->getStyle($sourceCoordinate), $targetCoordinate);

            if (is_string($sourceValue) && str_starts_with($sourceValue, '=')) {
                $sheet->setCellValue($targetCoordinate, self::shiftFormulaRowReferences($sourceValue, $templateRow, $targetRow));

                continue;
            }

            $sheet->setCellValue($targetCoordinate, $sourceValue);
        }

        $sheet
            ->getRowDimension($targetRow)
            ->setRowHeight($sheet->getRowDimension($templateRow)->getRowHeight());
    }

    /**
     * @return array{dataset:string,options:array<string, string>,column_index:int}|null
     */
    private static function findRowsDirectiveInRow(Worksheet $sheet, int $row): ?array
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $value = $sheet->getCell(self::coordinate($columnIndex, $row))->getValue();

            if (! is_string($value)) {
                continue;
            }

            if (! preg_match('/^\{\{\s*rows:([a-zA-Z0-9_.-]+)(.*?)\}\}$/', trim($value), $matches)) {
                continue;
            }

            return [
                'dataset' => (string) $matches[1],
                'options' => self::parseDirectiveOptions((string) ($matches[2] ?? '')),
                'column_index' => $columnIndex,
            ];
        }

        return null;
    }

    private static function findRowsDirectiveEndRow(Worksheet $sheet, int $startRow, int $highestRow): ?int
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($row = $startRow; $row <= $highestRow; $row++) {
            for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                $value = $sheet->getCell(self::coordinate($columnIndex, $row))->getValue();

                if (! is_string($value)) {
                    continue;
                }

                if (preg_match('/^\{\{\s*\/rows\s*\}\}$/', trim($value)) === 1) {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array{dataset:string,options:array<string, string>,column_index:int}  $directive
     * @return array<int, array{is_group_header:bool,is_group_footer?:bool,label?:string,row?:array<string, mixed>,index?:int,group_key?:string,group_index?:int,subtotal_column_index?:int}>
     */
    private static function recordsForRowsDirective(array $context, array $directive): array
    {
        $raw = self::resolvePath($context, $directive['dataset']);

        if (! is_iterable($raw)) {
            return [];
        }

        $rows = [];

        foreach ($raw as $item) {
            $rows[] = self::normalizeRecord($item);
        }

        $groupBy = trim((string) ($directive['options']['by'] ?? ''));

        if ($groupBy === '') {
            return collect($rows)
                ->values()
                ->map(static fn (array $row, int $index): array => [
                    'is_group_header' => false,
                    'row' => $row,
                    'index' => $index + 1,
                ])
                ->all();
        }

        $groupLabel = trim((string) ($directive['options']['groupLabel'] ?? 'Groep'));
        $subtotalLabelBase = trim((string) ($directive['options']['subtotalLabel'] ?? 'Subtotaal'));
        $subtotalColumnIndex = self::columnIndexFromOptionValue((string) ($directive['options']['subtotalColumn'] ?? ''));
        $result = [];
        $groupIndex = 0;

        foreach (collect($rows)->groupBy(static fn (array $row): string => (string) (self::resolvePath($row, $groupBy) ?? '')) as $groupKey => $groupRows) {
            $groupIndex++;
            $result[] = [
                'is_group_header' => true,
                'label' => sprintf('%s: %s', $groupLabel, $groupKey === '' ? '-' : $groupKey),
            ];

            foreach ($groupRows->values() as $index => $row) {
                $result[] = [
                    'is_group_header' => false,
                    'row' => $row,
                    'index' => $index + 1,
                    'group_key' => $groupKey,
                    'group_index' => $groupIndex,
                ];
            }

            if ($subtotalColumnIndex > 0) {
                $result[] = [
                    'is_group_header' => false,
                    'is_group_footer' => true,
                    'label' => sprintf('%s: %s', $subtotalLabelBase, $groupKey === '' ? '-' : $groupKey),
                    'group_key' => $groupKey,
                    'group_index' => $groupIndex,
                    'subtotal_column_index' => $subtotalColumnIndex,
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function resolveTextPlaceholders(string $value, array $context): mixed
    {
        if (preg_match('/^\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}$/', trim($value), $matches) === 1) {
            $resolved = self::resolvePath($context, (string) $matches[1]);

            if ($resolved === null) {
                return $value;
            }

            if (is_scalar($resolved)) {
                return $resolved;
            }

            return json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', static function (array $matches) use ($context): string {
            $resolved = self::resolvePath($context, (string) ($matches[1] ?? ''));

            if ($resolved === null) {
                return (string) ($matches[0] ?? '');
            }

            if (is_scalar($resolved)) {
                return (string) $resolved;
            }

            return json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }, $value) ?? $value;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{path:string,width?:int,height?:int}|null
     */
    private static function resolveImageDirective(string $value, array $context): ?array
    {
        if (preg_match('/^\{\{\s*image:([a-zA-Z0-9_.-]+)(.*?)\}\}$/', trim($value), $matches) !== 1) {
            return null;
        }

        $pathExpression = (string) ($matches[1] ?? '');
        $options = self::parseDirectiveOptions((string) ($matches[2] ?? ''));
        $resolved = self::resolvePath($context, $pathExpression);

        if (is_array($resolved)) {
            $resolved = Arr::get($resolved, 'path');
        }

        if (! is_string($resolved) || trim($resolved) === '') {
            return null;
        }

        $imagePath = self::resolveImagePath($resolved);

        if ($imagePath === null) {
            return null;
        }

        $payload = ['path' => $imagePath];

        if (isset($options['width']) && is_numeric($options['width'])) {
            $payload['width'] = (int) $options['width'];
        }

        if (isset($options['height']) && is_numeric($options['height'])) {
            $payload['height'] = (int) $options['height'];
        }

        return $payload;
    }

    /**
     * @param  array{path:string,width?:int,height?:int}  $image
     */
    private static function insertImage(Worksheet $sheet, string $coordinate, array $image): void
    {
        $drawing = new Drawing;
        $drawing->setPath($image['path'], true);
        $drawing->setCoordinates($coordinate);

        if (array_key_exists('width', $image) && $image['width'] > 0) {
            $drawing->setWidth($image['width']);
        }

        if (array_key_exists('height', $image) && $image['height'] > 0) {
            $drawing->setHeight($image['height']);
        }

        $drawing->setWorksheet($sheet);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{rows:array<int, array<string, mixed>>,columns:int,span:int,width:int,height:int,caption?:string,caption_below:bool,row_gap:int,image:string,start_row_offset:int,start_col_offset:int,page_break_every:int,page_break_start:int,auto_page_break:bool,page_rows:int,first_page_rows:int}|null
     */
    private static function resolveImageGridDirective(string $value, array $context): ?array
    {
        if (preg_match('/^\{\{\s*imageGrid:([a-zA-Z0-9_.-]+)(.*?)\}\}$/', trim($value), $matches) !== 1) {
            return null;
        }

        $datasetPath = (string) ($matches[1] ?? '');
        $options = self::parseDirectiveOptions((string) ($matches[2] ?? ''));
        $raw = self::resolvePath($context, $datasetPath);

        if (! is_iterable($raw)) {
            return null;
        }

        $rows = [];

        foreach ($raw as $item) {
            $rows[] = self::normalizeRecord($item);
        }

        if ($rows === []) {
            return null;
        }

        $layout = strtolower(trim((string) ($options['layout'] ?? '')));
        $layoutDefaults = self::imageGridLayoutDefaults($layout);

        $columns = max(1, min(8, (int) ($options['columns'] ?? ($layoutDefaults['columns'] ?? 3))));
        $span = max(1, min(6, (int) ($options['span'] ?? ($layoutDefaults['span'] ?? 2))));
        $width = max(20, min(1200, (int) ($options['width'] ?? ($layoutDefaults['width'] ?? 160))));
        $height = max(20, min(1200, (int) ($options['height'] ?? ($layoutDefaults['height'] ?? 110))));
        $captionBelow = self::optionToBool($options['captionBelow'] ?? null, (bool) ($layoutDefaults['captionBelow'] ?? true));
        $rowGap = max(0, min(20, (int) ($options['rowGap'] ?? ($layoutDefaults['rowGap'] ?? 0))));
        $startRowOffset = max(-200, min(200, (int) ($options['startRowOffset'] ?? 0)));
        $startColOffset = max(-200, min(200, (int) ($options['startColOffset'] ?? 0)));
        $pageBreakEvery = max(0, min(5000, (int) ($options['pageBreakEvery'] ?? 0)));
        $pageBreakStart = max(1, min(5000, (int) ($options['pageBreakStart'] ?? 1)));
        $autoPageBreak = self::optionToBool($options['autoPageBreak'] ?? null, (bool) ($layoutDefaults['autoPageBreak'] ?? false));
        $pageRows = max(1, min(5000, (int) ($options['pageRows'] ?? ($layoutDefaults['pageRows'] ?? 45))));
        $firstPageRows = max(0, min(5000, (int) ($options['firstPageRows'] ?? ($layoutDefaults['firstPageRows'] ?? 0))));
        $imageExpression = trim((string) ($options['image'] ?? 'path'));

        if ($imageExpression === '') {
            $imageExpression = 'path';
        }

        return [
            'rows' => $rows,
            'columns' => $columns,
            'span' => $span,
            'width' => $width,
            'height' => $height,
            'caption' => isset($options['caption']) ? trim((string) $options['caption']) : '',
            'caption_below' => $captionBelow,
            'row_gap' => $rowGap,
            'image' => $imageExpression,
            'start_row_offset' => $startRowOffset,
            'start_col_offset' => $startColOffset,
            'page_break_every' => $pageBreakEvery,
            'page_break_start' => $pageBreakStart,
            'auto_page_break' => $autoPageBreak,
            'page_rows' => $pageRows,
            'first_page_rows' => $firstPageRows,
        ];
    }

    /**
     * @return array<string, int|bool>
     */
    private static function imageGridLayoutDefaults(string $layout): array
    {
        return match ($layout) {
            'a4-portrait', 'portrait' => [
                'columns' => 2,
                'span' => 3,
                'width' => 240,
                'height' => 160,
                'captionBelow' => true,
                'rowGap' => 1,
                'autoPageBreak' => true,
                'pageRows' => 42,
                'firstPageRows' => 34,
            ],
            'a4-landscape', 'landscape' => [
                'columns' => 4,
                'span' => 2,
                'width' => 130,
                'height' => 90,
                'captionBelow' => true,
                'rowGap' => 1,
                'autoPageBreak' => true,
                'pageRows' => 28,
                'firstPageRows' => 22,
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function resolveSheetTocDirective(string $value, array $context): ?string
    {
        if (preg_match('/^\{\{\s*sheetToc(.*?)\}\}$/', trim($value), $matches) !== 1) {
            return null;
        }

        $options = self::parseDirectiveOptions((string) ($matches[1] ?? ''));
        $sheetNamesRaw = self::resolvePath($context, 'workbook.sheet_names');

        if (! is_iterable($sheetNamesRaw)) {
            return '';
        }

        $sheetNames = [];

        foreach ($sheetNamesRaw as $sheetName) {
            $sheetNames[] = trim((string) $sheetName);
        }

        if ($sheetNames === []) {
            return '';
        }

        $numbered = self::optionToBool($options['numbered'] ?? null, true);
        $start = max(1, (int) ($options['start'] ?? 1));
        $separatorRaw = (string) ($options['separator'] ?? '\\n');
        $separator = str_replace(['\\n', '\\t'], ["\n", "\t"], $separatorRaw);
        $lines = [];

        foreach ($sheetNames as $index => $sheetName) {
            if ($numbered) {
                $lines[] = ($start + $index).'. '.$sheetName;

                continue;
            }

            $lines[] = $sheetName;
        }

        return implode($separator, $lines);
    }

    /**
     * @param  array{rows:array<int, array<string, mixed>>,columns:int,span:int,width:int,height:int,caption?:string,caption_below:bool,row_gap:int,image:string,start_row_offset:int,start_col_offset:int,page_break_every:int,page_break_start:int,auto_page_break:bool,page_rows:int,first_page_rows:int}  $grid
     * @param  array<string, mixed>  $context
     */
    private static function insertImageGrid(
        Worksheet $sheet,
        int $startColumnIndex,
        int $startRow,
        array $grid,
        array $context,
    ): void {
        $captionExpression = self::rowScopedExpression((string) ($grid['caption'] ?? ''));
        $imageExpression = self::rowScopedExpression((string) ($grid['image'] ?? 'path'));
        $columns = $grid['columns'];
        $span = $grid['span'];
        $width = $grid['width'];
        $height = $grid['height'];
        $captionBelow = $grid['caption_below'];
        $rowGap = $grid['row_gap'];
        $pageBreakEvery = $grid['page_break_every'];
        $pageBreakStart = $grid['page_break_start'];
        $autoPageBreak = $grid['auto_page_break'] && $pageBreakEvery <= 0;
        $pageRows = $grid['page_rows'];
        $firstPageRows = $grid['first_page_rows'];
        $baseStartRow = max(1, $startRow + $grid['start_row_offset']);
        $baseStartColumnIndex = max(1, $startColumnIndex + $grid['start_col_offset']);
        $hasCaption = $captionExpression !== '';
        $captionUsesExtraRow = $hasCaption && $captionBelow;
        $rowBlockSize = ($captionUsesExtraRow ? 2 : 1) + $rowGap;
        $breakRows = [];
        $totalPhotos = count($grid['rows']);
        $eligibleTotalPhotos = max(0, $totalPhotos - $pageBreakStart + 1);
        $firstPagePhotos = 0;
        $nextPagePhotos = 0;

        if ($autoPageBreak && $eligibleTotalPhotos > 0) {
            $effectiveFirstPageRows = $firstPageRows > 0
                ? $firstPageRows
                : max(1, $pageRows - ($baseStartRow - 1));

            $firstPagePhotos = max(1, (int) floor($effectiveFirstPageRows / $rowBlockSize) * $columns);
            $nextPagePhotos = max(1, (int) floor($pageRows / $rowBlockSize) * $columns);
        }

        foreach ($grid['rows'] as $index => $item) {
            $rowGroup = intdiv($index, $columns);
            $columnGroup = $index % $columns;
            $targetColumnIndex = $baseStartColumnIndex + ($columnGroup * $span);
            $targetRow = $baseStartRow + ($rowGroup * $rowBlockSize);
            $coordinate = self::coordinate($targetColumnIndex, $targetRow);
            $rowContext = array_merge($context, [
                'row' => $item,
                'item' => $item,
                'index' => $index + 1,
            ]);

            $resolved = self::resolvePath($rowContext, $imageExpression);

            if (is_array($resolved)) {
                $resolved = Arr::get($resolved, 'path');
            }

            if (is_string($resolved)) {
                $imagePath = self::resolveImagePath($resolved);

                if ($imagePath !== null) {
                    self::insertImage($sheet, $coordinate, [
                        'path' => $imagePath,
                        'width' => $width,
                        'height' => $height,
                    ]);
                }
            }

            self::applyMinimumRowHeight($sheet, $targetRow, ($height * 0.75) + 6);

            if ($hasCaption) {
                $captionValue = self::resolvePath($rowContext, $captionExpression);

                if ($captionValue !== null) {
                    if ($captionBelow || $span <= 1) {
                        $captionRow = $targetRow + 1;
                        $sheet->setCellValue(
                            self::coordinate($targetColumnIndex, $captionRow),
                            self::scalarToString($captionValue),
                        );
                        self::applyMinimumRowHeight($sheet, $captionRow, 18.0);
                    } else {
                        $captionColumn = $targetColumnIndex + 1;
                        $sheet->setCellValue(
                            self::coordinate($captionColumn, $targetRow),
                            self::scalarToString($captionValue),
                        );
                    }
                }
            }

            $photoNumber = $index + 1;

            if ($photoNumber < $pageBreakStart) {
                continue;
            }

            $relativePhotoNumber = $photoNumber - $pageBreakStart + 1;

            if ($pageBreakEvery > 0 && $photoNumber >= $pageBreakStart) {
                $breakIndex = $relativePhotoNumber;

                if ($breakIndex > 0 && $breakIndex % $pageBreakEvery === 0) {
                    $breakRows[$targetRow + $rowBlockSize] = true;
                }

                continue;
            }

            if (! $autoPageBreak || $eligibleTotalPhotos <= 0) {
                continue;
            }

            if ($relativePhotoNumber === $firstPagePhotos && $eligibleTotalPhotos > $firstPagePhotos) {
                $breakRows[$targetRow + $rowBlockSize] = true;

                continue;
            }

            if ($relativePhotoNumber <= $firstPagePhotos || $nextPagePhotos <= 0) {
                continue;
            }

            $afterFirstPage = $relativePhotoNumber - $firstPagePhotos;

            if ($afterFirstPage > 0 && $afterFirstPage % $nextPagePhotos === 0 && $afterFirstPage < ($eligibleTotalPhotos - $firstPagePhotos)) {
                $breakRows[$targetRow + $rowBlockSize] = true;
            }
        }

        foreach (array_keys($breakRows) as $breakRow) {
            if ($breakRow < 2) {
                continue;
            }

            $sheet->setBreak(self::coordinate(1, (int) $breakRow), Worksheet::BREAK_ROW);
        }
    }

    private static function optionToBool(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return $default;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        return $default;
    }

    private static function applyMinimumRowHeight(Worksheet $sheet, int $row, float $minimumHeight): void
    {
        $rowDimension = $sheet->getRowDimension($row);
        $current = (float) $rowDimension->getRowHeight();

        if ($current <= 0) {
            $rowDimension->setRowHeight($minimumHeight);

            return;
        }

        if ($minimumHeight > $current) {
            $rowDimension->setRowHeight($minimumHeight);
        }
    }

    private static function scalarToString(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private static function rowScopedExpression(string $expression): string
    {
        $normalized = trim($expression);

        if ($normalized === '' || str_contains($normalized, '.')) {
            return $normalized;
        }

        return 'row.'.$normalized;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private static function resolvePath(array $source, string $path): mixed
    {
        $normalizedPath = trim($path);

        if ($normalizedPath === '') {
            return null;
        }

        return Arr::get($source, $normalizedPath);
    }

    /**
     * @return array<string, string>
     */
    private static function parseDirectiveOptions(string $options): array
    {
        $result = [];

        if (preg_match_all('/([a-zA-Z_][a-zA-Z0-9_]*)=("[^"]*"|\'[^\']*\'|[^\s]+)/', $options, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $key = (string) ($match[1] ?? '');
                $rawValue = (string) ($match[2] ?? '');
                $trimmed = trim($rawValue);
                $result[$key] = trim($trimmed, "\"'");
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|object  $item
     * @return array<string, mixed>
     */
    private static function normalizeRecord(mixed $item): array
    {
        if (is_array($item)) {
            return $item;
        }

        if (is_object($item)) {
            return (array) $item;
        }

        return ['value' => $item];
    }

    private static function shiftFormulaRowReferences(string $formula, int $sourceRow, int $targetRow): string
    {
        if ($sourceRow === $targetRow) {
            return $formula;
        }

        return preg_replace_callback('/(\$?[A-Z]{1,3})(\$?)(\d+)/', static function (array $matches) use ($sourceRow, $targetRow): string {
            $column = (string) ($matches[1] ?? '');
            $isAbsoluteRow = (string) ($matches[2] ?? '') === '$';
            $rowNumber = (int) ($matches[3] ?? 0);

            if ($isAbsoluteRow || $rowNumber !== $sourceRow) {
                return (string) ($matches[0] ?? '');
            }

            return $column.$targetRow;
        }, $formula) ?? $formula;
    }

    private static function resolveImagePath(string $value): ?string
    {
        $candidate = trim($value);

        if ($candidate === '') {
            return null;
        }

        if (str_starts_with($candidate, 'private:')) {
            $candidate = ltrim(substr($candidate, 8), '/');
        }

        if (! str_starts_with($candidate, '/')) {
            if (! Storage::disk('private')->exists($candidate)) {
                return null;
            }

            $candidate = Storage::disk('private')->path($candidate);
        }

        $storageAppPath = storage_path('app');

        if (! str_starts_with($candidate, $storageAppPath)) {
            return null;
        }

        if (! is_file($candidate)) {
            return null;
        }

        $extension = strtolower((string) pathinfo($candidate, PATHINFO_EXTENSION));

        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp'], true)) {
            return null;
        }

        return $candidate;
    }

    private static function columnIndexFromOptionValue(string $value): int
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return 0;
        }

        if (ctype_digit($normalized)) {
            return max(0, (int) $normalized);
        }

        if (preg_match('/^[A-Za-z]{1,3}$/', $normalized) === 1) {
            return Coordinate::columnIndexFromString(strtoupper($normalized));
        }

        return 0;
    }

    private static function writerTypeForPath(string $outputPath): string
    {
        $extension = strtolower((string) pathinfo($outputPath, PATHINFO_EXTENSION));

        if ($extension === 'ods') {
            return 'Ods';
        }

        if ($extension === 'xlsx') {
            return 'Xlsx';
        }

        throw new RuntimeException(__('query_builder_ui.runtime.spreadsheet_template_unknown_format'));
    }

    private static function coordinate(int $columnIndex, int $row): string
    {
        return Coordinate::stringFromColumnIndex($columnIndex).$row;
    }
}
