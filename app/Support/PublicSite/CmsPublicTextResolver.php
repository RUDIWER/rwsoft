<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsPublicText;
use Illuminate\Support\Facades\Schema;

class CmsPublicTextResolver
{
    public function __construct(
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsPublicTextCache $publicTextCache,
    ) {}

    public function get(string $path, string $locale, ?string $fallback = null): string
    {
        return (string) ($this->all($locale)[$path] ?? $fallback ?? '');
    }

    /**
     * @return array<string, string>
     */
    public function all(string $locale): array
    {
        return $this->publicTextCache->remember($locale, function () use ($locale): array {
            return $this->resolveAll($locale);
        });
    }

    /**
     * @return array<string, string>
     */
    private function resolveAll(string $locale): array
    {
        if (! Schema::hasTable('cms_public_texts') || ! Schema::hasTable('cms_public_text_translations')) {
            return [];
        }

        $defaultLocale = $this->languageSettings->defaultLocale();

        return CmsPublicText::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->mapWithKeys(function (CmsPublicText $text) use ($locale, $defaultLocale): array {
                $translation = $text->translations->firstWhere('locale', $locale)
                    ?? $text->translations->firstWhere('locale', $defaultLocale);
                $value = trim((string) ($translation?->value ?? ''));

                if ($value === '') {
                    $value = trim((string) $text->default_value);
                }

                return [$text->group.'.'.$text->key => $value];
            })
            ->all();
    }
}
