<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCmsLanguageRequest extends FormRequest
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
        $languageId = (int) $this->route('id');

        return [
            'locale' => [
                'required',
                'string',
                'max:12',
                'regex:/^[a-z]{2}([_-][A-Z]{2})?$/',
                Rule::unique('cms_languages', 'locale')->ignore($languageId > 0 ? $languageId : null),
            ],
            'name' => ['required', 'string', 'max:255'],
            'native_name' => ['required', 'string', 'max:255'],
            'flag_media_asset_id' => ['nullable', 'integer', Rule::exists('cms_media_assets', 'id')->where('visibility', 'public')],
            'direction' => ['required', 'string', Rule::in(['ltr', 'rtl'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'locale.required' => __('cms_admin_ui.validation.required'),
            'locale.regex' => __('cms_admin_ui.validation.locale_code'),
            'locale.max' => __('cms_admin_ui.validation.max_string'),
            'locale.unique' => __('cms_admin_ui.validation.language_locale_unique'),
            'name.required' => __('cms_admin_ui.validation.required'),
            'name.max' => __('cms_admin_ui.validation.max_string'),
            'native_name.required' => __('cms_admin_ui.validation.required'),
            'native_name.max' => __('cms_admin_ui.validation.max_string'),
            'flag_media_asset_id.exists' => __('cms_admin_ui.validation.invalid_choice'),
            'direction.required' => __('cms_admin_ui.validation.required'),
            'direction.in' => __('cms_admin_ui.validation.language_direction'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'locale' => __('cms_admin_ui.languages.form.locale'),
            'name' => __('cms_admin_ui.languages.columns.name'),
            'native_name' => __('cms_admin_ui.languages.columns.native_name'),
            'flag_media_asset_id' => __('cms_admin_ui.languages.form.flag'),
            'direction' => __('cms_admin_ui.languages.columns.direction'),
        ];
    }
}
