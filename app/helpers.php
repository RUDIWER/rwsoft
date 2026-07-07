<?php

use App\Support\PublicSite\CmsPublicTextResolver;

if (! function_exists('public_text')) {
    function public_text(string $key, string $fallback = '', ?string $locale = null): string
    {
        $resolvedLocale = trim((string) ($locale ?: app()->getLocale()));

        return app(CmsPublicTextResolver::class)->get($key, $resolvedLocale, $fallback);
    }
}
