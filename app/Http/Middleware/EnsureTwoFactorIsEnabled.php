<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = (string) $request->route()?->getName();

        $hasTwoFactorEnabled = $user
            && filled($user->two_factor_secret)
            && filled($user->two_factor_confirmed_at);

        if ($user && ! $hasTwoFactorEnabled) {
            $allowedRoutes = [
                '2fa.setup',
                'logout',
                'profile.edit',
                'profile.update',
                'password.confirm',
                'password.update',
                'two-factor.login',
            ];

            if (! in_array($routeName, $allowedRoutes, true) && ! Str::startsWith($routeName, 'two-factor.')) {
                return redirect()
                    ->route('2fa.setup')
                    ->with('status', 'Je moet eerst two-factor authenticatie inschakelen.');
            }
        }

        return $next($request);
    }
}
