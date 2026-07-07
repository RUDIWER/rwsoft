<?php

namespace App\Actions\Admin\Base\Query;

class BuildQueryFromBuilderAction
{
    /** @var array<int, string> */
    private const ALLOWED_CONDITIONS = [
        '=',
        '!=',
        '>',
        '<',
        '>=',
        '<=',
        'LIKE',
        'NOT LIKE',
        'IN',
        'NOT IN',
        'BETWEEN',
        'NOT BETWEEN',
        'IS',
        'IS NOT',
        'IS NULL',
        'IS NOT NULL',
    ];

    /** @var array<int, string> */
    private const AGGREGATE_FUNCTIONS = [
        'COUNT',
        'SUM',
        'MIN',
        'MAX',
        'AVG',
        'GROUP_CONCAT',
        'CONCAT',
        'FORMULA',
    ];

    /** @var array<int, string> */
    private const ALLOWED_FORMULA_FUNCTIONS = [
        'ABS',
        'AVG',
        'CASE',
        'CEIL',
        'COALESCE',
        'CONCAT',
        'CONCAT_WS',
        'COUNT',
        'ELSE',
        'END',
        'FLOOR',
        'GREATEST',
        'IFNULL',
        'LEAST',
        'MAX',
        'MIN',
        'NULLIF',
        'POWER',
        'ROUND',
        'SQRT',
        'SUM',
        'THEN',
        'WHEN',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array{query:string,test_query:string}
     */
    public static function handle(array $payload): array
    {
        $baseTable = self::normalizeIdentifier((string) ($payload['table_name'] ?? ''));

        if ($baseTable === '') {
            return [
                'query' => '',
                'test_query' => '',
            ];
        }

        $querySql = self::buildSql($payload, $baseTable, false);
        $testSql = self::buildSql($payload, $baseTable, true);

        return [
            'query' => $querySql,
            'test_query' => $testSql,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public static function validate(array $payload): array
    {
        $errors = [];

        $baseTable = self::normalizeIdentifier((string) ($payload['table_name'] ?? ''));

        if ($baseTable === '') {
            $errors['table_name'] = 'Kies een geldige basistabel voor de builder query.';
        }

        $selectedFields = self::normalizeIdentifierList((array) ($payload['selected_fields'] ?? []));
        $groupRows = self::normalizeIdentifierList((array) ($payload['group_rows'] ?? []));
        $aggregateRows = (array) ($payload['aggregate_rows'] ?? []);

        $whereErrors = self::validateWhereRows((array) ($payload['where_rows'] ?? []));

        foreach ($whereErrors as $field => $message) {
            $errors[$field] = $message;
        }

        $whereHierarchyErrors = self::validateLogicalRowHierarchy(
            (array) ($payload['where_rows'] ?? []),
            'where_rows',
        );

        foreach ($whereHierarchyErrors as $field => $message) {
            $errors[$field] = $message;
        }

        $havingHierarchyErrors = self::validateLogicalRowHierarchy(
            (array) ($payload['having_rows'] ?? []),
            'having_rows',
        );

        foreach ($havingHierarchyErrors as $field => $message) {
            $errors[$field] = $message;
        }

        [$aggregateErrors, $knownAliases] = self::validateAggregateRows(
            $aggregateRows,
            $selectedFields,
            $groupRows,
        );

        foreach ($aggregateErrors as $field => $message) {
            $errors[$field] = $message;
        }

        $havingErrors = self::validateHavingRows(
            (array) ($payload['having_rows'] ?? []),
            $selectedFields,
            $groupRows,
            $knownAliases,
        );

        foreach ($havingErrors as $field => $message) {
            $errors[$field] = $message;
        }

        return $errors;
    }

    /**
     * @param  array<int, mixed>  $whereRows
     * @return array<string, string>
     */
    private static function validateWhereRows(array $whereRows): array
    {
        $errors = [];
        $allowedValueTypes = ['Waarde', 'Vaste waarde', 'Parameter', 'Systeemvariabele', 'Json Array'];

        foreach ($whereRows as $index => $whereRow) {
            if (! is_array($whereRow)) {
                continue;
            }

            if (! self::hasAnyValue($whereRow, ['whereField', 'whereFieldCondition', 'varOrValue', 'value', 'value_to', 'variabele', 'variabele_to', 'testValue', 'testValueTo'])) {
                continue;
            }

            $whereField = trim((string) ($whereRow['whereField'] ?? ''));

            if ($whereField === '' || self::normalizeIdentifier($whereField) === '') {
                $errors[sprintf('where_rows.%d.whereField', $index)] = 'WHERE veld is verplicht en moet in dot-notatie staan (tabel.kolom).';
            }

            $rawCondition = trim((string) ($whereRow['whereFieldCondition'] ?? '='));
            $normalizedCondition = self::normalizeCondition($rawCondition);

            if ($normalizedCondition !== strtoupper($rawCondition)) {
                $errors[sprintf('where_rows.%d.whereFieldCondition', $index)] = 'Kies een geldige WHERE conditie.';
            }

            $valueType = trim((string) ($whereRow['varOrValue'] ?? 'Vaste waarde'));
            $isNullCondition = in_array($normalizedCondition, ['IS NULL', 'IS NOT NULL'], true);
            $isRangeCondition = in_array($normalizedCondition, ['BETWEEN', 'NOT BETWEEN'], true);

            if (! in_array($valueType, $allowedValueTypes, true)) {
                $errors[sprintf('where_rows.%d.varOrValue', $index)] = 'Kies een geldig waarde type.';
            }

            if (! $isNullCondition && $valueType === 'Parameter') {
                $parameter = self::normalizeParameter((string) ($whereRow['variabele'] ?? ''));

                if ($parameter === '') {
                    $errors[sprintf('where_rows.%d.variabele', $index)] = 'Geef een geldige parameternaam op voor WHERE.';
                }

                if ($isRangeCondition) {
                    $parameterTo = self::normalizeParameter((string) ($whereRow['variabele_to'] ?? ''));

                    if ($parameterTo === '') {
                        $errors[sprintf('where_rows.%d.variabele_to', $index)] = 'Geef een tweede parameternaam op voor BETWEEN/NOT BETWEEN.';
                    }
                }
            }

            if ($isRangeCondition) {
                if ($valueType === 'Json Array') {
                    $errors[sprintf('where_rows.%d.varOrValue', $index)] = 'Json Array is niet geldig voor BETWEEN/NOT BETWEEN.';
                }

                if ($valueType !== 'Parameter') {
                    $fromValue = trim((string) ($whereRow['value'] ?? ''));
                    $toValue = trim((string) ($whereRow['value_to'] ?? ''));

                    if ($toValue === '') {
                        $fallbackTo = self::secondListValue($whereRow['value'] ?? '');
                        $toValue = $fallbackTo ?? '';
                    }

                    if ($fromValue === '') {
                        $errors[sprintf('where_rows.%d.value', $index)] = 'Startwaarde is verplicht voor BETWEEN/NOT BETWEEN.';
                    }

                    if ($toValue === '') {
                        $errors[sprintf('where_rows.%d.value_to', $index)] = 'Eindwaarde is verplicht voor BETWEEN/NOT BETWEEN.';
                    }
                }
            }

            if (in_array($normalizedCondition, ['IN', 'NOT IN'], true) && $valueType === 'Json Array') {
                if (self::listValues($whereRow['value'] ?? '') === []) {
                    $errors[sprintf('where_rows.%d.value', $index)] = 'Json Array voor IN/NOT IN mag niet leeg zijn.';
                }
            }

            if (
                ! $isNullCondition
                && ! $isRangeCondition
                && $valueType !== 'Parameter'
                && ! in_array($normalizedCondition, ['IS', 'IS NOT'], true)
                && trim((string) ($whereRow['value'] ?? '')) === ''
            ) {
                $errors[sprintf('where_rows.%d.value', $index)] = 'Waarde is verplicht voor deze WHERE conditie.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<string, string>
     */
    private static function validateLogicalRowHierarchy(array $rows, string $section): array
    {
        $errors = [];
        $rowMeta = [];
        $idToIndex = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = isset($row['id']) ? (int) $row['id'] : (int) $index;
            $isSubRow = (bool) ($row['subRow'] ?? false);
            $hasParent = array_key_exists('parentId', $row)
                && $row['parentId'] !== null
                && (string) $row['parentId'] !== '';
            $parentId = $hasParent ? (int) $row['parentId'] : null;

            $rowMeta[(int) $index] = [
                'id' => $id,
                'subRow' => $isSubRow,
                'parentId' => $parentId,
            ];

            if (isset($idToIndex[$id])) {
                $errors[sprintf('%s.%d.id', $section, $index)] = 'Rij-ID moet uniek zijn binnen deze filterset.';
            } else {
                $idToIndex[$id] = (int) $index;
            }

            if ($isSubRow && $parentId === null) {
                $errors[sprintf('%s.%d.parentId', $section, $index)] = 'Subrij vereist een geldige parent regel.';
            }

            if (! $isSubRow && $parentId !== null) {
                $errors[sprintf('%s.%d.parentId', $section, $index)] = 'Alleen subrijen mogen een parent regel hebben.';
            }

            if ($isSubRow && $parentId !== null && $parentId === $id) {
                $errors[sprintf('%s.%d.parentId', $section, $index)] = 'Een subrij kan zichzelf niet als parent hebben.';
            }
        }

        foreach ($rowMeta as $index => $meta) {
            $isSubRow = (bool) ($meta['subRow'] ?? false);

            if (! $isSubRow) {
                continue;
            }

            $parentId = $meta['parentId'] ?? null;

            if (! is_int($parentId)) {
                continue;
            }

            if (! array_key_exists($parentId, $idToIndex)) {
                $errors[sprintf('%s.%d.parentId', $section, $index)] = 'Parent regel van subrij bestaat niet (meer).';

                continue;
            }

            $parentIndex = (int) $idToIndex[$parentId];
            $parentMeta = $rowMeta[$parentIndex] ?? null;

            if (! is_array($parentMeta)) {
                $errors[sprintf('%s.%d.parentId', $section, $index)] = 'Parent regel van subrij is ongeldig.';

                continue;
            }

            if ((bool) ($parentMeta['subRow'] ?? false)) {
                $errors[sprintf('%s.%d.parentId', $section, $index)] = 'Een subrij mag enkel aan een hoofdregel gekoppeld zijn.';
            }

            if ($parentIndex >= $index) {
                $errors[sprintf('%s.%d.parentId', $section, $index)] = 'Subrij moet na zijn parent regel staan.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function buildSql(array $payload, string $baseTable, bool $testMode): string
    {
        $useGrouping = (bool) ($payload['group_by'] ?? false);
        $allFields = (bool) ($payload['all_fields'] ?? false);
        $distinctSelect = (bool) ($payload['distinct_select'] ?? false);

        $selectedFields = self::normalizeIdentifierList((array) ($payload['selected_fields'] ?? []));
        $groupRows = self::normalizeIdentifierList((array) ($payload['group_rows'] ?? []));
        $aggregateRows = (array) ($payload['aggregate_rows'] ?? []);
        $aggregateFieldIdentifiers = self::collectAggregateFieldIdentifiers($aggregateRows);
        $allowedFormulaIdentifiers = array_values(array_unique([
            ...$selectedFields,
            ...$groupRows,
            ...$aggregateFieldIdentifiers,
        ]));

        $selectParts = [];

        if ($useGrouping) {
            foreach ($groupRows as $groupField) {
                if (! in_array($groupField, $selectParts, true)) {
                    $selectParts[] = $groupField;
                }
            }

            foreach ($selectedFields as $selectedField) {
                if (! in_array($selectedField, $selectParts, true)) {
                    $selectParts[] = $selectedField;
                }
            }

            $selectParts = [
                ...$selectParts,
                ...self::aggregateSelectParts($aggregateRows, $allowedFormulaIdentifiers),
            ];
        } else {
            if ($allFields) {
                $selectParts[] = sprintf('%s.*', $baseTable);
            } else {
                $selectParts = [...$selectedFields];
            }

            $selectParts = [
                ...$selectParts,
                ...self::aggregateSelectParts($aggregateRows, $allowedFormulaIdentifiers),
            ];
        }

        if ($selectParts === []) {
            $selectParts[] = sprintf('%s.*', $baseTable);
        }

        $lines = [];
        $distinctPrefix = $distinctSelect ? 'DISTINCT ' : '';

        $lines[] = sprintf('SELECT %s%s', $distinctPrefix, implode(', ', $selectParts));
        $lines[] = sprintf('FROM %s', $baseTable);

        foreach ((array) ($payload['join_rows'] ?? []) as $joinRow) {
            if (! is_array($joinRow)) {
                continue;
            }

            $joinLine = self::joinLine($joinRow, $baseTable);

            if ($joinLine !== '') {
                $lines[] = $joinLine;
            }
        }

        $whereLines = self::buildLogicalLines((array) ($payload['where_rows'] ?? []), 'WHERE', $testMode);
        $havingLines = self::buildLogicalLines((array) ($payload['having_rows'] ?? []), 'HAVING', $testMode);

        $lines = [...$lines, ...$whereLines];

        if ($useGrouping && $groupRows !== []) {
            $lines[] = sprintf('GROUP BY %s', implode(', ', $groupRows));
        }

        $lines = [...$lines, ...$havingLines];

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, mixed>  $aggregateRows
     * @param  array<int, string>  $selectedFields
     * @param  array<int, string>  $groupRows
     * @return array{0:array<string, string>,1:array<int, string>}
     */
    private static function validateAggregateRows(
        array $aggregateRows,
        array $selectedFields,
        array $groupRows,
    ): array {
        $errors = [];
        $knownAliases = [];
        $knownAliasLookup = [];
        $aggregateFieldIdentifiers = self::collectAggregateFieldIdentifiers($aggregateRows);
        $allowedFormulaIdentifiers = array_values(array_unique([
            ...$selectedFields,
            ...$groupRows,
            ...$aggregateFieldIdentifiers,
        ]));

        foreach ($aggregateRows as $index => $aggregateRow) {
            if (! is_array($aggregateRow)) {
                continue;
            }

            if (! self::hasAnyValue($aggregateRow, ['func', 'field', 'fields', 'formula', 'alias'])) {
                continue;
            }

            $function = strtoupper(trim((string) ($aggregateRow['func'] ?? '')));

            if (! in_array($function, self::AGGREGATE_FUNCTIONS, true)) {
                $errors[sprintf('aggregate_rows.%d.func', $index)] = 'Kies een geldige aggregate functie.';

                continue;
            }

            $alias = self::normalizeAlias((string) ($aggregateRow['alias'] ?? ''));

            if ($alias === '') {
                $errors[sprintf('aggregate_rows.%d.alias', $index)] = 'Alias is verplicht en mag enkel letters, cijfers en underscore bevatten.';
            } else {
                $aliasKey = strtolower($alias);

                if (isset($knownAliasLookup[$aliasKey])) {
                    $errors[sprintf('aggregate_rows.%d.alias', $index)] = 'Deze alias wordt al gebruikt in een vorige aggregate rij.';
                }
            }

            if ($function === 'FORMULA') {
                $formulaExpression = self::normalizeFormulaExpression(
                    (string) ($aggregateRow['formula'] ?? ''),
                    $allowedFormulaIdentifiers,
                    $knownAliases,
                );

                if ($formulaExpression === '') {
                    $formulaExpression = self::normalizeFormulaExpression(
                        (string) ($aggregateRow['field'] ?? ''),
                        $allowedFormulaIdentifiers,
                        $knownAliases,
                    );
                }

                if ($formulaExpression === '') {
                    $errors[sprintf('aggregate_rows.%d.formula', $index)] = 'Formule is ongeldig of verwijst naar onbekende alias/functie.';
                }
            } elseif ($function === 'CONCAT') {
                if (self::normalizeConcatFields($aggregateRow) === []) {
                    $errors[sprintf('aggregate_rows.%d.fields', $index)] = 'Kies minstens een geldig veld voor samenvoegen (CONCAT).';
                }
            } else {
                $field = self::normalizeAggregateField((string) ($aggregateRow['field'] ?? ''));

                if ($field === '') {
                    $errors[sprintf('aggregate_rows.%d.field', $index)] = 'Veld is verplicht voor deze aggregate functie.';
                }
            }

            if ($alias !== '' && ! isset($knownAliasLookup[strtolower($alias)])) {
                $knownAliases[] = $alias;
                $knownAliasLookup[strtolower($alias)] = true;
            }
        }

        return [$errors, $knownAliases];
    }

    /**
     * @param  array<int, mixed>  $havingRows
     * @param  array<int, string>  $selectedFields
     * @param  array<int, string>  $groupRows
     * @param  array<int, string>  $knownAliases
     * @return array<string, string>
     */
    private static function validateHavingRows(
        array $havingRows,
        array $selectedFields,
        array $groupRows,
        array $knownAliases,
    ): array {
        $errors = [];
        $havingFieldLookup = array_fill_keys(array_values(array_unique([
            ...$selectedFields,
            ...$groupRows,
            ...$knownAliases,
        ])), true);
        $aliasLookup = array_fill_keys(array_map(static fn (string $alias): string => strtolower($alias), $knownAliases), true);
        $allowedValueTypes = ['Waarde', 'Vaste waarde', 'Parameter', 'Systeemvariabele', 'Json Array'];

        foreach ($havingRows as $index => $havingRow) {
            if (! is_array($havingRow)) {
                continue;
            }

            if (! self::hasAnyValue($havingRow, ['whereField', 'whereFieldCondition', 'varOrValue', 'value', 'value_to', 'variabele', 'variabele_to', 'testValue', 'testValueTo'])) {
                continue;
            }

            $havingField = trim((string) ($havingRow['whereField'] ?? ''));

            if ($havingField === '') {
                $errors[sprintf('having_rows.%d.whereField', $index)] = 'Veld/Alias is verplicht in HAVING.';
            } else {
                $isAlias = self::normalizeSimpleIdentifier($havingField) !== '' && isset($aliasLookup[strtolower($havingField)]);
                $isKnownField = isset($havingFieldLookup[$havingField]);
                $isDottedField = self::normalizeIdentifier($havingField) !== '';

                if (! $isAlias && ! $isKnownField && ! $isDottedField) {
                    $errors[sprintf('having_rows.%d.whereField', $index)] = 'HAVING veld/alias is ongeldig.';
                }
            }

            $rawCondition = trim((string) ($havingRow['whereFieldCondition'] ?? '='));
            $normalizedCondition = self::normalizeCondition($rawCondition);

            if ($normalizedCondition !== strtoupper($rawCondition)) {
                $errors[sprintf('having_rows.%d.whereFieldCondition', $index)] = 'Kies een geldige HAVING conditie.';
            }

            $valueType = trim((string) ($havingRow['varOrValue'] ?? 'Waarde'));
            $isNullCondition = in_array($normalizedCondition, ['IS NULL', 'IS NOT NULL'], true);
            $isRangeCondition = in_array($normalizedCondition, ['BETWEEN', 'NOT BETWEEN'], true);

            if (! in_array($valueType, $allowedValueTypes, true)) {
                $errors[sprintf('having_rows.%d.varOrValue', $index)] = 'Kies een geldig waarde type.';
            }

            if (! $isNullCondition && $valueType === 'Parameter') {
                $parameter = self::normalizeParameter((string) ($havingRow['variabele'] ?? ''));

                if ($parameter === '') {
                    $errors[sprintf('having_rows.%d.variabele', $index)] = 'Geef een geldige parameternaam op voor HAVING.';
                }

                if ($isRangeCondition) {
                    $parameterTo = self::normalizeParameter((string) ($havingRow['variabele_to'] ?? ''));

                    if ($parameterTo === '') {
                        $errors[sprintf('having_rows.%d.variabele_to', $index)] = 'Geef een tweede parameternaam op voor BETWEEN/NOT BETWEEN.';
                    }
                }
            }

            if ($isRangeCondition) {
                if ($valueType === 'Json Array') {
                    $errors[sprintf('having_rows.%d.varOrValue', $index)] = 'Json Array is niet geldig voor BETWEEN/NOT BETWEEN.';
                }

                if ($valueType !== 'Parameter') {
                    $fromValue = trim((string) ($havingRow['value'] ?? ''));
                    $toValue = trim((string) ($havingRow['value_to'] ?? ''));

                    if ($toValue === '') {
                        $fallbackTo = self::secondListValue($havingRow['value'] ?? '');
                        $toValue = $fallbackTo ?? '';
                    }

                    if ($fromValue === '') {
                        $errors[sprintf('having_rows.%d.value', $index)] = 'Startwaarde is verplicht voor BETWEEN/NOT BETWEEN.';
                    }

                    if ($toValue === '') {
                        $errors[sprintf('having_rows.%d.value_to', $index)] = 'Eindwaarde is verplicht voor BETWEEN/NOT BETWEEN.';
                    }
                }
            }

            if (in_array($normalizedCondition, ['IN', 'NOT IN'], true) && $valueType === 'Json Array') {
                if (self::listValues($havingRow['value'] ?? '') === []) {
                    $errors[sprintf('having_rows.%d.value', $index)] = 'Json Array voor IN/NOT IN mag niet leeg zijn.';
                }
            }

            if (
                ! $isNullCondition
                && ! $isRangeCondition
                &&
                $valueType !== 'Parameter'
                && ! in_array($normalizedCondition, ['IS', 'IS NOT'], true)
                && trim((string) ($havingRow['value'] ?? '')) === ''
            ) {
                $errors[sprintf('having_rows.%d.value', $index)] = 'Waarde is verplicht voor deze HAVING conditie.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, mixed>  $aggregateRows
     * @param  array<int, string>  $allowedFormulaIdentifiers
     * @return array<int, string>
     */
    private static function aggregateSelectParts(array $aggregateRows, array $allowedFormulaIdentifiers): array
    {
        $output = [];
        $knownAliases = [];

        foreach ($aggregateRows as $aggregateRow) {
            if (! is_array($aggregateRow)) {
                continue;
            }

            $function = strtoupper(trim((string) ($aggregateRow['func'] ?? '')));

            if (! in_array($function, self::AGGREGATE_FUNCTIONS, true)) {
                continue;
            }

            $alias = self::normalizeAlias((string) ($aggregateRow['alias'] ?? ''));

            if ($alias === '') {
                continue;
            }

            if ($function === 'FORMULA') {
                $formulaExpression = self::normalizeFormulaExpression(
                    (string) ($aggregateRow['formula'] ?? ''),
                    $allowedFormulaIdentifiers,
                    $knownAliases,
                );

                if ($formulaExpression === '') {
                    $formulaExpression = self::normalizeFormulaExpression(
                        (string) ($aggregateRow['field'] ?? ''),
                        $allowedFormulaIdentifiers,
                        $knownAliases,
                    );
                }

                if ($formulaExpression === '') {
                    continue;
                }

                $output[] = sprintf('%s AS %s', $formulaExpression, $alias);
                $knownAliases[] = $alias;

                continue;
            }

            if ($function === 'CONCAT') {
                $concatFields = self::normalizeConcatFields($aggregateRow);

                if ($concatFields === []) {
                    continue;
                }

                $separator = str_replace("'", "''", (string) ($aggregateRow['separator'] ?? ' '));

                $concatSql = $separator === ''
                    ? sprintf('CONCAT(%s)', implode(', ', $concatFields))
                    : sprintf("CONCAT_WS('%s', %s)", $separator, implode(', ', $concatFields));

                $output[] = sprintf('%s AS %s', $concatSql, $alias);
                $knownAliases[] = $alias;

                continue;
            }

            $field = self::normalizeAggregateField((string) ($aggregateRow['field'] ?? ''));

            if ($field === '') {
                continue;
            }

            if ($function === 'GROUP_CONCAT') {
                $distinctPrefix = (bool) ($aggregateRow['distinct'] ?? false) ? 'DISTINCT ' : '';
                $separator = str_replace("'", "''", (string) ($aggregateRow['separator'] ?? ','));

                $output[] = sprintf(
                    "%s(%s%s SEPARATOR '%s') AS %s",
                    $function,
                    $distinctPrefix,
                    $field,
                    $separator,
                    $alias,
                );
                $knownAliases[] = $alias;

                continue;
            }

            $distinctPrefix = (bool) ($aggregateRow['distinct'] ?? false) ? 'DISTINCT ' : '';
            $output[] = sprintf('%s(%s%s) AS %s', $function, $distinctPrefix, $field, $alias);
            $knownAliases[] = $alias;
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function joinLine(array $row, string $baseTable): string
    {
        $joinType = strtoupper(trim((string) ($row['joinType'] ?? 'LEFT')));

        if (! in_array($joinType, ['LEFT', 'RIGHT', 'INNER'], true)) {
            $joinType = 'LEFT';
        }

        $relationTable = self::normalizeIdentifier((string) ($row['relTable'] ?? ''));
        $originTable = self::normalizeIdentifier((string) ($row['originTable'] ?? $baseTable));
        $originField = self::normalizeSimpleIdentifier((string) ($row['relFieldT1'] ?? ''));
        $relationField = self::normalizeSimpleIdentifier((string) ($row['relFieldT2'] ?? ''));

        if ($relationTable === '' || $originTable === '' || $originField === '' || $relationField === '') {
            return '';
        }

        return sprintf(
            '%s JOIN %s ON %s.%s = %s.%s',
            $joinType,
            $relationTable,
            $originTable,
            $originField,
            $relationTable,
            $relationField,
        );
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, string>
     */
    private static function buildLogicalLines(array $rows, string $firstKeyword, bool $testMode): array
    {
        $normalizedRows = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $row['id'] = isset($row['id']) ? (int) $row['id'] : $index;
            $normalizedRows[] = $row;
        }

        $childrenByParentId = [];
        $topRows = [];

        foreach ($normalizedRows as $row) {
            $isSubRow = (bool) ($row['subRow'] ?? false);
            $parentId = isset($row['parentId']) ? (int) $row['parentId'] : null;

            if ($isSubRow && $parentId !== null) {
                if (! array_key_exists($parentId, $childrenByParentId)) {
                    $childrenByParentId[$parentId] = [];
                }

                $childrenByParentId[$parentId][] = $row;

                continue;
            }

            $topRows[] = $row;
        }

        $lines = [];

        foreach ($topRows as $topRowIndex => $topRow) {
            $topExpression = self::rowExpression($topRow, $testMode);
            $rowId = isset($topRow['id']) ? (int) $topRow['id'] : -1;
            $children = $childrenByParentId[$rowId] ?? [];
            $childExpressions = [];

            foreach ($children as $childRow) {
                $childExpression = self::rowExpression($childRow, $testMode);

                if ($childExpression === '') {
                    continue;
                }

                $childExpressions[] = [
                    'boolean' => self::normalizeAndOr((string) ($childRow['whereFieldAndOr'] ?? 'AND')),
                    'expression' => $childExpression,
                ];
            }

            if ($topExpression === '' && $childExpressions === []) {
                continue;
            }

            $groupExpression = $topExpression;

            foreach ($childExpressions as $childIndex => $childExpression) {
                $expression = (string) $childExpression['expression'];
                $boolean = (string) $childExpression['boolean'];

                if ($childIndex === 0 && $groupExpression === '') {
                    $groupExpression = $expression;

                    continue;
                }

                $groupExpression = sprintf('%s %s %s', $groupExpression, $boolean, $expression);
            }

            $prefix = $topRowIndex === 0
                ? $firstKeyword
                : self::normalizeAndOr((string) ($topRow['whereFieldAndOr'] ?? 'AND'));

            if ($childExpressions !== []) {
                $lines[] = sprintf('%s (%s)', $prefix, $groupExpression);

                continue;
            }

            $lines[] = sprintf('%s %s', $prefix, $groupExpression);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function rowExpression(array $row, bool $testMode): string
    {
        $field = self::normalizeIdentifier((string) ($row['whereField'] ?? ''));

        if ($field === '') {
            return '';
        }

        $condition = self::normalizeCondition((string) ($row['whereFieldCondition'] ?? '='));
        $valueSql = self::rowValueSql($row, $testMode, false);
        $valueType = trim((string) ($row['varOrValue'] ?? ''));

        if (in_array($condition, ['IN', 'NOT IN'], true)) {
            if ($valueType === 'Json Array') {
                $rawValues = self::listValues($row['value'] ?? '');

                if ($rawValues === []) {
                    return '';
                }

                $predicates = [];

                foreach ($rawValues as $rawValue) {
                    $escapedValue = str_replace("'", "''", $rawValue);

                    if (preg_match('/^-?\d+(\.\d+)?$/', $rawValue) === 1) {
                        $predicates[] = sprintf(
                            "JSON_CONTAINS(%s, '%s') OR JSON_CONTAINS(%s, JSON_QUOTE('%s'))",
                            $field,
                            $rawValue,
                            $field,
                            $escapedValue,
                        );

                        continue;
                    }

                    $predicates[] = sprintf(
                        "JSON_CONTAINS(%s, JSON_QUOTE('%s'))",
                        $field,
                        $escapedValue,
                    );
                }

                if ($condition === 'IN') {
                    $wrappedPredicates = array_map(static function (string $predicate): string {
                        if (str_contains($predicate, ' OR ')) {
                            return sprintf('(%s)', $predicate);
                        }

                        return $predicate;
                    }, $predicates);

                    return sprintf('(%s)', implode(' OR ', $wrappedPredicates));
                }

                return implode(' AND ', array_map(static fn (string $predicate): string => sprintf('NOT (%s)', $predicate), $predicates));
            }

            $normalizedValueSql = trim($valueSql);

            if (str_starts_with($normalizedValueSql, '(')) {
                return sprintf('%s %s %s', $field, $condition, $normalizedValueSql);
            }

            $rawValues = self::listValues($row['value'] ?? '');
            $listSql = [];

            foreach ($rawValues as $rawValue) {
                $listSql[] = self::quoteSqlValue($rawValue);
            }

            $joinedValues = $listSql !== [] ? implode(', ', $listSql) : $valueSql;

            return sprintf('%s %s (%s)', $field, $condition, $joinedValues);
        }

        if (in_array($condition, ['BETWEEN', 'NOT BETWEEN'], true)) {
            $valueToSql = self::rowValueSql($row, $testMode, true);

            if (
                trim($valueSql) === ''
                || trim($valueToSql) === ''
                || strtoupper(trim($valueSql)) === 'NULL'
                || strtoupper(trim($valueToSql)) === 'NULL'
            ) {
                return '';
            }

            return sprintf('%s %s %s AND %s', $field, $condition, $valueSql, $valueToSql);
        }

        if (in_array($condition, ['IS NULL', 'IS NOT NULL'], true)) {
            return sprintf('%s %s', $field, $condition);
        }

        if (in_array($condition, ['IS', 'IS NOT'], true)) {
            $upperValueSql = strtoupper(trim($valueSql));

            if (in_array($upperValueSql, ['NULL', 'TRUE', 'FALSE', 'UNKNOWN'], true)) {
                return sprintf('%s %s %s', $field, $condition, $upperValueSql);
            }

            $fallbackCondition = $condition === 'IS' ? '=' : '!=';

            return sprintf('%s %s %s', $field, $fallbackCondition, $valueSql);
        }

        if (in_array($condition, ['LIKE', 'NOT LIKE'], true)) {
            $baseValue = trim((string) ($row['value'] ?? ''));
            $likeValue = self::quoteSqlValue(sprintf('%%%s%%', $baseValue));

            if (! $testMode && $valueType === 'Parameter') {
                $likeValue = sprintf("CONCAT('%%', %s, '%%')", $valueSql);
            }

            return sprintf('%s %s %s', $field, $condition, $likeValue);
        }

        return sprintf('%s %s %s', $field, $condition, $valueSql);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function rowValueSql(array $row, bool $testMode, bool $toValue): string
    {
        $valueType = trim((string) ($row['varOrValue'] ?? 'Vaste waarde'));

        $valueKey = $toValue ? 'value_to' : 'value';
        $parameterKey = $toValue ? 'variabele_to' : 'variabele';
        $testValueKey = $toValue ? 'testValueTo' : 'testValue';
        $rawValue = $row[$valueKey] ?? null;
        $rawParameter = $row[$parameterKey] ?? null;
        $rawTestValue = $row[$testValueKey] ?? null;

        if ($toValue && trim((string) $rawValue) === '') {
            $rawValue = self::secondListValue($row['value'] ?? '') ?? $rawValue;
        }

        if ($toValue && trim((string) $rawParameter) === '') {
            $rawParameter = self::secondListValue($row['variabele'] ?? '') ?? $rawParameter;
        }

        if ($toValue && trim((string) $rawTestValue) === '') {
            $rawTestValue = self::secondListValue($row['testValue'] ?? '') ?? $rawTestValue;
        }

        if ($valueType === 'Parameter') {
            $parameter = self::normalizeParameter((string) $rawParameter);
            $fallbackTestValue = trim((string) $rawTestValue);

            if ($parameter === '') {
                if ($testMode) {
                    return self::quoteSqlValue($fallbackTestValue);
                }

                return 'NULL';
            }

            if ($testMode) {
                return self::quoteSqlValue($fallbackTestValue !== '' ? $fallbackTestValue : null);
            }

            return sprintf(':%s', $parameter);
        }

        if ($valueType === 'Systeemvariabele') {
            $systemVariable = self::normalizeSystemVariable((string) $rawValue);

            if ($systemVariable === '') {
                return 'NULL';
            }

            return self::quoteSqlValue($systemVariable);
        }

        if ($valueType === 'Json Array') {
            return self::quoteSqlValue(trim((string) $rawValue));
        }

        return self::quoteSqlValue($rawValue);
    }

    private static function normalizeCondition(string $condition): string
    {
        $normalizedCondition = strtoupper(trim($condition));

        if (in_array($normalizedCondition, self::ALLOWED_CONDITIONS, true)) {
            return $normalizedCondition;
        }

        return '=';
    }

    private static function normalizeAndOr(string $value): string
    {
        return strtoupper(trim($value)) === 'OR' ? 'OR' : 'AND';
    }

    private static function normalizeSystemVariable(string $value): string
    {
        $normalized = strtoupper(trim($value));

        if (in_array($normalized, ['CURRENTSCHOOLYEAR', 'USERSCHOOLIDS', 'USERWISASCHOOLIDS', 'USERWISAVIRTSCHOOLIDS'], true)) {
            return $normalized;
        }

        return '';
    }

    private static function normalizeParameter(string $value): string
    {
        $normalized = trim($value);

        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $normalized) !== 1) {
            return '';
        }

        return $normalized;
    }

    private static function normalizeAggregateField(string $field): string
    {
        $trimmedField = trim($field);

        if ($trimmedField === '*') {
            return '*';
        }

        return self::normalizeIdentifier($trimmedField);
    }

    private static function normalizeAlias(string $value): string
    {
        $normalizedAlias = trim($value);

        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $normalizedAlias) !== 1) {
            return '';
        }

        return $normalizedAlias;
    }

    private static function normalizeSimpleIdentifier(string $value): string
    {
        $normalizedIdentifier = trim($value);

        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $normalizedIdentifier) !== 1) {
            return '';
        }

        return $normalizedIdentifier;
    }

    private static function normalizeIdentifier(string $value): string
    {
        $normalizedIdentifier = trim($value);

        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $normalizedIdentifier) !== 1) {
            return '';
        }

        return $normalizedIdentifier;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private static function normalizeIdentifierList(array $values): array
    {
        $output = [];

        foreach ($values as $value) {
            $normalizedIdentifier = self::normalizeIdentifier((string) $value);

            if ($normalizedIdentifier === '' || in_array($normalizedIdentifier, $output, true)) {
                continue;
            }

            $output[] = $normalizedIdentifier;
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $aggregateRow
     * @return array<int, string>
     */
    private static function normalizeConcatFields(array $aggregateRow): array
    {
        $fields = [];

        $fieldList = $aggregateRow['fields'] ?? null;

        if (is_array($fieldList)) {
            foreach ($fieldList as $fieldValue) {
                $normalizedField = self::normalizeIdentifier((string) $fieldValue);

                if ($normalizedField === '' || in_array($normalizedField, $fields, true)) {
                    continue;
                }

                $fields[] = $normalizedField;
            }
        }

        if ($fields !== []) {
            return $fields;
        }

        $fallbackField = trim((string) ($aggregateRow['field'] ?? ''));

        if ($fallbackField === '') {
            return [];
        }

        $parts = array_map(
            static fn (string $part): string => trim($part),
            explode(',', $fallbackField),
        );

        foreach ($parts as $part) {
            $normalizedField = self::normalizeIdentifier($part);

            if ($normalizedField === '' || in_array($normalizedField, $fields, true)) {
                continue;
            }

            $fields[] = $normalizedField;
        }

        return $fields;
    }

    /**
     * @param  array<int, string>  $allowedFormulaIdentifiers
     * @param  array<int, string>  $knownAliases
     */
    private static function normalizeFormulaExpression(
        string $value,
        array $allowedFormulaIdentifiers,
        array $knownAliases,
    ): string {
        $formula = trim($value);

        if ($formula === '') {
            return '';
        }

        if (str_contains($formula, ';')) {
            return '';
        }

        if (preg_match('/(--|#|\/\*)/', $formula) === 1) {
            return '';
        }

        if (preg_match('/\b(insert|update|delete|drop|truncate|alter|create|replace|grant|revoke|merge|call|execute|exec|attach|detach|vacuum|pragma)\b/i', $formula) === 1) {
            return '';
        }

        if (preg_match('/^[a-zA-Z0-9_().,+\-*\/%\s<>=!\'"\?]+$/', $formula) !== 1) {
            return '';
        }

        if (! self::formulaReferencesAreAllowed($formula, $allowedFormulaIdentifiers, $knownAliases)) {
            return '';
        }

        return $formula;
    }

    /**
     * @param  array<int, string>  $allowedFormulaIdentifiers
     * @param  array<int, string>  $knownAliases
     */
    private static function formulaReferencesAreAllowed(
        string $formula,
        array $allowedFormulaIdentifiers,
        array $knownAliases,
    ): bool {
        $allowedIdentifierLookup = array_fill_keys($allowedFormulaIdentifiers, true);
        $knownAliasLookup = array_fill_keys($knownAliases, true);
        $knownAliasLowerLookup = array_fill_keys(
            array_map(static fn (string $alias): string => strtolower($alias), $knownAliases),
            true,
        );
        $constantLookup = array_fill_keys([
            'NULL',
            'TRUE',
            'FALSE',
            'AND',
            'OR',
            'NOT',
            'IN',
            'IS',
            'LIKE',
        ], true);

        preg_match_all('/\b[a-zA-Z_][a-zA-Z0-9_.]*\b/', $formula, $matches, PREG_OFFSET_CAPTURE);

        foreach ((array) ($matches[0] ?? []) as $match) {
            if (! is_array($match) || ! isset($match[0], $match[1])) {
                continue;
            }

            $token = (string) $match[0];
            $offset = (int) $match[1];
            $upperToken = strtoupper($token);

            if (isset($constantLookup[$upperToken])) {
                continue;
            }

            $afterToken = substr($formula, $offset + strlen($token));
            $isFunctionCall = str_starts_with(ltrim((string) $afterToken), '(');

            if ($isFunctionCall) {
                if (! in_array($upperToken, self::ALLOWED_FORMULA_FUNCTIONS, true)) {
                    return false;
                }

                continue;
            }

            if (str_contains($token, '.')) {
                if (self::normalizeIdentifier($token) === '') {
                    return false;
                }

                if (isset($allowedIdentifierLookup[$token])) {
                    continue;
                }

                continue;
            }

            if (isset($knownAliasLookup[$token]) || isset($knownAliasLowerLookup[strtolower($token)])) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $aggregateRows
     * @return array<int, string>
     */
    private static function collectAggregateFieldIdentifiers(array $aggregateRows): array
    {
        $fields = [];

        foreach ($aggregateRows as $aggregateRow) {
            if (! is_array($aggregateRow)) {
                continue;
            }

            $function = strtoupper(trim((string) ($aggregateRow['func'] ?? '')));

            if ($function === 'FORMULA') {
                continue;
            }

            if ($function === 'CONCAT') {
                foreach (self::normalizeConcatFields($aggregateRow) as $concatField) {
                    if (! in_array($concatField, $fields, true)) {
                        $fields[] = $concatField;
                    }
                }

                continue;
            }

            $field = self::normalizeAggregateField((string) ($aggregateRow['field'] ?? ''));

            if ($field === '' || $field === '*') {
                continue;
            }

            if (! in_array($field, $fields, true)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private static function hasAnyValue(array $row, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            if (is_array($value) && $value !== []) {
                return true;
            }

            if ($value === null) {
                continue;
            }

            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private static function listValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn (mixed $item): string => trim((string) $item),
                $value,
            ), static fn (string $item): bool => $item !== ''));
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', $stringValue),
        ), static fn (string $item): bool => $item !== ''));
    }

    private static function secondListValue(mixed $value): ?string
    {
        $values = self::listValues($value);

        if (! isset($values[1])) {
            return null;
        }

        $output = trim((string) $values[1]);

        return $output === '' ? null : $output;
    }

    private static function quoteSqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        $textValue = trim((string) $value);

        $dbFunction = self::dbFunctionExpression($textValue);

        if ($dbFunction !== null) {
            return $dbFunction;
        }

        if ($textValue === '') {
            return "''";
        }

        $upperValue = strtoupper($textValue);

        if (in_array($upperValue, ['NULL', 'TRUE', 'FALSE'], true)) {
            return $upperValue;
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $textValue) === 1) {
            return $textValue;
        }

        return sprintf("'%s'", str_replace("'", "''", $textValue));
    }

    private static function dbFunctionExpression(string $value): ?string
    {
        $normalized = strtoupper(trim($value));

        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['CURDATE()', 'CURRENT_DATE', 'CURRENT_DATE()', 'CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()', 'NOW()', 'CURTIME()', 'UTC_DATE()', 'UTC_TIMESTAMP()'], true)) {
            return $normalized;
        }

        return null;
    }
}
