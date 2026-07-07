<?php

namespace App\Http\Requests\Admin\Dev\Translation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTranslationValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $locales = (array) config('app.available_locales', [config('app.locale', 'en')]);

        return [
            'field' => ['required', 'string', 'max:120'],
            'locale' => ['required', 'string', Rule::in($locales)],
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
