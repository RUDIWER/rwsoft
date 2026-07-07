<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCmsDownloadAssetRequest extends FormRequest
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
            'folder_id' => ['nullable', 'integer', 'exists:cms_download_folders,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'translations' => ['nullable', 'array'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:5000'],
            'access_mode' => ['required', 'string', Rule::in(['inherit', 'public', 'authenticated', 'restricted', 'password'])],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
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
