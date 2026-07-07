<?php

namespace App\Support\Cms;

use Illuminate\Validation\ValidationException;

class CmsBlockPackageMapper
{
    public function __construct(private readonly CmsBlockRegistry $blockRegistry) {}

    /**
     * @param  array<string, mixed>  $content
     * @param  array<int, string>  $mediaKeys
     * @param  array<int, string>  $categoryKeys
     * @param  array<int, string>  $tagKeys
     * @param  array<string, string>  $formKeysByTranslationKey
     * @param  array<int, string>  $menuKeys
     * @param  array<int, string>  $downloadKeys
     * @param  array<int, string>  $downloadFolderKeys
     * @return array<string, mixed>
     */
    public function exportBlockContent(string $type, array $content, array $mediaKeys, array $categoryKeys = [], array $tagKeys = [], array $formKeysByTranslationKey = [], array $menuKeys = [], array $downloadKeys = [], array $downloadFolderKeys = []): array
    {
        foreach ($this->mappingsFor($type, $content) as $field => $mapping) {
            $content = $this->exportField($content, $field, $mapping, $mediaKeys, $categoryKeys, $tagKeys, $formKeysByTranslationKey, $menuKeys, $downloadKeys, $downloadFolderKeys);
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, int>  $mediaMappings
     * @param  array<string, int>  $categoryMappings
     * @param  array<string, int>  $tagMappings
     * @param  array<string, string>  $formMappings
     * @param  array<string, int>  $menuMappings
     * @param  array<string, int>  $downloadMappings
     * @param  array<string, int>  $downloadFolderMappings
     * @return array<string, mixed>
     */
    public function importBlockContent(string $type, array $content, array $mediaMappings, array $categoryMappings = [], array $tagMappings = [], array $formMappings = [], array $menuMappings = [], array $downloadMappings = [], array $downloadFolderMappings = []): array
    {
        foreach ($this->mappingsFor($type, $content) as $field => $mapping) {
            $content = $this->importField($content, $field, $mapping, $mediaMappings, $categoryMappings, $tagMappings, $formMappings, $menuMappings, $downloadMappings, $downloadFolderMappings);
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, string>
     */
    private function mappingsFor(string $type, array $content): array
    {
        $mappings = $this->blockRegistry->packageMappingsFor($type);

        foreach ($this->blockRegistry->fieldsFor($type) as $field) {
            if (! is_string($field) || isset($mappings[$field])) {
                continue;
            }

            $mapping = $this->legacyMappingForField($field) ?? $this->blockRegistry->packageMappingForField($type, $field);

            if (is_string($mapping) && $mapping !== '') {
                $mappings[$field] = $mapping;
            }
        }

        foreach ($content as $field => $value) {
            if (! is_string($field) || isset($mappings[$field])) {
                continue;
            }

            $mapping = $this->legacyMappingForField($field) ?? $this->blockRegistry->packageMappingForField($type, $field);

            if (is_string($mapping) && $mapping !== '') {
                $mappings[$field] = $mapping;
            }
        }

        return $mappings;
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<int, string>  $mediaKeys
     * @param  array<int, string>  $categoryKeys
     * @param  array<int, string>  $tagKeys
     * @param  array<string, string>  $formKeysByTranslationKey
     * @param  array<int, string>  $menuKeys
     * @param  array<int, string>  $downloadKeys
     * @param  array<int, string>  $downloadFolderKeys
     * @return array<string, mixed>
     */
    private function exportField(array $content, string $field, string $mapping, array $mediaKeys, array $categoryKeys, array $tagKeys, array $formKeysByTranslationKey, array $menuKeys, array $downloadKeys, array $downloadFolderKeys): array
    {
        if (! array_key_exists($field, $content)) {
            return $content;
        }

        return match ($mapping) {
            'media.single' => $this->exportSingleIdField($content, $field, $this->exportFieldName($field, 'media_import_key'), $mediaKeys),
            'media.multiple' => $this->exportMultipleIdField($content, $field, $this->exportFieldName($field, 'media_import_keys'), $mediaKeys),
            'download.single' => $this->exportSingleIdField($content, $field, $this->exportFieldName($field, 'download_import_key'), $downloadKeys),
            'download.multiple' => $this->exportMultipleIdField($content, $field, $this->exportFieldName($field, 'download_import_keys'), $downloadKeys),
            'download_folder.single' => $this->exportSingleIdField($content, $field, $this->exportFieldName($field, 'download_folder_import_key'), $downloadFolderKeys),
            'download_folder.multiple' => $this->exportMultipleIdField($content, $field, $this->exportFieldName($field, 'download_folder_import_keys'), $downloadFolderKeys),
            'category.import_key' => $this->exportSingleIdField($content, $field, $this->exportFieldName($field, 'category_import_key'), $categoryKeys),
            'tag.import_key' => $this->exportSingleIdField($content, $field, $this->exportFieldName($field, 'tag_import_key'), $tagKeys),
            'form.translation_key' => $this->exportFormField($content, $field, $formKeysByTranslationKey),
            'menu.import_key' => $this->exportSingleIdField($content, $field, $this->exportFieldName($field, 'menu_import_key'), $menuKeys),
            default => $content,
        };
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, int>  $mediaMappings
     * @param  array<string, int>  $categoryMappings
     * @param  array<string, int>  $tagMappings
     * @param  array<string, string>  $formMappings
     * @param  array<string, int>  $menuMappings
     * @param  array<string, int>  $downloadMappings
     * @param  array<string, int>  $downloadFolderMappings
     * @return array<string, mixed>
     */
    private function importField(array $content, string $field, string $mapping, array $mediaMappings, array $categoryMappings, array $tagMappings, array $formMappings, array $menuMappings, array $downloadMappings, array $downloadFolderMappings): array
    {
        return match ($mapping) {
            'media.single' => $this->importSingleIdField($content, $field, $this->exportFieldName($field, 'media_import_key'), $mediaMappings, 'media'),
            'media.multiple' => $this->importMultipleIdField($content, $field, $this->exportFieldName($field, 'media_import_keys'), $mediaMappings, 'media'),
            'download.single' => $this->importSingleIdField($content, $field, $this->exportFieldName($field, 'download_import_key'), $downloadMappings, 'download'),
            'download.multiple' => $this->importMultipleIdField($content, $field, $this->exportFieldName($field, 'download_import_keys'), $downloadMappings, 'download'),
            'download_folder.single' => $this->importSingleIdField($content, $field, $this->exportFieldName($field, 'download_folder_import_key'), $downloadFolderMappings, 'download_folder'),
            'download_folder.multiple' => $this->importMultipleIdField($content, $field, $this->exportFieldName($field, 'download_folder_import_keys'), $downloadFolderMappings, 'download_folder'),
            'category.import_key' => $this->importSingleIdField($content, $field, $this->exportFieldName($field, 'category_import_key'), $categoryMappings, 'category'),
            'tag.import_key' => $this->importSingleIdField($content, $field, $this->exportFieldName($field, 'tag_import_key'), $tagMappings, 'tag'),
            'form.translation_key' => $this->importFormField($content, $field, $formMappings),
            'menu.import_key' => $this->importSingleIdField($content, $field, $this->exportFieldName($field, 'menu_import_key'), $menuMappings, 'menu'),
            default => $content,
        };
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<int, string>  $mappings
     * @return array<string, mixed>
     */
    private function exportSingleIdField(array $content, string $sourceField, string $targetField, array $mappings): array
    {
        $id = (int) ($content[$sourceField] ?? 0);

        if ($id > 0 && isset($mappings[$id])) {
            $content[$targetField] = $mappings[$id];
        }

        unset($content[$sourceField]);

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<int, string>  $mappings
     * @return array<string, mixed>
     */
    private function exportMultipleIdField(array $content, string $sourceField, string $targetField, array $mappings): array
    {
        $content[$targetField] = collect(is_array($content[$sourceField] ?? null) ? $content[$sourceField] : [])
            ->map(fn (mixed $id): ?string => $mappings[(int) $id] ?? null)
            ->filter()
            ->values()
            ->all();

        unset($content[$sourceField]);

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, string>  $formKeysByTranslationKey
     * @return array<string, mixed>
     */
    private function exportFormField(array $content, string $field, array $formKeysByTranslationKey): array
    {
        $translationKey = (string) ($content[$field] ?? '');

        if ($translationKey !== '' && isset($formKeysByTranslationKey[$translationKey])) {
            $content[$this->formExportFieldName($field)] = $formKeysByTranslationKey[$translationKey];
        }

        unset($content[$field]);

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, int>  $mappings
     * @return array<string, mixed>
     */
    private function importSingleIdField(array $content, string $targetField, string $sourceField, array $mappings, string $type): array
    {
        $importKey = (string) ($content[$sourceField] ?? '');

        if ($importKey !== '') {
            $content[$targetField] = $this->mappedId($mappings, $importKey, $type);
            unset($content[$sourceField]);
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, int>  $mappings
     * @return array<string, mixed>
     */
    private function importMultipleIdField(array $content, string $targetField, string $sourceField, array $mappings, string $type): array
    {
        if (! is_array($content[$sourceField] ?? null)) {
            return $content;
        }

        $content[$targetField] = collect($content[$sourceField])
            ->map(fn (mixed $importKey): int => $this->mappedId($mappings, $importKey, $type))
            ->values()
            ->all();
        unset($content[$sourceField]);

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, string>  $formMappings
     * @return array<string, mixed>
     */
    private function importFormField(array $content, string $targetField, array $formMappings): array
    {
        $sourceField = $this->formExportFieldName($targetField);
        $importKey = (string) ($content[$sourceField] ?? '');

        if ($importKey !== '') {
            $translationKey = $formMappings[$importKey] ?? null;

            if (! is_string($translationKey) || $translationKey === '') {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_mapping', ['type' => 'form', 'key' => $importKey]),
                ]);
            }

            $content[$targetField] = $translationKey;
            unset($content[$sourceField]);
        }

        return $content;
    }

    private function mappedId(array $mappings, mixed $importKey, string $type): int
    {
        $importKey = (string) $importKey;
        $id = $mappings[$importKey] ?? null;

        if (! is_int($id)) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_mapping', ['type' => $type, 'key' => $importKey]),
            ]);
        }

        return $id;
    }

    private function exportFieldName(string $field, string $legacyField): string
    {
        return match ($field) {
            'media_asset_id', 'media_asset_ids', 'category_id', 'tag_id' => $legacyField,
            default => str($field)
                ->replaceEnd('_ids', '_import_keys')
                ->replaceEnd('_id', '_import_key')
                ->toString(),
        };
    }

    private function formExportFieldName(string $field): string
    {
        if (in_array($field, ['form_key', 'form_translation_key'], true)) {
            return 'form_import_key';
        }

        if (str($field)->endsWith('_translation_key')) {
            return str($field)->replaceEnd('_translation_key', '_import_key')->toString();
        }

        return $field.'_import_key';
    }

    private function legacyMappingForField(string $field): ?string
    {
        return match ($field) {
            'media_asset_id' => 'media.single',
            'media_asset_ids' => 'media.multiple',
            'form_key', 'form_translation_key' => 'form.translation_key',
            'cms_menu_id' => 'menu.import_key',
            'category_id' => 'category.import_key',
            'tag_id' => 'tag.import_key',
            default => null,
        };
    }
}
