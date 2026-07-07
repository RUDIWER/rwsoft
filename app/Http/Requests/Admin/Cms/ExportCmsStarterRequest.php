<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportCmsStarterRequest extends FormRequest
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
            'starter_key' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'starter_name' => ['nullable', 'string', 'max:120'],
            'layout_id' => ['required', 'integer', Rule::exists('cms_layouts', 'id')],
            'template_id' => ['required', 'integer', Rule::exists('cms_templates', 'id')],
            'page_id' => ['required', 'integer', Rule::exists('cms_pages', 'id')],
            'menu_id' => ['required', 'integer', Rule::exists('cms_menus', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starter_key.regex' => __('cms_admin_ui.validation.starter_export_key_format'),
        ];
    }
}
