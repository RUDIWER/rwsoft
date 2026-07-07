<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsTemplate;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsTemplateTranslationRequest extends FormRequest
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
                $templateId = (int) $this->route('id');
                $template = $templateId > 0
                    ? CmsTemplate::query()->with('layout:id,translation_key')->find($templateId)
                    : null;

                if (! $template instanceof CmsTemplate) {
                    return;
                }

                $targetLocale = trim((string) $this->input('target_locale', ''));

                if ($targetLocale === '' || $targetLocale === (string) $template->locale) {
                    $validator->errors()->add('target_locale', __('cms_admin_ui.validation.translation_target_same_locale'));

                    return;
                }

                $translationKey = (string) ($template->translation_key ?: $template->getKey());

                $exists = CmsTemplate::query()
                    ->where('translation_key', $translationKey)
                    ->where('locale', $targetLocale)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('target_locale', __('cms_admin_ui.validation.translation_target_exists'));

                    return;
                }

                $hasMatchingLayout = $this->hasMatchingLayout($template, $targetLocale);

                if (! $hasMatchingLayout) {
                    $validator->errors()->add('target_locale', __('cms_admin_ui.validation.template_translation_missing_layout'));
                }
            },
        ];
    }

    private function hasMatchingLayout(CmsTemplate $template, string $targetLocale): bool
    {
        $layoutTranslationKey = $template->layout?->translation_key;

        if (filled($layoutTranslationKey) && CmsLayout::query()
            ->where('translation_key', $layoutTranslationKey)
            ->where('locale', $targetLocale)
            ->where('is_active', true)
            ->exists()) {
            return true;
        }

        return CmsLayout::query()
            ->where('locale', $targetLocale)
            ->where('is_active', true)
            ->exists();
    }
}
