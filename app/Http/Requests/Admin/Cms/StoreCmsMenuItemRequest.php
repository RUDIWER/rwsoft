<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

class StoreCmsMenuItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return app(CmsLocalePermission::class)->canEditLocale($this->user(), (string) $this->input('locale'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:cms_menu_items,id'],
            'locale' => ['required', 'string', 'max:12'],
            'translation_key' => ['nullable', 'string', 'max:64'],
            'type' => ['required', 'string', 'in:custom,external,page,category,post'],
            'label' => ['nullable', 'string', 'max:160'],
            'cms_page_id' => ['nullable', 'integer', 'required_if:type,page,category', 'exists:cms_pages,id'],
            'cms_post_id' => ['nullable', 'integer', 'required_if:type,post', 'exists:cms_posts,id'],
            'url' => ['nullable', 'string', 'max:2048'],
            'target' => ['nullable', 'string', 'in:_self,_blank'],
            'rel' => ['nullable', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*.label' => ['nullable', 'string', 'max:160'],
            'translations.*.url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $locale = (string) $this->input('locale', '');
                $activeLocales = $this->activeLocales();

                if ($locale === '' || ! $activeLocales->contains($locale)) {
                    $validator->errors()->add('locale', 'Kies een actieve CMS-taal.');

                    return;
                }

                $label = trim((string) $this->input('label', ''));
                $url = trim((string) $this->input('url', ''));

                if ($label === '' && in_array($this->input('type'), ['custom', 'external'], true)) {
                    $validator->errors()->add('label', 'Vul een label in.');
                }

                if (in_array($this->input('type'), ['custom', 'external'], true) && $url === '') {
                    $validator->errors()->add('url', 'Vul een URL in.');
                }

                if ($this->input('type') === 'external' && $url !== '' && preg_match('/^https?:\/\//i', $url) !== 1) {
                    $validator->errors()->add('url', 'Vul een externe URL in die start met http:// of https://.');
                }

                if (in_array($this->input('type'), ['page', 'category'], true) && filled($this->input('cms_page_id'))) {
                    $pageLocale = CmsPage::query()->whereKey($this->integer('cms_page_id'))->value('locale');

                    if ($pageLocale !== null && (string) $pageLocale !== $locale) {
                        $validator->errors()->add('cms_page_id', 'Kies een pagina of categorie in dezelfde taal als het menu-item.');
                    }
                }

                if ($this->input('type') === 'category' && filled($this->input('cms_page_id'))) {
                    $categoryExists = CmsCategory::query()
                        ->where('landing_page_id', $this->integer('cms_page_id'))
                        ->exists();

                    if (! $categoryExists) {
                        $validator->errors()->add('cms_page_id', 'Kies een geldige categoriepagina.');
                    }
                }

                if ($this->input('type') === 'post' && filled($this->input('cms_post_id'))) {
                    $postLocale = CmsPost::query()->whereKey($this->integer('cms_post_id'))->value('locale');

                    if ($postLocale !== null && (string) $postLocale !== $locale) {
                        $validator->errors()->add('cms_post_id', 'Kies een bericht in dezelfde taal als het menu-item.');
                    }
                }
            },
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function activeLocales(): Collection
    {
        return collect(app(CmsLanguageSettings::class)->languages(true))
            ->pluck('locale')
            ->map(fn (mixed $locale): string => (string) $locale)
            ->filter()
            ->values();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'Vul een label in.',
            'type.in' => 'Kies een geldig menu-item type.',
            'cms_page_id.required_if' => 'Kies een pagina of categorie.',
            'cms_post_id.required_if' => 'Kies een bericht.',
            'url.required_if' => 'Vul een URL in.',
        ];
    }
}
