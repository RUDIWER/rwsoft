<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsEmail;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsEmailTranslationRequest extends FormRequest
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
        return [
            'target_locale' => ['required', 'string', 'max:12', Rule::in(app(CmsLanguageSettings::class)->activeLocales())],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $email = CmsEmail::query()->find((int) $this->route('id'));

                if (! $email instanceof CmsEmail) {
                    return;
                }

                $targetLocale = trim((string) $this->input('target_locale', ''));

                if ($targetLocale === '' || $targetLocale === (string) $email->locale) {
                    $validator->errors()->add('target_locale', __('cms_admin_ui.validation.translation_target_locale_different'));

                    return;
                }

                if ($this->emailTranslationExists($email, $targetLocale)) {
                    $validator->errors()->add('target_locale', __('cms_admin_ui.validation.translation_target_locale_exists'));
                }
            },
        ];
    }

    private function emailTranslationExists(CmsEmail $email, string $targetLocale): bool
    {
        if ($email->email_type === 'system' && filled($email->system_key)) {
            return CmsEmail::query()
                ->where('email_type', 'system')
                ->where('system_key', $email->system_key)
                ->where('locale', $targetLocale)
                ->exists();
        }

        if (blank($email->translation_key)) {
            return false;
        }

        return CmsEmail::query()
            ->where('translation_key', $email->translation_key)
            ->where('locale', $targetLocale)
            ->exists();
    }
}
