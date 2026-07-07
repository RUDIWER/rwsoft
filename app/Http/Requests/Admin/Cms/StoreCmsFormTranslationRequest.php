<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsForm;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsFormTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CmsLocalePermission::class)->canEditLocale($this->user(), (string) $this->input('target_locale'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $locales = app(CmsLanguageSettings::class)->activeLocales();

        return [
            'target_locale' => ['required', 'string', 'max:12', Rule::in($locales)],
            'use_ai' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $formId = (int) $this->route('id');
                $form = $formId > 0
                    ? CmsForm::query()->find($formId)
                    : null;

                if (! $form instanceof CmsForm) {
                    return;
                }

                $targetLocale = trim((string) $this->input('target_locale', ''));

                if ($targetLocale === '' || $targetLocale === (string) $form->locale) {
                    $validator->errors()->add('target_locale', 'Kies een andere taal dan het huidige formulier.');

                    return;
                }

                $exists = CmsForm::query()
                    ->where('translation_key', $form->translation_key)
                    ->where('locale', $targetLocale)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('target_locale', 'Er bestaat al een vertaling voor deze taal.');
                }
            },
        ];
    }
}
