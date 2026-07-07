<?php

namespace App\Support\PublicSite;

use Illuminate\Http\Request;

class PublicSiteLocaleDetector
{
    public const COOKIE_NAME = 'rw_public_locale';

    public const STRATEGY_BROWSER = 'browser';

    public const STRATEGY_IP = 'ip';

    public const STRATEGY_BROWSER_THEN_IP = 'browser_then_ip';

    public function __construct(private readonly CmsLanguageSettings $languageSettings) {}

    public function preferredLocale(Request $request): string
    {
        $routeLocale = $request->route('locale');

        if ($this->isActiveLocale($routeLocale)) {
            return (string) $routeLocale;
        }

        $cookieLocale = $request->cookie(self::COOKIE_NAME);

        if ($this->languageSettings->autoLocaleRememberChoice() && $this->isActiveLocale($cookieLocale)) {
            return (string) $cookieLocale;
        }

        if (! $this->languageSettings->autoLocaleDetectionEnabled()) {
            return $this->languageSettings->defaultLocale();
        }

        $strategy = $this->languageSettings->autoLocaleDetectionStrategy();

        if (in_array($strategy, [self::STRATEGY_BROWSER, self::STRATEGY_BROWSER_THEN_IP], true)) {
            $browserLocale = $this->browserLocale($request);

            if ($browserLocale !== null) {
                return $browserLocale;
            }
        }

        if (in_array($strategy, [self::STRATEGY_IP, self::STRATEGY_BROWSER_THEN_IP], true)) {
            $countryLocale = $this->countryLocale($request);

            if ($countryLocale !== null) {
                return $countryLocale;
            }
        }

        return $this->languageSettings->defaultLocale();
    }

    public function redirectLocale(Request $request, string $currentLocale): ?string
    {
        if (! $this->languageSettings->multilingualEnabled()) {
            return null;
        }

        if (! $this->languageSettings->autoLocaleRedirectEnabled()) {
            return null;
        }

        if ($this->isActiveLocale($request->route('locale'))) {
            return null;
        }

        $preferredLocale = $this->preferredLocale($request);

        return $preferredLocale !== $currentLocale ? $preferredLocale : null;
    }

    public function shouldRememberRouteLocale(Request $request): bool
    {
        return $this->languageSettings->autoLocaleRememberChoice()
            && $this->isActiveLocale($request->route('locale'));
    }

    public function cookieMinutes(): int
    {
        return $this->languageSettings->autoLocaleCookieDays() * 24 * 60;
    }

    private function browserLocale(Request $request): ?string
    {
        $acceptedLanguages = $request->getLanguages();

        foreach ($acceptedLanguages as $acceptedLanguage) {
            $locale = $this->normalizeLocale((string) $acceptedLanguage);

            if ($this->isActiveLocale($locale)) {
                return $locale;
            }

            $languageCode = explode('_', $locale, 2)[0] ?? '';

            if ($this->isActiveLocale($languageCode)) {
                return $languageCode;
            }
        }

        return null;
    }

    private function countryLocale(Request $request): ?string
    {
        $countryCode = $this->countryCode($request);

        if ($countryCode === null) {
            return null;
        }

        $locale = $this->languageSettings->autoLocaleCountryMap()[$countryCode] ?? null;

        return $this->isActiveLocale($locale) ? (string) $locale : null;
    }

    private function countryCode(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'X-Vercel-IP-Country', 'CloudFront-Viewer-Country', 'X-Country-Code'] as $header) {
            $value = strtoupper(trim((string) $request->headers->get($header, '')));

            if (preg_match('/^[A-Z]{2}$/', $value) === 1 && $value !== 'XX') {
                return $value;
            }
        }

        return null;
    }

    private function normalizeLocale(string $locale): string
    {
        return str_replace('-', '_', trim($locale));
    }

    private function isActiveLocale(mixed $locale): bool
    {
        return in_array($this->normalizeLocale((string) $locale), $this->languageSettings->activeLocales(), true);
    }
}
