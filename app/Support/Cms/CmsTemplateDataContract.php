<?php

namespace App\Support\Cms;

use App\Models\Cms\CmsTemplate;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CmsTemplateDataContract
{
    /**
     * @var array<int, string>
     */
    public const TEMPLATE_FIELD_TYPES = [
        'text',
        'textarea',
        'number',
        'boolean',
        'url',
        'media',
        'select',
    ];

    public function __construct(private readonly CmsTemplateFieldRegistry $fieldRegistry) {}

    /**
     * @param  array<string, mixed>|null  $contract
     * @return array{system_fields: array<int, array{key: string, enabled: bool}>, template_fields: array<int, array<string, mixed>>}
     */
    public function normalize(?array $contract, string $templateKey): array
    {
        $allowedSystemFields = collect($this->fieldRegistry->fieldsFor($templateKey))
            ->pluck('key')
            ->values()
            ->all();

        $rawSystemFields = is_array($contract['system_fields'] ?? null)
            ? $contract['system_fields']
            : collect($allowedSystemFields)
                ->map(fn (string $key): array => ['key' => $key, 'enabled' => true])
                ->all();

        $systemFields = collect($rawSystemFields)
            ->filter(fn (mixed $field): bool => is_array($field))
            ->map(fn (array $field): array => [
                'key' => (string) ($field['key'] ?? ''),
                'enabled' => (bool) ($field['enabled'] ?? true),
            ])
            ->filter(fn (array $field): bool => in_array($field['key'], $allowedSystemFields, true))
            ->unique('key')
            ->values()
            ->all();

        $templateFields = collect(is_array($contract['template_fields'] ?? null) ? $contract['template_fields'] : [])
            ->filter(fn (mixed $field): bool => is_array($field))
            ->map(fn (array $field, int $index): array => $this->normalizeTemplateField($field, $index))
            ->filter(fn (array $field): bool => $field['key'] !== '')
            ->unique('key')
            ->sortBy('sort_order')
            ->values()
            ->all();

        return [
            'system_fields' => $systemFields,
            'template_fields' => $templateFields,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableSystemFields(string $templateKey): array
    {
        return collect($this->fieldRegistry->fieldsFor($templateKey))
            ->map(fn (array $field): array => [
                'key' => (string) $field['key'],
                'label_key' => (string) $field['label_key'],
                'group_key' => (string) $field['group_key'],
                'type' => (string) $field['type'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fieldOptions(CmsTemplate $template, string $locale): array
    {
        $contract = $this->normalize($template->data_contract, (string) $template->template_key);
        $enabledSystemKeys = collect($contract['system_fields'])
            ->filter(fn (array $field): bool => (bool) $field['enabled'])
            ->pluck('key')
            ->all();

        $systemFields = collect($this->availableSystemFields((string) $template->template_key))
            ->filter(fn (array $field): bool => in_array($field['key'], $enabledSystemKeys, true))
            ->map(fn (array $field): array => array_merge($field, [
                'source' => 'system',
                'value' => $field['key'],
                'label' => __($field['label_key']).' ('.$field['key'].')',
            ]));

        $templateFields = collect($contract['template_fields'])
            ->map(fn (array $field): array => [
                'key' => 'template.'.$field['key'],
                'source' => 'template',
                'type' => $field['type'],
                'value' => 'template.'.$field['key'],
                'label' => $this->translatedFieldText($field, 'label', $locale).' (template.'.$field['key'].')',
            ]);

        return $systemFields
            ->merge($templateFields)
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(CmsTemplate $template, string $prefix = 'template_data'): array
    {
        $contract = $this->normalize($template->data_contract, (string) $template->template_key);
        $rules = [
            $prefix => ['nullable', 'array'],
        ];

        foreach ($contract['template_fields'] as $field) {
            $fieldPrefix = $prefix.'.'.$field['key'];
            $required = (bool) ($field['required'] ?? false);
            $base = $required ? ['required'] : ['nullable'];

            $rules[$fieldPrefix] = match ($field['type']) {
                'textarea' => [...$base, 'string', 'max:10000'],
                'number' => [...$base, 'numeric'],
                'boolean' => [...$base, 'boolean'],
                'url' => [...$base, 'url', 'max:2048'],
                'media' => [...$base, 'integer', Rule::exists('cms_media_assets', 'id')->whereNull('deleted_at')],
                'select' => [...$base, Rule::in($this->selectOptionValues($field))],
                default => [...$base, 'string', 'max:255'],
            };
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function cleanTemplateData(array $data, CmsTemplate $template): array
    {
        $contract = $this->normalize($template->data_contract, (string) $template->template_key);
        $clean = [];

        foreach ($contract['template_fields'] as $field) {
            $value = Arr::get($data, $field['key']);

            if ($value === null || $value === '') {
                $default = $field['default'] ?? null;
                $value = $default === '' ? null : $default;
            }

            if ($field['type'] === 'boolean') {
                $value = (bool) $value;
            }

            if ($field['type'] === 'media') {
                $value = (int) $value > 0 ? (int) $value : null;
            }

            if ($value !== null && $value !== '') {
                Arr::set($clean, $field['key'], $value);
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>|null  $contract
     */
    public function validateContract(Validator $validator, ?array $contract, string $templateKey, string $attribute = 'data_contract'): void
    {
        $allowedSystemKeys = collect($this->availableSystemFields($templateKey))->pluck('key')->all();
        $systemKeys = [];

        foreach ((array) ($contract['system_fields'] ?? []) as $index => $field) {
            $key = is_array($field) ? (string) ($field['key'] ?? '') : '';

            if ($key === '' || ! in_array($key, $allowedSystemKeys, true)) {
                $validator->errors()->add("{$attribute}.system_fields.{$index}.key", __('cms_admin_ui.validation.template_field_forbidden'));
            }

            if (in_array($key, $systemKeys, true)) {
                $validator->errors()->add("{$attribute}.system_fields.{$index}.key", __('cms_admin_ui.validation.duplicate_template_field'));
            }

            $systemKeys[] = $key;
        }

        $templateKeys = [];

        foreach ((array) ($contract['template_fields'] ?? []) as $index => $field) {
            $key = is_array($field) ? (string) ($field['key'] ?? '') : '';
            $type = is_array($field) ? (string) ($field['type'] ?? '') : '';

            if (! $this->isValidTemplateFieldKey($key)) {
                $validator->errors()->add("{$attribute}.template_fields.{$index}.key", __('cms_admin_ui.validation.template_field_key_invalid'));
            }

            if (in_array($key, $templateKeys, true)) {
                $validator->errors()->add("{$attribute}.template_fields.{$index}.key", __('cms_admin_ui.validation.duplicate_template_field'));
            }

            if (! in_array($type, self::TEMPLATE_FIELD_TYPES, true)) {
                $validator->errors()->add("{$attribute}.template_fields.{$index}.type", __('cms_admin_ui.validation.invalid_choice'));
            }

            $templateKeys[] = $key;
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function normalizeTemplateField(array $field, int $index): array
    {
        $key = $this->normalizeTemplateFieldKey((string) ($field['key'] ?? ''));
        $type = in_array($field['type'] ?? null, self::TEMPLATE_FIELD_TYPES, true)
            ? (string) $field['type']
            : 'text';

        return [
            'key' => $key,
            'type' => $type,
            'required' => (bool) ($field['required'] ?? false),
            'sort_order' => (int) ($field['sort_order'] ?? (($index + 1) * 10)),
            'default' => $field['default'] ?? null,
            'options' => is_array($field['options'] ?? null) ? array_values($field['options']) : [],
            'translations' => is_array($field['translations'] ?? null) ? $field['translations'] : [],
        ];
    }

    private function translatedFieldText(array $field, string $name, string $locale): string
    {
        $fallback = (string) ($field['translations']['en'][$name] ?? $field['key']);

        return (string) ($field['translations'][$locale][$name] ?? $fallback);
    }

    private function normalizeTemplateFieldKey(string $key): string
    {
        $key = trim($key);
        $key = preg_replace('/[^A-Za-z0-9_.]+/', '_', $key) ?: '';
        $key = trim($key, '._');

        return preg_replace('/\.{2,}/', '.', $key) ?: '';
    }

    private function isValidTemplateFieldKey(string $key): bool
    {
        return preg_match('/^(?!template\.)(?!page\.)(?!blog\.)(?!site\.)(?!category\.)(?!tag\.)[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*)*$/', $key) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function selectOptionValues(array $field): array
    {
        return collect($field['options'] ?? [])
            ->map(fn (mixed $option): string => is_array($option) ? (string) ($option['value'] ?? '') : (string) $option)
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->all();
    }
}
