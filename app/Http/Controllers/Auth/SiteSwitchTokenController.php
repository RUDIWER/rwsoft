<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Platform\SiteSwitchToken;
use App\Models\Platform\SiteUserMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SiteSwitchTokenController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $plainToken = trim((string) $request->query('token'));

        abort_if($plainToken === '', 403);

        $switchToken = SiteSwitchToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->first();

        abort_unless($switchToken instanceof SiteSwitchToken, 403);
        abort_unless(TenantContext::siteId() === $switchToken->site_id, 403);

        $user = $switchToken->user;

        abort_unless($user instanceof User, 403);

        $membership = SiteUserMembership::query()
            ->where('site_id', $switchToken->site_id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        abort_unless($membership instanceof SiteUserMembership, 403);

        $switchToken->forceFill([
            'used_at' => now(),
        ])->save();

        $membership->forceFill([
            'last_accessed_at' => now(),
        ])->save();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended('/admin');
    }
}
