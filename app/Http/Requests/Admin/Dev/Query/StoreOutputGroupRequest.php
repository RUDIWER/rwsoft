<?php

namespace App\Http\Requests\Admin\Dev\Query;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOutputGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $groupType = (string) $this->route('groupType');
        $groupId = (int) ($this->route('groupId') ?? 0);
        $table = match ($groupType) {
            'query' => 'query_groups',
            'report' => 'report_groups',
            'chart' => 'chart_groups',
            default => 'query_groups',
        };

        return [
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique($table, 'name')->ignore($groupId > 0 ? $groupId : null),
            ],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('query_builder_ui.validation.name_required'),
            'name.unique' => __('query_builder_ui.validation.group_name_unique'),
        ];
    }
}
