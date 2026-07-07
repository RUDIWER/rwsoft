<?php

namespace App\Http\Requests\Admin\Dev\Query;

use App\Models\Query\QueryBuilderSelectTable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQueryBindingSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $source = $this->route('source');
        $sourceId = $source instanceof QueryBuilderSelectTable ? (int) $source->id : 0;

        return [
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('query_builder_select_tables', 'name')->ignore($sourceId),
            ],
            'table_name' => ['required', 'string', 'max:160', 'regex:/^[a-zA-Z0-9_]+$/'],
            'select_field' => ['required', 'string', 'max:160', 'regex:/^[a-zA-Z0-9_]+$/'],
            'label_fields' => ['required', 'array', 'min:1'],
            'label_fields.*' => ['required', 'string', 'max:160', 'regex:/^[a-zA-Z0-9_]+$/'],
            'search_fields' => ['nullable', 'array'],
            'search_fields.*' => ['nullable', 'string', 'max:160', 'regex:/^[a-zA-Z0-9_]+$/'],
            'default_filters' => ['nullable', 'array'],
            'default_sort' => ['nullable', 'array'],
            'default_sort.field' => ['nullable', 'string', 'max:160', 'regex:/^[a-zA-Z0-9_]+$/'],
            'default_sort.direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('query_builder_ui.validation.name_required'),
            'name.unique' => __('query_builder_ui.validation.name_unique'),
            'table_name.required' => __('query_builder_ui.validation.table_name_required'),
            'select_field.required' => __('query_builder_ui.validation.select_field_required'),
            'label_fields.required' => __('query_builder_ui.validation.label_fields_required'),
            'label_fields.min' => __('query_builder_ui.validation.label_fields_required'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $labelFields = $this->normalizeFields($this->input('label_fields', []));
        $searchFields = $this->normalizeFields($this->input('search_fields', []));
        $defaultSortField = trim((string) data_get($this->input('default_sort', []), 'field', ''));
        $defaultSortDirection = strtolower(trim((string) data_get($this->input('default_sort', []), 'direction', 'asc')));

        $this->merge([
            'table_name' => trim((string) $this->input('table_name', '')),
            'select_field' => trim((string) $this->input('select_field', '')),
            'label_fields' => $labelFields,
            'search_fields' => $searchFields,
            'default_sort' => $defaultSortField !== ''
                ? [
                    'field' => $defaultSortField,
                    'direction' => $defaultSortDirection === 'desc' ? 'desc' : 'asc',
                ]
                : null,
        ]);
    }

    /**
     * @param  array<int, mixed>|string|null  $value
     * @return array<int, string>
     */
    private function normalizeFields(array|string|null $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(static fn (mixed $field): string => trim((string) $field))
            ->filter(static fn (string $field): bool => $field !== '')
            ->unique()
            ->values()
            ->all();
    }
}
