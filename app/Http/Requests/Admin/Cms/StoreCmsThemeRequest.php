<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCmsThemeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9\-]*$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'author' => ['nullable', 'string', 'max:160'],
            'version' => ['required', 'string', 'max:40'],
            'developer_css' => ['nullable', 'string', 'max:400000'],
            'theme_settings' => ['nullable', 'array'],
            'theme_settings.*' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('cms_admin_ui.validation.theme_name_required'),
            'key.required' => __('cms_admin_ui.validation.theme_key_required'),
            'key.regex' => __('cms_admin_ui.validation.theme_key_regex'),
            'developer_css.max' => __('cms_admin_ui.validation.theme_css_too_large'),
        ];
    }
}
