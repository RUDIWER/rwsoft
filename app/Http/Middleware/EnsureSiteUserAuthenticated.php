<?php

namespace App\Http\Middleware;

use App\Models\PublicSite\SiteUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteUserAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $siteUser = $request->user('site_user');

        if (! $siteUser instanceof SiteUser) {
            return redirect()->route('site-user.login');
        }

        if (! $siteUser->isActive()) {
            auth('site_user')->logout();
            $request->session()->regenerateToken();

            return redirect()
                ->route('site-user.login')
                ->with('error', __('public_account.auth.inactive'));
        }

        return $next($request);
    }
}
