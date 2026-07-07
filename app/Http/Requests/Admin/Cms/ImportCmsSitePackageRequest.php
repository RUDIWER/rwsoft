<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportCmsSitePackageRequest extends FormRequest
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
            'site_package_zip' => ['required', 'file', 'mimes:zip', 'max:51200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'site_package_zip.required' => __('cms_admin_ui.validation.site_package_zip_required'),
            'site_package_zip.mimes' => __('cms_admin_ui.validation.site_package_zip_mimes'),
            'site_package_zip.max' => __('cms_admin_ui.validation.site_package_zip_max'),
        ];
    }
}
