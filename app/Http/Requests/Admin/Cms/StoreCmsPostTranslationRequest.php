<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsPost;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsPostTranslationRequest extends FormRequest
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
                $postId = (int) $this->route('id');
                $post = $postId > 0
                    ? CmsPost::query()->find($postId)
                    : null;

                if (! $post instanceof CmsPost) {
                    return;
                }

                $targetLocale = trim((string) $this->input('target_locale', ''));

                if ($targetLocale === '' || $targetLocale === (string) $post->locale) {
                    $validator->errors()->add('target_locale', 'Kies een andere taal dan het huidige bericht.');

                    return;
                }

                $translationKey = (string) ($post->translation_key ?: $post->getKey());

                $exists = CmsPost::query()
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
