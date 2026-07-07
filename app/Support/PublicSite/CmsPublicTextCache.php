<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsPublicTextTranslation;
use App\Models\Cms\CmsSetting;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CmsPublicTextCache
{
    public function __construct(private readonly CmsLanguageSettings $languageSettings) {}

    /**
     * @return array<string, string>
     */
    public function remember(string $locale, Closure $callback): array
    {
        if (! $this->enabled()) {
            return (array) $callback();
        }

        $ttl = $this->ttlSeconds();

        if ($ttl <= 0) {
            return (array) Cache::rememberForever($this->key($locale), $callback);
        }

        return (array) Cache::remember($this->key($locale), $ttl, $callback);
    }

    public function flush(?string $locale = null): void
    {
        if ($locale !== null && trim($locale) !== '') {
            Cache::forget($this->key($locale));

            return;
        }

        foreach ($this->knownLocales() as $knownLocale) {
            Cache::forget($this->key($knownLocale));
        }
    }

    private function enabled(): bool
    {
        return (bool) $this->settingValue('performance', 'public_text_cache_enabled', true);
    }

    private function ttlSeconds(): int
    {
        return max(0, (int) $this->settingValue('performance', 'public_text_cache_ttl', 3600));
    }

    private function key(string $locale): string
    {
        $siteKey = TenantContext::siteId() ?: TenantContext::database() ?: 'global';

        return 'cms_public_texts:'.$siteKey.':'.trim($locale);
    }

    /**
     * @return array<int, string>
     */
    private function knownLocales(): array
    {
        $locales = collect($this->languageSettings->languages(false))
            ->pluck('locale')
            ->merge((array) config('app.available_locales', []));

        if (Schema::hasTable('cms_public_text_translations')) {
            $locales = $locales->merge(
                CmsPublicTextTranslation::query()->distinct()->pluck('locale')
            );
        }

        return $locales
            ->map(static fn (mixed $locale): string => trim((string) $locale))
            ->filter(static fn (string $locale): bool => $locale !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function settingValue(string $group, string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('cms_settings')) {
            return $default;
        }

        $setting = CmsSetting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if (! $setting instanceof CmsSetting) {
            return $default;
        }

        return $setting->value['value'] ?? $default;
    }
}
