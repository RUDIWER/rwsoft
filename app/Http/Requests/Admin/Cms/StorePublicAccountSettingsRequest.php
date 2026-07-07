<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePublicAccountSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'registration_enabled' => ['nullable', 'boolean'],
            'email_verification_required' => ['nullable', 'boolean'],
            'two_factor_mode' => ['required', 'string', 'in:disabled,optional,required'],
        ];
    }
}
