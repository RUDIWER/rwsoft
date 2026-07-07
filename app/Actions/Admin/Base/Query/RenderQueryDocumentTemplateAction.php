<?php

namespace App\Actions\Admin\Base\Query;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class RenderQueryDocumentTemplateAction
{
    /** @var array<string, string> */
    private static array $docxRelationshipXmlCache = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public static function handle(string $templatePath, string $outputPath, array $context): void
    {
        self::$docxRelationshipXmlCache = [];

        if (! is_file($templatePath)) {
            throw new RuntimeException(__('query_builder_ui.runtime.document_template_missing'));
        }

        $extension = strtolower((string) pathinfo($templatePath, PATHINFO_EXTENSION));

        if (! in_array($extension, ['docx', 'odt'], true)) {
            throw new RuntimeException(__('query_builder_ui.runtime.document_template_unknown_format'));
        }

        File::ensureDirectoryExists(dirname($outputPath));

        if (! copy($templatePath, $outputPath)) {
            throw new RuntimeException(__('query_builder_ui.runtime.document_template_copy_failed'));
        }

        $zip = new ZipArchive;

        if ($zip->open($outputPath) !== true) {
            throw new RuntimeException(__('query_builder_ui.runtime.document_template_open_failed'));
        }

        if ($extension === 'docx') {
            $imageCounter = 1;
            $drawingIdCounter = 1;
            self::renderDocx($zip, $context, $imageCounter, $drawingIdCounter);
        } else {
            $imageCounter = 1;
            self::renderOdt($zip, $context, $imageCounter);
        }

        $zip->close();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function renderDocx(
        ZipArchive $zip,
        array $context,
        int &$imageCounter,
        int &$drawingIdCounter,
    ): void {
        $targets = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if ($name === 'word/document.xml' || preg_match('#^word/(header|footer)[0-9]*\.xml$#', $name) === 1) {
                $targets[] = $name;
            }
        }

        foreach ($targets as $target) {
            $xml = $zip->getFromName($target);

            if (! is_string($xml)) {
                continue;
            }

            $zip->addFromString(
                $target,
                self::renderDocxXml(
                    $xml,
                    $context,
                    $zip,
                    $target,
                    $imageCounter,
                    $drawingIdCounter,
                ),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function renderOdt(ZipArchive $zip, array $context, int &$imageCounter): void
    {
        $xml = $zip->getFromName('content.xml');

        if (! is_string($xml)) {
            return;
        }

        $zip->addFromString('content.xml', self::renderOdtXml($xml, $context, $zip, $imageCounter));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function renderDocxXml(
        string $xml,
        array $context,
        ZipArchive $zip,
        string $partPath,
        int &$imageCounter,
        int &$drawingIdCounter,
    ): string {
        $rendered = self::renderRowsBlocks($xml, $context, static function (string $block, array $rowContext) use (
            $zip,
            $partPath,
            &$imageCounter,
            &$drawingIdCounter
        ): string {
            $withText = self::renderTextPlaceholders($block, $rowContext);

            return self::replaceDocxImageRuns(
                $withText,
                $rowContext,
                $zip,
                $partPath,
                $imageCounter,
                $drawingIdCounter,
            );
        });

        $withText = self::renderTextPlaceholders($rendered, $context);

        return self::replaceDocxImageRuns(
            $withText,
            $context,
            $zip,
            $partPath,
            $imageCounter,
            $drawingIdCounter,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function renderOdtXml(
        string $xml,
        array $context,
        ZipArchive $zip,
        int &$imageCounter,
    ): string {
        $rendered = self::renderRowsBlocks($xml, $context, static function (string $block, array $rowContext) use (
            $zip,
            &$imageCounter
        ): string {
            $withText = self::renderTextPlaceholders($block, $rowContext);

            return self::replaceOdtImagePlaceholders($withText, $rowContext, $zip, $imageCounter);
        });

        $withText = self::renderTextPlaceholders($rendered, $context);

        return self::replaceOdtImagePlaceholders($withText, $context, $zip, $imageCounter);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  callable(string, array<string, mixed>): string  $blockRenderer
     */
    private static function renderRowsBlocks(string $xml, array $context, callable $blockRenderer): string
    {
        $rendered = $xml;
        $guard = 0;

        while ($guard < 50 && preg_match('/\{\{\s*rows:[a-zA-Z0-9_.-]+.*?\}\}/s', $rendered) === 1) {
            $guard++;
            $next = preg_replace_callback(
                '/\{\{\s*rows:([a-zA-Z0-9_.-]+)(.*?)\}\}(.*?)\{\{\s*\/rows\s*\}\}/s',
                static function (array $matches) use ($context, $blockRenderer): string {
                    $datasetPath = (string) ($matches[1] ?? '');
                    $options = self::parseDirectiveOptions((string) ($matches[2] ?? ''));
                    $block = (string) ($matches[3] ?? '');
                    $records = self::rowsForDataset($context, $datasetPath, $options);
                    $output = '';

                    foreach ($records as $record) {
                        $rowContext = array_merge($context, [
                            'row' => $record['row'],
                            'item' => $record['row'],
                            'index' => $record['index'],
                            'group' => [
                                'key' => $record['group_key'] ?? null,
                                'index' => $record['group_index'] ?? null,
                            ],
                        ]);

                        $output .= $blockRenderer($block, $rowContext);
                    }

                    return $output;
                },
                $rendered,
            );

            if (! is_string($next) || $next === $rendered) {
                break;
            }

            $rendered = $next;
        }

        return $rendered;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function replaceDocxImageRuns(
        string $xml,
        array $context,
        ZipArchive $zip,
        string $partPath,
        int &$imageCounter,
        int &$drawingIdCounter,
    ): string {
        $document = new DOMDocument('1.0', 'UTF-8');

        if (! @$document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            return $xml;
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $gridNodes = [];

        foreach ($xpath->query('//w:t[contains(., "{{ imageGrid:")]') ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $gridNodes[] = $node;
            }
        }

        foreach ($gridNodes as $gridNode) {
            self::replaceDocxImageGridPlaceholderInTextNode(
                $document,
                $gridNode,
                $context,
                $zip,
                $partPath,
                $imageCounter,
                $drawingIdCounter,
            );
        }

        $textNodes = [];

        foreach ($xpath->query('//w:t[contains(., "{{ image:")]') ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $textNodes[] = $node;
            }
        }

        foreach ($textNodes as $textNode) {
            self::replaceDocxImagePlaceholderInTextNode(
                $document,
                $textNode,
                $context,
                $zip,
                $partPath,
                $imageCounter,
                $drawingIdCounter,
            );
        }

        return $document->saveXML($document->documentElement) ?: $xml;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function replaceDocxImagePlaceholderInTextNode(
        DOMDocument $document,
        DOMElement $textNode,
        array $context,
        ZipArchive $zip,
        string $partPath,
        int &$imageCounter,
        int &$drawingIdCounter,
    ): void {
        $textValue = $textNode->textContent;

        if (! is_string($textValue) || ! str_contains($textValue, '{{ image:')) {
            return;
        }

        $runNode = self::findDocxAncestorRun($textNode);

        if (! $runNode instanceof DOMElement || ! $runNode->parentNode) {
            return;
        }

        if (preg_match_all('/\{\{\s*image:([a-zA-Z0-9_.-]+)(.*?)\}\}/', $textValue, $matches, PREG_OFFSET_CAPTURE) !== false) {
            if (count($matches[0]) === 0) {
                return;
            }

            $parent = $runNode->parentNode;
            $cursor = 0;

            foreach ($matches[0] as $index => $rawMatch) {
                $placeholder = (string) ($rawMatch[0] ?? '');
                $offset = (int) ($rawMatch[1] ?? 0);
                $before = substr($textValue, $cursor, $offset - $cursor);

                if ($before !== '') {
                    $parent->insertBefore(self::createDocxTextRunNode($document, $runNode, $before), $runNode);
                }

                $pathExpression = (string) (($matches[1][$index][0] ?? ''));
                $options = self::parseDirectiveOptions((string) ($matches[2][$index][0] ?? ''));
                $image = self::resolveImage($context, $pathExpression, $options);

                if ($image === null) {
                    $parent->insertBefore(self::createDocxTextRunNode($document, $runNode, $placeholder), $runNode);
                } else {
                    $relationshipId = self::addDocxImageRelationship($zip, $partPath, $image['path'], $imageCounter);
                    $drawingXml = self::docxDrawingRunXml(
                        $relationshipId,
                        $image['basename'],
                        $image['width_px'],
                        $image['height_px'],
                        $drawingIdCounter++,
                    );

                    $fragment = $document->createDocumentFragment();

                    if (@$fragment->appendXML($drawingXml)) {
                        $parent->insertBefore($fragment, $runNode);
                    } else {
                        $parent->insertBefore(self::createDocxTextRunNode($document, $runNode, $placeholder), $runNode);
                    }
                }

                $cursor = $offset + strlen($placeholder);
            }

            $after = substr($textValue, $cursor);

            if ($after !== '') {
                $parent->insertBefore(self::createDocxTextRunNode($document, $runNode, $after), $runNode);
            }

            $parent->removeChild($runNode);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function replaceDocxImageGridPlaceholderInTextNode(
        DOMDocument $document,
        DOMElement $textNode,
        array $context,
        ZipArchive $zip,
        string $partPath,
        int &$imageCounter,
        int &$drawingIdCounter,
    ): void {
        $textValue = $textNode->textContent;

        if (! is_string($textValue)) {
            return;
        }

        if (preg_match('/\{\{\s*imageGrid:([a-zA-Z0-9_.-]+)(.*?)\}\}/', $textValue, $matches) !== 1) {
            return;
        }

        $datasetPath = (string) ($matches[1] ?? '');
        $options = self::parseDirectiveOptions((string) ($matches[2] ?? ''));
        $placeholder = (string) ($matches[0] ?? '');
        $recordsRaw = self::resolvePath($context, $datasetPath);

        if (! is_iterable($recordsRaw)) {
            $textNode->nodeValue = str_replace($placeholder, '', $textValue);

            return;
        }

        $records = [];

        foreach ($recordsRaw as $item) {
            $records[] = self::normalizeRecord($item);
        }

        $tableXml = self::docxImageGridTableXml(
            $records,
            $options,
            $context,
            $zip,
            $partPath,
            $imageCounter,
            $drawingIdCounter,
        );

        $textNode->nodeValue = str_replace($placeholder, '', $textValue);

        $paragraphNode = self::findDocxAncestorParagraph($textNode);

        if (! $paragraphNode instanceof DOMElement || ! $paragraphNode->parentNode) {
            return;
        }

        $fragment = $document->createDocumentFragment();

        if (@$fragment->appendXML($tableXml)) {
            self::insertNodeAfter($paragraphNode, $fragment);
        }

        if (self::isDocxParagraphEmpty($paragraphNode)) {
            $paragraphNode->parentNode->removeChild($paragraphNode);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<string, string>  $options
     * @param  array<string, mixed>  $context
     */
    private static function docxImageGridTableXml(
        array $records,
        array $options,
        array $context,
        ZipArchive $zip,
        string $partPath,
        int &$imageCounter,
        int &$drawingIdCounter,
    ): string {
        $layout = strtolower(trim((string) ($options['layout'] ?? '')));
        $layoutDefaults = self::docxImageGridLayoutDefaults($layout);

        $columns = (int) ($options['columns'] ?? ($layoutDefaults['columns'] ?? 3));

        if ($columns < 1) {
            $columns = 3;
        }

        if ($columns > 6) {
            $columns = 6;
        }

        $imagePathExpression = trim((string) ($options['image'] ?? 'path'));

        if ($imagePathExpression === '') {
            $imagePathExpression = 'path';
        }

        $imagePathExpression = self::rowScopedExpression($imagePathExpression);
        $effectiveOptions = $options;

        if (! array_key_exists('width', $effectiveOptions) && array_key_exists('width', $layoutDefaults)) {
            $effectiveOptions['width'] = (string) $layoutDefaults['width'];
        }

        if (! array_key_exists('height', $effectiveOptions) && array_key_exists('height', $layoutDefaults)) {
            $effectiveOptions['height'] = (string) $layoutDefaults['height'];
        }

        if (! array_key_exists('caption', $effectiveOptions) && array_key_exists('caption', $layoutDefaults)) {
            $effectiveOptions['caption'] = (string) $layoutDefaults['caption'];
        }

        $cellWidth = max(800, (int) floor(9000 / $columns));

        $table = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/></w:tblPr><w:tblGrid>';

        for ($column = 0; $column < $columns; $column++) {
            $table .= sprintf('<w:gridCol w:w="%d"/>', $cellWidth);
        }

        $table .= '</w:tblGrid>';

        $cells = [];

        foreach ($records as $index => $record) {
            $rowContext = array_merge($context, [
                'row' => $record,
                'item' => $record,
                'index' => $index + 1,
            ]);

            $image = self::resolveImage($rowContext, $imagePathExpression, $effectiveOptions);
            $caption = self::resolveGridCaption($rowContext, $effectiveOptions);

            $cells[] = self::docxImageGridCellXml(
                $cellWidth,
                $image,
                $caption,
                $zip,
                $partPath,
                $imageCounter,
                $drawingIdCounter,
            );
        }

        if ($cells === []) {
            $cells[] = self::docxEmptyGridCellXml($cellWidth);
        }

        $rowCells = [];

        foreach ($cells as $cellXml) {
            $rowCells[] = $cellXml;

            if (count($rowCells) === $columns) {
                $table .= '<w:tr>'.implode('', $rowCells).'</w:tr>';
                $rowCells = [];
            }
        }

        if ($rowCells !== []) {
            while (count($rowCells) < $columns) {
                $rowCells[] = self::docxEmptyGridCellXml($cellWidth);
            }

            $table .= '<w:tr>'.implode('', $rowCells).'</w:tr>';
        }

        $table .= '</w:tbl>';

        return $table;
    }

    /**
     * @return array<string, int|string>
     */
    private static function docxImageGridLayoutDefaults(string $layout): array
    {
        return match ($layout) {
            'a4-portrait', 'portrait' => [
                'columns' => 2,
                'width' => 240,
                'height' => 160,
                'caption' => 'title',
            ],
            'a4-landscape', 'landscape' => [
                'columns' => 4,
                'width' => 130,
                'height' => 90,
                'caption' => 'title',
            ],
            default => [],
        };
    }

    /**
     * @param  array{path:string,width_px:int,height_px:int,basename:string}|null  $image
     */
    private static function docxImageGridCellXml(
        int $cellWidth,
        ?array $image,
        ?string $caption,
        ZipArchive $zip,
        string $partPath,
        int &$imageCounter,
        int &$drawingIdCounter,
    ): string {
        $inner = '<w:p/>';

        if ($image !== null) {
            $relationshipId = self::addDocxImageRelationship($zip, $partPath, $image['path'], $imageCounter);
            $drawingRun = self::docxDrawingRunXml(
                $relationshipId,
                $image['basename'],
                $image['width_px'],
                $image['height_px'],
                $drawingIdCounter++,
            );

            $inner = '<w:p>'.$drawingRun.'</w:p>';
        }

        if (is_string($caption) && $caption !== '') {
            $inner .= sprintf(
                '<w:p><w:r><w:t xml:space="preserve">%s</w:t></w:r></w:p>',
                htmlspecialchars($caption, ENT_QUOTES),
            );
        }

        return sprintf(
            '<w:tc><w:tcPr><w:tcW w:w="%d" w:type="dxa"/></w:tcPr>%s</w:tc>',
            $cellWidth,
            $inner,
        );
    }

    private static function docxEmptyGridCellXml(int $cellWidth): string
    {
        return sprintf(
            '<w:tc><w:tcPr><w:tcW w:w="%d" w:type="dxa"/></w:tcPr><w:p/></w:tc>',
            $cellWidth,
        );
    }

    /**
     * @param  array<string, mixed>  $rowContext
     * @param  array<string, string>  $options
     */
    private static function resolveGridCaption(array $rowContext, array $options): ?string
    {
        $captionExpression = trim((string) ($options['caption'] ?? ''));

        if ($captionExpression === '') {
            return null;
        }

        $captionExpression = self::rowScopedExpression($captionExpression);

        $value = self::resolvePath($rowContext, $captionExpression);

        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    private static function rowScopedExpression(string $expression): string
    {
        $normalized = trim($expression);

        if ($normalized === '' || str_contains($normalized, '.')) {
            return $normalized;
        }

        return 'row.'.$normalized;
    }

    private static function findDocxAncestorRun(DOMElement $node): ?DOMElement
    {
        $current = $node;

        while ($current->parentNode instanceof DOMElement) {
            $current = $current->parentNode;

            if ($current->localName === 'r' && $current->namespaceURI === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main') {
                return $current;
            }
        }

        return null;
    }

    private static function findDocxAncestorParagraph(DOMElement $node): ?DOMElement
    {
        $current = $node;

        while ($current->parentNode instanceof DOMElement) {
            $current = $current->parentNode;

            if ($current->localName === 'p' && $current->namespaceURI === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main') {
                return $current;
            }
        }

        return null;
    }

    private static function insertNodeAfter(\DOMNode $referenceNode, \DOMNode $newNode): void
    {
        $parent = $referenceNode->parentNode;

        if (! $parent instanceof \DOMNode) {
            return;
        }

        if ($referenceNode->nextSibling) {
            $parent->insertBefore($newNode, $referenceNode->nextSibling);

            return;
        }

        $parent->appendChild($newNode);
    }

    private static function isDocxParagraphEmpty(DOMElement $paragraphNode): bool
    {
        $ownerDocument = $paragraphNode->ownerDocument;

        if (! $ownerDocument instanceof DOMDocument) {
            return false;
        }

        $xpath = new DOMXPath($ownerDocument);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $textNodes = $xpath->query('.//w:t[normalize-space(.)!=""]', $paragraphNode);
        $drawingNodes = $xpath->query('.//w:drawing', $paragraphNode);

        return (int) ($textNodes?->length ?? 0) === 0
            && (int) ($drawingNodes?->length ?? 0) === 0;
    }

    private static function createDocxTextRunNode(DOMDocument $document, DOMElement $baseRun, string $text): DOMElement
    {
        $run = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');

        foreach ($baseRun->childNodes as $childNode) {
            if ($childNode instanceof DOMElement && $childNode->localName === 'rPr') {
                $run->appendChild($childNode->cloneNode(true));
                break;
            }
        }

        $textNode = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');

        if ($text !== trim($text)) {
            $textNode->setAttribute('xml:space', 'preserve');
        }

        $textNode->nodeValue = $text;
        $run->appendChild($textNode);

        return $run;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function replaceOdtImagePlaceholders(
        string $xml,
        array $context,
        ZipArchive $zip,
        int &$imageCounter,
    ): string {
        return preg_replace_callback(
            '/\{\{\s*image:([a-zA-Z0-9_.-]+)(.*?)\}\}/',
            static function (array $matches) use ($context, $zip, &$imageCounter): string {
                $pathExpression = (string) ($matches[1] ?? '');
                $options = self::parseDirectiveOptions((string) ($matches[2] ?? ''));
                $image = self::resolveImage($context, $pathExpression, $options);

                if ($image === null) {
                    return '';
                }

                $targetPath = self::addOdtImageAsset($zip, $image['path'], $imageCounter);
                $widthCm = number_format($image['width_px'] * 0.026458333, 2, '.', '');
                $heightCm = number_format($image['height_px'] * 0.026458333, 2, '.', '');
                $name = 'RwImage'.str_pad((string) $imageCounter, 4, '0', STR_PAD_LEFT);

                return sprintf(
                    '<draw:frame draw:name="%s" text:anchor-type="as-char" svg:width="%scm" svg:height="%scm"><draw:image xlink:href="%s" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad"/></draw:frame>',
                    htmlspecialchars($name, ENT_QUOTES),
                    $widthCm,
                    $heightCm,
                    htmlspecialchars($targetPath, ENT_QUOTES),
                );
            },
            $xml,
        ) ?? $xml;
    }

    private static function addDocxImageRelationship(
        ZipArchive $zip,
        string $partPath,
        string $absoluteImagePath,
        int &$imageCounter,
    ): string {
        $extension = strtolower((string) pathinfo($absoluteImagePath, PATHINFO_EXTENSION));
        $imageFilename = sprintf('rwimg-%04d.%s', $imageCounter, $extension);
        $imageCounter++;

        $mediaPath = 'word/media/'.$imageFilename;
        $contents = file_get_contents($absoluteImagePath);

        if ($contents === false) {
            throw new RuntimeException(__('query_builder_ui.runtime.docx_image_read_failed'));
        }

        $zip->addFromString($mediaPath, $contents);
        self::ensureDocxContentType($zip, $extension);

        $relsPath = 'word/_rels/'.basename($partPath).'.rels';
        $relsXml = self::$docxRelationshipXmlCache[$relsPath] ?? $zip->getFromName($relsPath);

        if (! is_string($relsXml) || trim($relsXml) === '') {
            $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
        }

        preg_match_all('/Id="rId(\d+)"/', $relsXml, $matches);
        $ids = array_map(static fn (string $id): int => (int) $id, (array) ($matches[1] ?? []));
        $nextId = $ids === [] ? 1 : (max($ids) + 1);
        $relationshipId = 'rId'.$nextId;

        $relationshipXml = sprintf(
            '<Relationship Id="%s" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/%s"/>',
            $relationshipId,
            $imageFilename,
        );

        $updatedRels = str_replace('</Relationships>', $relationshipXml.'</Relationships>', $relsXml);
        self::$docxRelationshipXmlCache[$relsPath] = $updatedRels;
        $zip->addFromString($relsPath, $updatedRels);

        return $relationshipId;
    }

    private static function ensureDocxContentType(ZipArchive $zip, string $extension): void
    {
        $contentTypes = $zip->getFromName('[Content_Types].xml');

        if (! is_string($contentTypes)) {
            return;
        }

        if (str_contains($contentTypes, sprintf('Extension="%s"', $extension))) {
            return;
        }

        $mime = self::mimeTypeForImageExtension($extension);

        if ($mime === null) {
            return;
        }

        $default = sprintf('<Default Extension="%s" ContentType="%s"/>', $extension, $mime);
        $updated = str_replace('</Types>', $default.'</Types>', $contentTypes);
        $zip->addFromString('[Content_Types].xml', $updated);
    }

    private static function addOdtImageAsset(ZipArchive $zip, string $absoluteImagePath, int &$imageCounter): string
    {
        $extension = strtolower((string) pathinfo($absoluteImagePath, PATHINFO_EXTENSION));
        $imageFilename = sprintf('rwimg-%04d.%s', $imageCounter, $extension);
        $imageCounter++;
        $targetPath = 'Pictures/'.$imageFilename;

        $contents = file_get_contents($absoluteImagePath);

        if ($contents === false) {
            throw new RuntimeException(__('query_builder_ui.runtime.odt_image_read_failed'));
        }

        $zip->addFromString($targetPath, $contents);
        self::ensureOdtManifestEntry($zip, $targetPath, self::mimeTypeForImageExtension($extension));

        return $targetPath;
    }

    private static function ensureOdtManifestEntry(ZipArchive $zip, string $path, ?string $mimeType): void
    {
        if ($mimeType === null) {
            return;
        }

        $manifestPath = 'META-INF/manifest.xml';
        $manifest = $zip->getFromName($manifestPath);

        if (! is_string($manifest) || trim($manifest) === '') {
            $manifest = '<?xml version="1.0" encoding="UTF-8"?><manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0"></manifest:manifest>';
        }

        if (str_contains($manifest, sprintf('manifest:full-path="%s"', $path))) {
            return;
        }

        $entry = sprintf(
            '<manifest:file-entry manifest:full-path="%s" manifest:media-type="%s"/>',
            htmlspecialchars($path, ENT_QUOTES),
            htmlspecialchars($mimeType, ENT_QUOTES),
        );

        $updated = str_replace('</manifest:manifest>', $entry.'</manifest:manifest>', $manifest);
        $zip->addFromString($manifestPath, $updated);
    }

    private static function docxDrawingRunXml(
        string $relationshipId,
        string $name,
        int $widthPx,
        int $heightPx,
        int $docPrId,
    ): string {
        $widthEmu = max(1, $widthPx) * 9525;
        $heightEmu = max(1, $heightPx) * 9525;
        $escapedName = htmlspecialchars($name, ENT_QUOTES);

        return sprintf(
            '<w:r><w:drawing><wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"><wp:extent cx="%d" cy="%d"/><wp:docPr id="%d" name="%s"/><a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="%d" name="%s"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="%s"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="%d" cy="%d"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>',
            $widthEmu,
            $heightEmu,
            $docPrId,
            $escapedName,
            $docPrId,
            $escapedName,
            $relationshipId,
            $widthEmu,
            $heightEmu,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $options
     * @return array{path:string,width_px:int,height_px:int,basename:string}|null
     */
    private static function resolveImage(array $context, string $pathExpression, array $options): ?array
    {
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

        $size = @getimagesize($imagePath);
        $width = isset($size[0]) ? (int) $size[0] : 120;
        $height = isset($size[1]) ? (int) $size[1] : 80;

        if (isset($options['width']) && is_numeric($options['width'])) {
            $width = max(1, (int) $options['width']);
        }

        if (isset($options['height']) && is_numeric($options['height'])) {
            $height = max(1, (int) $options['height']);
        }

        return [
            'path' => $imagePath,
            'width_px' => $width,
            'height_px' => $height,
            'basename' => (string) pathinfo($imagePath, PATHINFO_BASENAME),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function renderTextPlaceholders(string $value, array $context): string
    {
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
     * @param  array<string, string>  $options
     * @return array<int, array{row:array<string, mixed>,index:int,group_key?:string,group_index?:int}>
     */
    private static function rowsForDataset(array $context, string $datasetPath, array $options): array
    {
        $raw = self::resolvePath($context, $datasetPath);

        if (! is_iterable($raw)) {
            return [];
        }

        $rows = [];

        foreach ($raw as $item) {
            $rows[] = self::normalizeRecord($item);
        }

        $groupBy = trim((string) ($options['by'] ?? ''));

        if ($groupBy === '') {
            return collect($rows)
                ->values()
                ->map(static fn (array $row, int $index): array => [
                    'row' => $row,
                    'index' => $index + 1,
                ])
                ->all();
        }

        $result = [];
        $groupIndex = 0;

        foreach (collect($rows)->groupBy(static fn (array $row): string => (string) (self::resolvePath($row, $groupBy) ?? '')) as $groupKey => $groupRows) {
            $groupIndex++;

            foreach ($groupRows->values() as $index => $row) {
                $result[] = [
                    'row' => $row,
                    'index' => $index + 1,
                    'group_key' => $groupKey,
                    'group_index' => $groupIndex,
                ];
            }
        }

        return $result;
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

        if (! is_file($candidate)) {
            return null;
        }

        $storageAppPath = storage_path('app');

        if (! str_starts_with($candidate, $storageAppPath)) {
            return null;
        }

        $extension = strtolower((string) pathinfo($candidate, PATHINFO_EXTENSION));

        return in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp'], true)
            ? $candidate
            : null;
    }

    private static function mimeTypeForImageExtension(string $extension): ?string
    {
        return match (strtolower($extension)) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            default => null,
        };
    }
}
