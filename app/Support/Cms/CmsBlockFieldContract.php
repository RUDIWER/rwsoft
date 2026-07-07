<?php

namespace App\Support\Cms;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class CmsBlockFieldContract
{
    /**
     * @var array<int, string>
     */
    public const FIELD_TYPES = [
        'text',
        'textarea',
        'number',
        'checkbox',
        'url',
        'rich_text',
        'markdown',
        'select',
        'media_select',
        'media_list',
        'download_select',
        'download_list',
        'download_folder_select',
        'download_folder_list',
        'form_select',
        'menu_select',
        'code',
        'repeater',
    ];

    public function __construct(
        private readonly CmsBlockRegistry $blockRegistry,
        private readonly CmsHtmlSanitizer $htmlSanitizer,
    ) {}

    /**
     * @param  array<string, mixed>|null  $schema
     * @param  array<string, mixed>|null  $defaults
     * @return array<int, array<string, mixed>>
     */
    public function fieldsForBlock(string $rendererKey, ?array $schema = null, ?array $defaults = null): array
    {
        $schemaFields = $this->stringList($schema['fields'] ?? null);
        $fieldKeys = $schemaFields !== [] ? $schemaFields : $this->blockRegistry->fieldsFor($rendererKey);
        $defaults = is_array($defaults) ? $defaults : $this->blockRegistry->defaultsFor($rendererKey);
        $editorFields = $this->editorFields($rendererKey, $schema);

        return collect($fieldKeys)
            ->map(function (string $fieldKey, int $index) use ($defaults, $editorFields): array {
                $editorField = $editorFields[$fieldKey] ?? [];

                return $this->normalizeField($fieldKey, $editorField, $defaults[$fieldKey] ?? null, $index);
            })
            ->filter(fn (array $field): bool => $field['key'] !== '')
            ->unique('key')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(array $fields, string $prefix): array
    {
        $rules = [
            $prefix => ['nullable', 'array'],
        ];

        foreach ($fields as $field) {
            $fieldPrefix = $prefix.'.'.$field['key'];
            $base = (bool) ($field['required'] ?? false) ? ['required'] : ['nullable'];

            $rules[$fieldPrefix] = match ($field['type']) {
                'textarea' => [...$base, 'string', 'max:20000'],
                'rich_text', 'markdown' => [...$base, 'string', 'max:50000'],
                'code' => [...$base, 'string', 'max:50000'],
                'url' => [...$base, 'string', 'max:2048', 'regex:/^(\/|https?:\/\/)/i'],
                'number' => [...$base, 'numeric'],
                'checkbox' => [...$base, 'boolean'],
                'media_select' => [...$base, 'integer', Rule::exists('cms_media_assets', 'id')->whereNull('deleted_at')],
                'media_list' => [...$base, 'array'],
                'download_select' => [...$base, 'integer', Rule::exists('cms_download_assets', 'id')->whereNull('deleted_at')],
                'download_list' => [...$base, 'array'],
                'download_folder_select' => [...$base, 'integer', Rule::exists('cms_download_folders', 'id')],
                'download_folder_list' => [...$base, 'array'],
                'form_select' => [...$base, 'string', Rule::exists('cms_forms', 'translation_key')->where('is_active', true)],
                'menu_select' => [...$base, 'integer', Rule::exists('cms_menus', 'id')->where('is_active', true)],
                'select' => [...$base, Rule::in($this->selectOptionValues($field))],
                'repeater' => [...$base, 'array'],
                default => [...$base, 'string', 'max:255'],
            };

            if ($field['type'] === 'media_list') {
                $rules[$fieldPrefix.'.*'] = ['integer', Rule::exists('cms_media_assets', 'id')->whereNull('deleted_at')];
            }

            if ($field['type'] === 'download_list') {
                $rules[$fieldPrefix.'.*'] = ['integer', Rule::exists('cms_download_assets', 'id')->whereNull('deleted_at')];
            }

            if ($field['type'] === 'download_folder_list') {
                $rules[$fieldPrefix.'.*'] = ['integer', Rule::exists('cms_download_folders', 'id')];
            }

            if ($field['type'] === 'repeater') {
                $rules = array_merge($rules, $this->repeaterValidationRules($field, $fieldPrefix));
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public function cleanData(array $data, array $fields): array
    {
        $clean = [];

        foreach ($fields as $field) {
            $fieldKey = (string) $field['key'];
            $value = Arr::get($data, $fieldKey);

            if ($value === null || $value === '') {
                $value = $field['default'] ?? null;
            }

            $value = $this->cleanValue($value, $field);

            if ($this->filledValue($value, $field)) {
                Arr::set($clean, $fieldKey, $value);
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $editorField
     * @return array<string, mixed>
     */
    private function normalizeField(string $fieldKey, array $editorField, mixed $default, int $index): array
    {
        $type = in_array($editorField['type'] ?? null, self::FIELD_TYPES, true)
            ? (string) $editorField['type']
            : 'text';
        $type = $fieldKey === 'url' || str_ends_with($fieldKey, '_url') ? 'url' : $type;

        return [
            'key' => $this->normalizeFieldKey($fieldKey),
            'type' => $type,
            'required' => (bool) ($editorField['required'] ?? false),
            'sort_order' => (int) ($editorField['sort_order'] ?? (($index + 1) * 10)),
            'default' => $default,
            'label_key' => is_string($editorField['label_key'] ?? null) ? $editorField['label_key'] : null,
            'placeholder_key' => is_string($editorField['placeholder_key'] ?? null) ? $editorField['placeholder_key'] : null,
            'translations' => is_array($editorField['translations'] ?? null) ? $editorField['translations'] : [],
            'options' => is_array($editorField['options'] ?? null) ? array_values($editorField['options']) : [],
            'fields' => $type === 'repeater' && is_array($editorField['fields'] ?? null)
                ? $this->normalizeRepeaterFields($editorField['fields'])
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $schema
     * @return array<string, array<string, mixed>>
     */
    private function editorFields(string $rendererKey, ?array $schema): array
    {
        $schemaEditorFields = is_array($schema['editor_fields'] ?? null)
            ? $schema['editor_fields']
            : Arr::get($schema ?? [], 'editor.fields', []);
        $editorFields = is_array($schemaEditorFields) && $schemaEditorFields !== []
            ? $schemaEditorFields
            : Arr::get($this->blockRegistry->definition($rendererKey), 'editor.fields', []);

        return collect($editorFields)
            ->filter(fn (mixed $field): bool => is_array($field) && is_string($field['name'] ?? null))
            ->mapWithKeys(fn (array $field): array => [(string) $field['name'] => $field])
            ->all();
    }

    /**
     * @param  array<int, mixed>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRepeaterFields(array $fields): array
    {
        return collect($fields)
            ->filter(fn (mixed $field): bool => is_array($field) && is_string($field['name'] ?? null))
            ->map(function (array $field, int $index): array {
                $type = in_array($field['type'] ?? null, ['text', 'textarea', 'number', 'checkbox', 'select', 'media_select', 'download_select', 'download_folder_select'], true)
                    ? (string) $field['type']
                    : 'text';

                return [
                    'key' => $this->normalizeFieldKey((string) $field['name']),
                    'type' => $type,
                    'required' => (bool) ($field['required'] ?? false),
                    'sort_order' => (int) ($field['sort_order'] ?? (($index + 1) * 10)),
                    'default' => $field['default'] ?? null,
                    'options' => is_array($field['options'] ?? null) ? array_values($field['options']) : [],
                    'label_key' => is_string($field['label_key'] ?? null) ? $field['label_key'] : null,
                    'translations' => is_array($field['translations'] ?? null) ? $field['translations'] : [],
                ];
            })
            ->filter(fn (array $field): bool => $field['key'] !== '')
            ->unique('key')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, array<int, mixed>>
     */
    private function repeaterValidationRules(array $field, string $prefix): array
    {
        $rules = [];

        foreach ($field['fields'] ?? [] as $nestedField) {
            if (! is_array($nestedField)) {
                continue;
            }

            $fieldPrefix = $prefix.'.*.'.$nestedField['key'];
            $base = (bool) ($nestedField['required'] ?? false) ? ['required'] : ['nullable'];
            $rules[$fieldPrefix] = match ($nestedField['type']) {
                'textarea' => [...$base, 'string', 'max:20000'],
                'number' => [...$base, 'numeric'],
                'url' => [...$base, 'string', 'max:2048', 'regex:/^(\/|https?:\/\/)/i'],
                'checkbox' => [...$base, 'boolean'],
                'media_select' => [...$base, 'integer', Rule::exists('cms_media_assets', 'id')->whereNull('deleted_at')],
                'select' => [...$base, Rule::in($this->selectOptionValues($nestedField))],
                default => [...$base, 'string', 'max:255'],
            };
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private function selectOptionValues(array $field): array
    {
        return collect($field['options'] ?? [])
            ->map(fn (mixed $option): mixed => is_array($option) ? ($option['value'] ?? null) : $option)
            ->filter(fn (mixed $value): bool => is_scalar($value) && $value !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function cleanValue(mixed $value, array $field): mixed
    {
        return match ($field['type']) {
            'number' => is_numeric($value) ? $value + 0 : null,
            'checkbox' => (bool) $value,
            'rich_text' => $this->htmlSanitizer->clean($value),
            'media_select', 'menu_select' => (int) $value > 0 ? (int) $value : null,
            'media_list' => $this->cleanIntegerList($value),
            'repeater' => $this->cleanRepeaterItems($value, is_array($field['fields'] ?? null) ? $field['fields'] : []),
            'select' => $this->cleanSelectValue($value, $field),
            default => is_scalar($value) ? trim((string) $value) : null,
        };
    }

    /**
     * @return array<int, int>
     */
    private function cleanIntegerList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function cleanRepeaterItems(mixed $value, array $fields): array
    {
        if (! is_array($value) || $fields === []) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => $this->cleanData($item, $fields))
            ->filter(fn (array $item): bool => $item !== [])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function cleanSelectValue(mixed $value, array $field): mixed
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = (string) $value;
        $options = $this->selectOptionValues($field);

        return $options === [] || in_array($value, $options, true) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function filledValue(mixed $value, array $field): bool
    {
        if ($field['type'] === 'checkbox') {
            return true;
        }

        return $value !== null && $value !== '' && $value !== [];
    }

    private function normalizeFieldKey(string $key): string
    {
        $key = trim($key);
        $key = preg_replace('/[^a-z0-9_]+/', '_', mb_strtolower($key)) ?: '';

        return trim($key, '_');
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->values()
            ->all();
    }
}
