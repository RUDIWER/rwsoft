<?php

use App\Actions\PublicSite\RenderCmsErrorPageAction;
use App\Http\Middleware\AttachAuditContext;
use App\Http\Middleware\AuthAdminUsers;
use App\Http\Middleware\AuthorizeAdminRoute;
use App\Http\Middleware\EnsureActiveSiteUserSession;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureSiteMembership;
use App\Http\Middleware\EnsureSiteUserAccountReady;
use App\Http\Middleware\EnsureSiteUserAuthenticated;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfSiteUserAuthenticated;
use App\Http\Middleware\ResolvePublicSiteLocale;
use App\Http\Middleware\ResolveRouteLocale;
use App\Http\Middleware\ResolveTenantSite;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackPublicCmsVisitor;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            AttachAuditContext::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'AuthAdmin' => AuthAdminUsers::class,
            'AdminAcl' => AuthorizeAdminRoute::class,
            '2fa.required' => EnsureTwoFactorIsEnabled::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'site-user.auth' => EnsureSiteUserAuthenticated::class,
            'site-user.guest' => RedirectIfSiteUserAuthenticated::class,
            'site-user.ready' => EnsureSiteUserAccountReady::class,
            'site-user.session' => EnsureActiveSiteUserSession::class,
            'tenant.resolve' => ResolveTenantSite::class,
            'site.member' => EnsureSiteMembership::class,
            'locale.resolve' => ResolveRouteLocale::class,
            'public.locale' => ResolvePublicSiteLocale::class,
            'public.track' => TrackPublicCmsVisitor::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('cms:process-visitor-tracking')
            ->hourly()
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->expectsJson() || $request->is('admin*') || $request->is('platform*') || $request->is('api*')) {
                return null;
            }

            if (! TenantContext::isResolved()) {
                return null;
            }

            $statusCode = match (true) {
                $exception instanceof TokenMismatchException => 419,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };

            if (! in_array($statusCode, [403, 404, 419, 500, 503], true)) {
                return null;
            }

            try {
                return app(RenderCmsErrorPageAction::class)->handle($request, $statusCode, $exception);
            } catch (Throwable) {
                return null;
            }
        });
    })->create();
