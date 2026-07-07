<?php

namespace App\Support\PublicSite;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CmsCanonicalUrlPolicy
{
    public function __construct(private readonly CmsLanguageSettings $languageSettings) {}

    public function isValid(?string $canonicalUrl, ?string $locale, Request $request): bool
    {
        $canonicalUrl = trim((string) $canonicalUrl);

        if ($canonicalUrl === '') {
            return true;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $canonicalUrl) || str_contains($canonicalUrl, '\\')) {
            return false;
        }

        if (Str::startsWith($canonicalUrl, '//')) {
            return false;
        }

        if (Str::startsWith($canonicalUrl, '/')) {
            $path = parse_url($canonicalUrl, PHP_URL_PATH);

            return is_string($path) && $this->pathMatchesLocale($path, $locale);
        }

        $parts = parse_url($canonicalUrl);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if ($this->httpHost($parts) !== strtolower($request->getHttpHost())) {
            return false;
        }

        $path = (string) ($parts['path'] ?? '/');

        return $this->pathMatchesLocale($path, $locale);
    }

    public function toAbsoluteUrl(?string $canonicalUrl, string $fallbackUrl, ?string $locale, Request $request): string
    {
        $canonicalUrl = trim((string) $canonicalUrl);

        if ($canonicalUrl === '' || ! $this->isValid($canonicalUrl, $locale, $request)) {
            return $fallbackUrl;
        }

        if (Str::startsWith($canonicalUrl, '/')) {
            return $request->getSchemeAndHttpHost().$canonicalUrl;
        }

        return $canonicalUrl;
    }

    private function pathMatchesLocale(string $path, ?string $locale): bool
    {
        $locale = trim((string) $locale);

        if ($locale === '') {
            return false;
        }

        $activeLocales = $this->languageSettings->activeLocales();
        $defaultLocale = $this->languageSettings->defaultLocale();
        $firstSegment = $this->firstPathSegment($path);

        if (! $this->languageSettings->multilingualEnabled()) {
            return ! in_array($firstSegment, $activeLocales, true);
        }

        if ($locale !== $defaultLocale) {
            return $firstSegment === $locale;
        }

        $otherLocales = array_values(array_diff($activeLocales, [$defaultLocale]));

        return $firstSegment === ''
            || $firstSegment === $defaultLocale
            || ! in_array($firstSegment, $otherLocales, true);
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function httpHost(array $parts): string
    {
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = $parts['port'] ?? null;

        return is_int($port) ? $host.':'.$port : $host;
    }

    private function firstPathSegment(string $path): string
    {
        $segments = explode('/', trim($path, '/'));

        return rawurldecode((string) ($segments[0] ?? ''));
    }
}
