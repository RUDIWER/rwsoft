<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsLanguage;
use App\Models\Cms\CmsSetting;
use Illuminate\Support\Facades\Schema;

class CmsLanguageSettings
{
    public function __construct(private readonly PublicMediaUrl $mediaUrl) {}

    /**
     * @return array<int, array{locale: string, name: string, native_name: string, direction: string, is_active: bool, sort_order: int, flag: array<string, mixed>|null}>
     */
    public function languages(bool $activeOnly = false): array
    {
        if (Schema::hasTable('cms_languages')) {
            $query = CmsLanguage::query()
                ->with('flagMediaAsset')
                ->orderBy('sort_order')
                ->orderBy('locale');

            if ($activeOnly) {
                $query->where('is_active', true);
            }

            $languages = $query->get(['locale', 'name', 'native_name', 'flag_media_asset_id', 'direction', 'is_active', 'sort_order']);

            if ($languages->isNotEmpty()) {
                return $languages
                    ->map(fn (CmsLanguage $language): array => [
                        'locale' => (string) $language->locale,
                        'name' => (string) $language->name,
                        'native_name' => (string) $language->native_name,
                        'flag' => $this->mediaUrl->payload($language->flagMediaAsset, (string) $language->locale),
                        'direction' => (string) ($language->direction ?: 'ltr'),
                        'is_active' => (bool) $language->is_active,
                        'sort_order' => (int) $language->sort_order,
                    ])
                    ->values()
                    ->all();
            }
        }

        return collect((array) config('app.available_locales', [config('app.locale', 'en')]))
            ->map(fn (string $locale, int $index): array => [
                'locale' => $locale,
                'name' => strtoupper($locale),
                'native_name' => strtoupper($locale),
                'flag' => null,
                'direction' => 'ltr',
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function activeLocales(): array
    {
        return collect($this->languages(true))
            ->pluck('locale')
            ->values()
            ->all();
    }

    public function multilingualEnabled(): bool
    {
        return (bool) $this->settingValue('general', 'multilingual_enabled', true);
    }

    public function defaultLocale(): string
    {
        $configured = (string) $this->settingValue('general', 'default_locale', config('app.locale', 'nl'));

        if (in_array($configured, $this->activeLocales(), true)) {
            return $configured;
        }

        return $this->activeLocales()[0] ?? (string) config('app.locale', 'nl');
    }

    public function pathPrefix(?string $locale): string
    {
        if (! $this->multilingualEnabled()) {
            return '';
        }

        $locale = trim((string) $locale);

        return $locale !== '' ? '/'.$locale : '';
    }

    public function autoLocaleDetectionEnabled(): bool
    {
        return (bool) $this->settingValue('localization', 'auto_locale_detection_enabled', false);
    }

    public function autoLocaleDetectionStrategy(): string
    {
        $strategy = (string) $this->settingValue('localization', 'auto_locale_detection_strategy', PublicSiteLocaleDetector::STRATEGY_BROWSER_THEN_IP);

        return in_array($strategy, [PublicSiteLocaleDetector::STRATEGY_BROWSER, PublicSiteLocaleDetector::STRATEGY_IP, PublicSiteLocaleDetector::STRATEGY_BROWSER_THEN_IP], true)
            ? $strategy
            : PublicSiteLocaleDetector::STRATEGY_BROWSER_THEN_IP;
    }

    public function autoLocaleRedirectEnabled(): bool
    {
        return (bool) $this->settingValue('localization', 'auto_locale_redirect_enabled', true);
    }

    public function autoLocaleRememberChoice(): bool
    {
        return (bool) $this->settingValue('localization', 'auto_locale_remember_choice', true);
    }

    public function autoLocaleCookieDays(): int
    {
        return max(1, min(730, (int) $this->settingValue('localization', 'auto_locale_cookie_days', 180)));
    }

    /**
     * @return array<string, string>
     */
    public function autoLocaleCountryMap(): array
    {
        $map = [];
        $value = (string) $this->settingValue('localization', 'auto_locale_country_map', '');

        foreach (preg_split('/\R/u', $value) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$country, $locale] = array_pad(preg_split('/\s*[=:]\s*/', $line, 2) ?: [], 2, '');
            $country = strtoupper(trim((string) $country));
            $locale = str_replace('-', '_', trim((string) $locale));

            if (preg_match('/^[A-Z]{2}$/', $country) === 1 && in_array($locale, $this->activeLocales(), true)) {
                $map[$country] = $locale;
            }
        }

        return $map;
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
