<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCmsDownloadFolderRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'access_mode' => ['required', 'string', Rule::in(['inherit', 'public', 'authenticated', 'restricted', 'password'])],
            'password' => ['nullable', 'string', 'max:255'],
            'clear_password' => ['nullable', 'boolean'],
            'password_expires_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'access_rules' => ['nullable', 'array'],
            'access_rules.*.rule_type' => ['required_with:access_rules', 'string', Rule::in(['site_user', 'download_group', 'profile_field'])],
            'access_rules.*.site_user_id' => ['nullable', 'integer', 'exists:site_users,id'],
            'access_rules.*.cms_download_group_id' => ['nullable', 'integer', 'exists:cms_download_groups,id'],
            'access_rules.*.profile_field_key' => ['nullable', 'string', 'max:80'],
            'access_rules.*.operator' => ['nullable', 'string', Rule::in(['equals', 'not_equals', 'in', 'not_in', 'contains', 'filled'])],
            'access_rules.*.value' => ['nullable'],
        ];
    }
}
