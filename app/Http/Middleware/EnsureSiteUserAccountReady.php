<?php

namespace App\Http\Middleware;

use App\Models\PublicSite\SiteUser;
use App\Support\PublicSite\PublicAccountSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteUserAccountReady
{
    public function __construct(private readonly PublicAccountSettings $settings) {}

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

        if ($this->settings->emailVerificationRequired() && ! $siteUser->hasVerifiedEmail()) {
            return redirect()
                ->route('site-user.security')
                ->with('warning', __('public_account.auth.email_verification_required'));
        }

        if ($this->settings->twoFactorRequired() && ! $siteUser->hasEnabledTwoFactorAuthentication()) {
            return redirect()
                ->route('site-user.security')
                ->with('warning', __('public_account.two_factor.required'));
        }

        return $next($request);
    }
}
