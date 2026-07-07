<?php

namespace App\Http\Middleware;

use App\Jobs\Cms\TrackPublicCmsVisitJob;
use App\Support\PublicSite\CmsVisitorTrackingSettings;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class TrackPublicCmsVisitor
{
    public function __construct(private readonly CmsVisitorTrackingSettings $settings) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldTrack($request, $response)) {
            return $response;
        }

        $uuid = $request->cookies->get(CmsVisitorTrackingSettings::COOKIE_NAME);

        if (! is_string($uuid) || ! Str::isUuid($uuid)) {
            $uuid = (string) Str::uuid();
            $response->headers->setCookie($this->visitorCookie($uuid));
        }

        $isCrawler = $this->isCrawler($request);

        if ($isCrawler && $this->settings->ignoreBots()) {
            return $response;
        }

        TrackPublicCmsVisitJob::dispatch($this->siteId(), [
            'uuid' => $uuid,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => '/'.ltrim($request->path(), '/'),
            'locale' => $request->attributes->get('public_locale'),
            'ref' => $request->query('ref'),
            'referer' => $request->headers->get('referer'),
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'user_agent' => $request->userAgent(),
            'platform' => $request->headers->get('sec-ch-ua-platform'),
            'country_code_header' => $this->settings->countryCodeHeader($request),
            'is_crawler' => $isCrawler,
            'data' => [
                'route' => $request->route()?->getName(),
                'accept_language' => $request->headers->get('accept-language'),
            ],
        ])->afterResponse();

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! TenantContext::isResolved() || ! $this->settings->enabled()) {
            return false;
        }

        if (! $request->isMethod('GET') || $request->expectsJson()) {
            return false;
        }

        if ($response->isRedirection() || $response->getStatusCode() >= 400) {
            return false;
        }

        $path = '/'.ltrim($request->path(), '/');

        if ($this->settings->pathIsExcluded($path)) {
            return false;
        }

        $routeName = (string) $request->route()?->getName();

        return str_starts_with($routeName, 'cms.public.');
    }

    private function isCrawler(Request $request): bool
    {
        return (new CrawlerDetect(null, $request->userAgent()))->isCrawler();
    }

    private function visitorCookie(string $uuid): Cookie
    {
        return Cookie::create(
            name: CmsVisitorTrackingSettings::COOKIE_NAME,
            value: $uuid,
            expire: now()->addDays($this->settings->cookieDays()),
            path: '/',
            secure: request()->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }

    private function siteId(): int
    {
        return (int) TenantContext::siteId();
    }
}
