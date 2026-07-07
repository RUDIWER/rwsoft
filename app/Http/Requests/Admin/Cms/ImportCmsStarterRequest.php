<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportCmsStarterRequest extends FormRequest
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
            'starter_zip' => ['required', 'file', 'mimes:zip', 'max:51200'],
            'activate_theme' => ['nullable', 'boolean'],
            'publish_pages' => ['nullable', 'boolean'],
            'include_code_blocks' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starter_zip.required' => __('cms_admin_ui.validation.starter_zip_required'),
            'starter_zip.mimes' => __('cms_admin_ui.validation.starter_zip_mimes'),
            'starter_zip.max' => __('cms_admin_ui.validation.starter_zip_max'),
        ];
    }
}
