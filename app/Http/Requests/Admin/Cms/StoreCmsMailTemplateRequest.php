<?php

namespace App\Http\Requests\Admin\Cms;

use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCmsMailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CmsLocalePermission::class)->canEditLocale($this->user(), (string) config('app.locale', 'en'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'context_key' => ['required', 'string', 'max:96', Rule::in(['public_site.auth_email', 'cms.form_submission.email'])],
            'body_blocks' => ['nullable', 'array'],
            'body_blocks.*.key' => ['required_with:body_blocks', 'string', 'max:80', 'regex:/^[a-z0-9_-]+$/'],
            'body_blocks.*.type' => ['required_with:body_blocks', 'string', Rule::in(['heading', 'text', 'button', 'divider', 'spacer', 'form_answers'])],
            'body_blocks.*.label' => ['required_with:body_blocks', 'string', 'max:120'],
            'body_blocks.*.required' => ['nullable', 'boolean'],
            'body_blocks.*.url_source' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'sections' => ['nullable', 'array'],
            'sections.content' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('cms_admin_ui.validation.required'),
            'context_key.required' => __('cms_admin_ui.validation.required'),
            'context_key.in' => __('cms_admin_ui.validation.invalid_choice'),
            'body_blocks.*.key.required_with' => __('cms_admin_ui.validation.required'),
            'body_blocks.*.key.regex' => __('cms_admin_ui.validation.alpha_dash_ascii'),
            'body_blocks.*.type.required_with' => __('cms_admin_ui.validation.required'),
            'body_blocks.*.type.in' => __('cms_admin_ui.validation.invalid_choice'),
            'body_blocks.*.label.required_with' => __('cms_admin_ui.validation.required'),
        ];
    }
}
