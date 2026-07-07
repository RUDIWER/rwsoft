<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsCategory;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsCategoryTranslationRequest extends FormRequest
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
                $category = CmsCategory::query()->find((int) $this->route('id'), ['id', 'translation_key', 'locale']);
                $targetLocale = $this->string('target_locale')->toString();

                if (! $category instanceof CmsCategory || $targetLocale === '') {
                    return;
                }

                if ((string) $category->locale === $targetLocale) {
                    $validator->errors()->add('target_locale', 'Deze taal is al de huidige taalvariant.');

                    return;
                }

                if (filled($category->translation_key) && CmsCategory::query()->where('translation_key', $category->translation_key)->where('locale', $targetLocale)->exists()) {
                    $validator->errors()->add('target_locale', 'Er bestaat al een categorievariant voor deze taal.');
                }
            },
        ];
    }
}
