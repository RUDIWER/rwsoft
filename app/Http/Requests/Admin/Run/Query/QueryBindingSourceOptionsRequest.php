<?php

namespace App\Http\Requests\Admin\Run\Query;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class QueryBindingSourceOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'source_table_id' => ['required', 'integer', 'exists:query_builder_select_tables,id'],
            'q' => ['nullable', 'string', 'max:160'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
