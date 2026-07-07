<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfSiteUserAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('site_user')->check()) {
            return redirect()->route('site-user.dashboard');
        }

        return $next($request);
    }
}
