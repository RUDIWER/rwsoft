<?php

namespace App\Http\Middleware;

use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\PublicSiteLocaleDetector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ResolvePublicSiteLocale
{
    public function __construct(
        private readonly CmsLanguageSettings $languageSettings,
        private readonly PublicSiteLocaleDetector $localeDetector,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLocale($request));

        $response = $next($request);

        if ($this->localeDetector->shouldRememberRouteLocale($request)) {
            $response->headers->setCookie(cookie(
                PublicSiteLocaleDetector::COOKIE_NAME,
                (string) $request->route('locale'),
                $this->localeDetector->cookieMinutes(),
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'Lax',
            ));
        }

        return $response;
    }

    private function resolveLocale(Request $request): string
    {
        $activeLocales = $this->languageSettings->activeLocales();
        $routeLocale = $request->route('locale');

        if (is_string($routeLocale) && in_array($routeLocale, $activeLocales, true)) {
            return $routeLocale;
        }

        $refererLocale = $this->refererLocale($request);

        if ($refererLocale !== null && in_array($refererLocale, $activeLocales, true)) {
            return $refererLocale;
        }

        return $this->localeDetector->preferredLocale($request);
    }

    private function refererLocale(Request $request): ?string
    {
        $referer = (string) $request->headers->get('referer', '');

        if ($referer === '') {
            return null;
        }

        $path = (string) parse_url($referer, PHP_URL_PATH);
        $firstSegment = trim(explode('/', trim($path, '/'))[0] ?? '');

        return $firstSegment !== '' ? $firstSegment : null;
    }
}
