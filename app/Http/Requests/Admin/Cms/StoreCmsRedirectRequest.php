<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCmsRedirectRequest extends FormRequest
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
            'source_path' => [
                'required',
                'string',
                'max:2048',
                'regex:/^\/[^\s]*$/',
                Rule::unique('cms_redirects')
                    ->where(fn ($query) => $query->where('locale', $this->input('locale')))
                    ->ignore((int) $this->route('id')),
            ],
            'target_url' => ['required', 'string', 'max:2048', 'regex:/^(\/[^\s]*|https?:\/\/[^\s]+)$/i'],
            'status_code' => ['required', 'integer', 'in:301,302,307,308'],
            'locale' => ['nullable', 'string', 'max:12', 'regex:/^[a-z]{2}([_-][A-Z]{2})?$/'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_path.required' => __('cms_admin_ui.validation.required'),
            'source_path.regex' => __('cms_admin_ui.validation.redirect_source_path'),
            'source_path.max' => __('cms_admin_ui.validation.max_string'),
            'source_path.unique' => __('cms_admin_ui.validation.redirect_source_unique'),
            'target_url.required' => __('cms_admin_ui.validation.required'),
            'target_url.regex' => __('cms_admin_ui.validation.redirect_target_url'),
            'target_url.max' => __('cms_admin_ui.validation.max_string'),
            'status_code.required' => __('cms_admin_ui.validation.required'),
            'status_code.integer' => __('cms_admin_ui.validation.redirect_status_code'),
            'status_code.in' => __('cms_admin_ui.validation.redirect_status_code'),
            'locale.regex' => __('cms_admin_ui.validation.locale_code'),
            'locale.max' => __('cms_admin_ui.validation.max_string'),
            'starts_at.date' => __('cms_admin_ui.validation.date'),
            'ends_at.date' => __('cms_admin_ui.validation.date'),
            'ends_at.after_or_equal' => __('cms_admin_ui.validation.redirect_end_after_start'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'source_path' => __('cms_admin_ui.redirects.form.source_path'),
            'target_url' => __('cms_admin_ui.redirects.form.target_url'),
            'status_code' => __('cms_admin_ui.redirects.form.status_code'),
            'locale' => __('cms_admin_ui.common.columns.locale'),
            'starts_at' => __('cms_admin_ui.redirects.form.starts_at'),
            'ends_at' => __('cms_admin_ui.redirects.form.ends_at'),
        ];
    }
}
