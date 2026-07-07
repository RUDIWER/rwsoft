<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExportCmsSitePackageRequest extends FormRequest
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
            'package_key' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'package_name' => ['nullable', 'string', 'max:120'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', 'in:site,public_texts,layouts,templates,pages,menus,media,downloads,themes,redirects,taxonomies,blogs,forms,docs'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'package_key.regex' => __('cms_admin_ui.validation.site_package_export_key_format'),
        ];
    }
}
