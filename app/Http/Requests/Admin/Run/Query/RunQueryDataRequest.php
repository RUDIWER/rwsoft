<?php

namespace App\Http\Requests\Admin\Run\Query;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RunQueryDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'rowsPerPage' => ['nullable', 'integer', 'min:1', 'max:200'],
            'sortField' => ['nullable', 'string', 'max:160'],
            'sortOrder' => ['nullable', 'string', 'in:asc,desc'],
            'global' => ['nullable', 'string', 'max:255'],
            'filters' => ['nullable', 'array'],
            'filterModes' => ['nullable', 'array'],
            'filterTypes' => ['nullable', 'array'],
            'bindings' => ['nullable', 'array'],
            'bindings.*' => ['nullable'],
        ];
    }
}
