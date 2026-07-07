<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsPage;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsPageTranslationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return app(CmsLocalePermission::class)->canEditLocale($this->user(), (string) $this->input('target_locale'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
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
                $pageId = (int) $this->route('id');
                $page = $pageId > 0
                    ? CmsPage::query()->find($pageId)
                    : null;

                if (! $page instanceof CmsPage) {
                    return;
                }

                $targetLocale = trim((string) $this->input('target_locale', ''));

                if ($targetLocale === '' || $targetLocale === (string) $page->locale) {
                    $validator->errors()->add('target_locale', 'Kies een andere taal dan de huidige pagina.');

                    return;
                }

                $translationKey = (string) ($page->translation_key ?: $page->getKey());

                $exists = CmsPage::query()
                    ->where('translation_key', $translationKey)
                    ->where('locale', $targetLocale)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('target_locale', 'Er bestaat al een vertaling voor deze taal.');
                }
            },
        ];
    }
}
