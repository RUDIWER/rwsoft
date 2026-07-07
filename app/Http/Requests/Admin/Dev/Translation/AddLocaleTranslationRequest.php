<?php

namespace App\Http\Requests\Admin\Dev\Translation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddLocaleTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $locales = (array) config('app.available_locales', [config('app.locale', 'en')]);

        return [
            'locale' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2}(?:_[A-Z]{2})?$/'],
            'source_locale' => ['nullable', 'string', Rule::in($locales)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalizedSourceLocale = $this->normalizeLocaleCode((string) $this->input('source_locale', ''));

        $this->merge([
            'locale' => $this->normalizeLocaleCode((string) $this->input('locale', '')),
            'source_locale' => $normalizedSourceLocale !== '' ? $normalizedSourceLocale : null,
        ]);
    }

    private function normalizeLocaleCode(string $value): string
    {
        $normalized = trim(str_replace('-', '_', $value));

        if ($normalized === '') {
            return '';
        }

        $parts = explode('_', $normalized, 2);
        $languagePart = strtolower(trim((string) ($parts[0] ?? '')));
        $countryPart = strtoupper(trim((string) ($parts[1] ?? '')));

        if ($languagePart === '') {
            return '';
        }

        if ($countryPart === '') {
            return $languagePart;
        }

        return $languagePart.'_'.$countryPart;
    }
}
