<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsLayout;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsLayoutTranslationRequest extends FormRequest
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
                $layoutId = (int) $this->route('id');
                $layout = $layoutId > 0
                    ? CmsLayout::query()->find($layoutId)
                    : null;

                if (! $layout instanceof CmsLayout) {
                    return;
                }

                $targetLocale = trim((string) $this->input('target_locale', ''));

                if ($targetLocale === '' || $targetLocale === (string) $layout->locale) {
                    $validator->errors()->add('target_locale', __('cms_admin_ui.validation.translation_target_same_locale'));

                    return;
                }

                $translationKey = (string) ($layout->translation_key ?: $layout->getKey());

                $exists = CmsLayout::query()
                    ->where('translation_key', $translationKey)
                    ->where('locale', $targetLocale)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('target_locale', __('cms_admin_ui.validation.translation_target_exists'));
                }
            },
        ];
    }
}
