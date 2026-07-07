<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivateCmsSitePackageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'package_key' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['required', 'string', Rule::in(['layouts', 'templates', 'pages', 'menus', 'downloads', 'redirects', 'taxonomies', 'blogs', 'forms', 'docs', 'themes'])],
            'publish_pages' => ['nullable', 'boolean'],
            'publish_blogs' => ['nullable', 'boolean'],
            'set_homepage' => ['nullable', 'boolean'],
            'set_default_layouts' => ['nullable', 'boolean'],
            'set_default_templates' => ['nullable', 'boolean'],
            'activate_theme_import_key' => ['nullable', 'string', 'max:160', 'regex:/^[A-Za-z0-9][A-Za-z0-9_.-]*$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'package_key.required' => __('cms_admin_ui.validation.site_package_activation_key_required'),
            'package_key.regex' => __('cms_admin_ui.validation.site_package_export_key_format'),
            'modules.required' => __('cms_admin_ui.validation.site_package_activation_modules_required'),
            'modules.min' => __('cms_admin_ui.validation.site_package_activation_modules_required'),
            'modules.*.in' => __('cms_admin_ui.validation.site_package_activation_module_invalid'),
            'activate_theme_import_key.regex' => __('cms_admin_ui.validation.site_package_activation_theme_key_format'),
        ];
    }
}
