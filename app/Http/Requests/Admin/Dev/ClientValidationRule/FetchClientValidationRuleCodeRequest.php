<?php

namespace App\Http\Requests\Admin\Dev\ClientValidationRule;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FetchClientValidationRuleCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'version_id' => ['required', 'integer', 'exists:rw_client_rule_versions,id'],
        ];
    }
}
