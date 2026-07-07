<?php

namespace App\Http\Requests\Admin\Dev\Translation;

use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePublicTextTranslationValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CmsLocalePermission::class)->canEditLocale(
            $this->user(),
            trim((string) $this->input('locale', '')),
        );
    }

    public function rules(): array
    {
        return [
            'field' => ['required', 'string', 'max:120'],
            'locale' => ['required', 'string', Rule::in(app(CmsLanguageSettings::class)->activeLocales())],
            'value' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $field = trim((string) $this->input('field', ''));
        $locale = trim((string) $this->input('locale', ''));

        if ($locale === '' && str_starts_with($field, 'value_')) {
            $locale = trim(substr($field, 6));
        }

        $this->merge([
            'field' => $field,
            'locale' => $locale,
        ]);
    }
}
