<?php

namespace App\Http\Requests\PublicSite;

use App\Support\PublicSite\PublicAccountProfileFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SiteUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('site_user')->check();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ], app(PublicAccountProfileFields::class)->rules('profile'));
    }
}
