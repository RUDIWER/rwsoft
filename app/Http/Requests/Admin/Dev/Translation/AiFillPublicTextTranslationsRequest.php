<?php

namespace App\Http\Requests\Admin\Dev\Translation;

use App\Support\Ai\AiProviderSettings;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AiFillPublicTextTranslationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CmsLocalePermission::class)->canEditLocale(
            $this->user(),
            trim((string) $this->input('target_locale', '')),
        );
    }

    public function rules(): array
    {
        $locales = app(CmsLanguageSettings::class)->activeLocales();
        $translationSettings = app(AiProviderSettings::class)->translationSettings();
        $maxLimit = (int) ($translationSettings['fill_limit_max'] ?? config('translation_editor.ai.fill_limit_max', 500));

        if ($maxLimit <= 0) {
            $maxLimit = 500;
        }

        return [
            'target_locale' => ['required', 'string', Rule::in($locales)],
            'source_locale' => ['nullable', 'string', Rule::in($locales)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.$maxLimit],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $targetLocale = trim((string) $this->input('target_locale', ''));
                $sourceLocale = trim((string) $this->input('source_locale', ''));

                if ($targetLocale !== '' && $sourceLocale !== '' && $targetLocale === $sourceLocale) {
                    $validator->errors()->add('target_locale', __('translation_editor_ui.errors.ai_target_matches_source'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $sourceLocale = trim((string) $this->input('source_locale', ''));
        $translationSettings = app(AiProviderSettings::class)->translationSettings();
        $defaultLimit = (int) ($translationSettings['fill_limit_default'] ?? config('translation_editor.ai.fill_limit_default', 100));

        if ($defaultLimit <= 0) {
            $defaultLimit = 100;
        }

        $this->merge([
            'target_locale' => trim((string) $this->input('target_locale', '')),
            'source_locale' => $sourceLocale !== '' ? $sourceLocale : null,
            'limit' => $this->input('limit', $defaultLimit),
        ]);
    }
}
