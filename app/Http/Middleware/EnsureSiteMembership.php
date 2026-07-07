<?php

namespace App\Http\Middleware;

use App\Models\Platform\SiteUserMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $siteId = TenantContext::siteId();

        if (! $user instanceof User) {
            return redirect()
                ->route('login')
                ->with('error', 'Je moet eerst aanmelden voordat je deze adminomgeving kan openen.');
        }

        if (! $siteId) {
            return redirect()
                ->route('site-switcher.index')
                ->with('warning', 'Er is geen actieve tenant-site gevonden voor deze adminomgeving. Kies eerst een site of laat het domein koppelen in platformbeheer.');
        }

        $hasMembership = SiteUserMembership::query()
            ->where('site_id', $siteId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $hasMembership) {
            return redirect()
                ->route('site-switcher.index')
                ->with('warning', 'Je account is niet gekoppeld aan deze site. Vraag een platformbeheerder om je een actieve site-membership te geven.');
        }

        return $next($request);
    }
}
