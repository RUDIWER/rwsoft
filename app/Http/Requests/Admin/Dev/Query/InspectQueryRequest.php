<?php

namespace App\Http\Requests\Admin\Dev\Query;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InspectQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:120000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'query.required' => __('query_builder_ui.validation.sql_query_required'),
        ];
    }
}
