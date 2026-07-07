<?php

namespace App\Actions\Admin\Base;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ResolveLocalizedHelpContentAction
{
    public static function handle(string $key, ?string $locale = null): string
    {
        $normalizedKey = self::normalizeKey($key);
        if ($normalizedKey === '') {
            return '';
        }

        foreach (self::resolveLocales($locale) as $candidateLocale) {
            $path = resource_path("help/{$candidateLocale}/{$normalizedKey}.md");

            if (! File::exists($path)) {
                continue;
            }

            $markdown = trim((string) File::get($path));
            if ($markdown === '') {
                continue;
            }

            return (string) Str::markdown($markdown, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private static function resolveLocales(?string $locale): array
    {
        $requestedLocale = self::normalizeLocale($locale);
        $fallbackLocale = self::normalizeLocale((string) config('app.fallback_locale', 'en'));

        return array_values(array_unique(array_filter([
            $requestedLocale,
            $fallbackLocale,
            'en',
        ])));
    }

    private static function normalizeLocale(?string $locale): string
    {
        $normalized = strtolower(trim((string) $locale));
        if ($normalized === '') {
            return '';
        }

        return (string) preg_replace('/[^a-z]/', '', explode('_', explode('-', $normalized)[0])[0]);
    }

    private static function normalizeKey(string $key): string
    {
        $normalized = trim(str_replace('\\', '/', $key), '/');
        if ($normalized === '') {
            return '';
        }

        if (! preg_match('/^[a-zA-Z0-9_\/-]+$/', $normalized)) {
            return '';
        }

        return $normalized;
    }
}
