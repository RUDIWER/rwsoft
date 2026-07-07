<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeAdminRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        $routeName = $request->route()?->getName();

        if (! $user instanceof User) {
            abort(403);
        }

        if (! is_string($routeName) || $routeName === '') {
            abort(403);
        }

        if (in_array($routeName, ['admin', 'admin.locale.update'], true)) {
            return $next($request);
        }

        if (! $user->canAccessRoute($routeName)) {
            if ($request->expectsJson() || $request->wantsJson()) {
                abort(403);
            }

            $redirect = $request->isMethod('GET')
                ? redirect('/admin')
                : redirect()->to($this->safePreviousUrl($request));

            return $redirect->with('error', __('admin_common_ui.errors.unauthorized_route'));
        }

        return $next($request);
    }

    private function safePreviousUrl(Request $request): string
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || trim($referer) === '') {
            return '/admin';
        }

        if (str_starts_with($referer, '/') && ! str_starts_with($referer, '//')) {
            return $referer;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);

        return $refererHost === $request->getHost() ? $referer : '/admin';
    }
}
