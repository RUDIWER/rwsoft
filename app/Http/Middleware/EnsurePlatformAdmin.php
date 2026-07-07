<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()
                ->route('login')
                ->with('error', 'Je moet eerst aanmelden voordat je platformbeheer kan openen.');
        }

        if (! $user->is_platform_admin) {
            return redirect()
                ->route('site-switcher.index')
                ->with('warning', 'Je account heeft geen platformbeheerrechten. Vraag een platformbeheerder om je account te promoveren als je sites moet beheren.');
        }

        return $next($request);
    }
}
