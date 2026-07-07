<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsDocPage;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsDocPageTranslationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $localePermission = app(CmsLocalePermission::class);
        $sourcePage = CmsDocPage::query()->find((int) $this->route('page'));

        if ($sourcePage instanceof CmsDocPage && ! $localePermission->canEditLocale($this->user(), (string) $sourcePage->locale)) {
            return false;
        }

        foreach ($this->targetLocales() as $locale) {
            if (! $localePermission->canEditLocale($this->user(), $locale)) {
                return false;
            }
        }

        return true;
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
            'target_locale' => ['nullable', 'string', 'max:12', Rule::in($locales)],
            'target_locales' => ['nullable', 'array'],
            'target_locales.*' => ['required', 'string', 'max:12', Rule::in($locales)],
            'use_ai' => ['nullable', 'boolean'],
            'source_title' => ['nullable', 'string', 'max:255'],
            'source_body' => ['nullable', 'string', 'max:500000'],
            'source_seo_title' => ['nullable', 'string', 'max:255'],
            'source_seo_description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $sourcePage = CmsDocPage::query()->find((int) $this->route('page'));

                if (! $sourcePage instanceof CmsDocPage) {
                    return;
                }

                $targetLocales = $this->targetLocales();

                if ($targetLocales === []) {
                    $validator->errors()->add('target_locale', __('cms_admin_ui.validation.translation_target_required'));

                    return;
                }

                if (in_array((string) $sourcePage->locale, $targetLocales, true)) {
                    $validator->errors()->add('target_locale', __('cms_admin_ui.validation.translation_target_must_differ'));
                }
            },
        ];
    }

    /**
     * @return array<int, string>
     */
    public function targetLocales(): array
    {
        return collect((array) $this->input('target_locales', []))
            ->push($this->input('target_locale'))
            ->filter(fn (mixed $locale): bool => is_string($locale) && trim($locale) !== '')
            ->map(fn (string $locale): string => trim($locale))
            ->unique()
            ->values()
            ->all();
    }
}
