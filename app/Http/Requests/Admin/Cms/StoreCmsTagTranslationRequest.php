<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsTag;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsTagTranslationRequest extends FormRequest
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
        return [
            'target_locale' => ['required', 'string', Rule::in(app(CmsLanguageSettings::class)->activeLocales())],
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
                $tag = CmsTag::query()->find((int) $this->route('id'), ['id', 'translation_key', 'locale']);
                $targetLocale = $this->string('target_locale')->toString();

                if (! $tag instanceof CmsTag || $targetLocale === '') {
                    return;
                }

                if ((string) $tag->locale === $targetLocale) {
                    $validator->errors()->add('target_locale', 'Deze taal is al de huidige taalvariant.');

                    return;
                }

                if (filled($tag->translation_key) && CmsTag::query()->where('translation_key', $tag->translation_key)->where('locale', $targetLocale)->exists()) {
                    $validator->errors()->add('target_locale', 'Er bestaat al een tagvariant voor deze taal.');
                }
            },
        ];
    }
}
