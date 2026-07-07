<?php

namespace App\Http\Controllers\PublicSite;

use App\Actions\PublicSite\TrackSiteUserSessionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\SiteUserLoginRequest;
use App\Models\PublicSite\SiteUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class SiteUserAuthenticatedSessionController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function store(SiteUserLoginRequest $request, TrackSiteUserSessionAction $sessions): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $siteUser = SiteUser::query()
            ->where('email', $request->string('email')->lower()->toString())
            ->first();

        if (! $siteUser instanceof SiteUser || ! Hash::check((string) $request->input('password'), (string) $siteUser->password)) {
            RateLimiter::hit($request->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('public_account.auth.failed'),
            ]);
        }

        if (! $siteUser->isActive()) {
            RateLimiter::hit($request->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('public_account.auth.inactive'),
            ]);
        }

        RateLimiter::clear($request->throttleKey());

        if ($siteUser->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put('site_user.login.id', $siteUser->id);
            $request->session()->put('site_user.login.remember', $request->boolean('remember'));

            return redirect()->route('site-user.two-factor.challenge');
        }

        auth('site_user')->login($siteUser, $request->boolean('remember'));
        $request->session()->regenerate();
        $sessions->track($request, $siteUser);
        $this->markLogin($request, $siteUser);

        return redirect()->intended(route('site-user.dashboard', absolute: false));
    }

    public function destroy(Request $request, TrackSiteUserSessionAction $sessions): RedirectResponse
    {
        $sessions->revokeCurrent($request);
        auth('site_user')->logout();

        $request->session()->forget(['site_user.login.id', 'site_user.login.remember', TrackSiteUserSessionAction::SESSION_TOKEN_KEY]);
        $request->session()->regenerateToken();

        return redirect()
            ->route('site-user.login')
            ->with('status', __('public_account.auth.signed_out'));
    }

    private function markLogin(Request $request, SiteUser $siteUser): void
    {
        $siteUser->forceFill([
            'last_login_at' => now(),
            'last_login_ip_hash' => hash('sha256', (string) $request->ip()),
        ])->save();
    }
}
