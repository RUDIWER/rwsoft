<?php

namespace App\Http\Requests\Admin\Cms;

use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCmsContentTranslationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return app(CmsLocalePermission::class)->canEditLocale(
            $this->user(),
            (string) $this->input('target_locale'),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['page', 'post', 'category', 'tag', 'form', 'menu_item'])],
            'source_id' => ['required', 'integer', 'min:1'],
            'target_locale' => ['required', 'string', Rule::in(app(CmsLanguageSettings::class)->activeLocales())],
            'use_ai' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => trim((string) $this->input('type', '')),
            'source_id' => (int) $this->input('source_id', 0),
            'target_locale' => trim((string) $this->input('target_locale', '')),
            'use_ai' => (bool) $this->input('use_ai', false),
        ]);
    }
}
