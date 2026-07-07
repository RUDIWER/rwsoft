<?php

namespace App\Http\Controllers\PublicSite;

use App\Actions\PublicSite\TrackSiteUserSessionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\SiteUserTwoFactorChallengeRequest;
use App\Models\PublicSite\SiteUser;
use App\Support\PublicSite\PublicAccountSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

class SiteUserTwoFactorController extends Controller
{
    public function __construct(
        private readonly PublicAccountSettings $settings,
        private readonly TwoFactorAuthenticationProvider $provider,
    ) {}

    public function enable(Request $request): RedirectResponse
    {
        abort_unless($this->settings->twoFactorEnabled(), 404);

        $siteUser = $request->user('site_user');
        abort_unless($siteUser instanceof SiteUser, 403);

        $siteUser->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($this->provider->generateSecretKey()),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($this->generateRecoveryCodes(), JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('status', __('public_account.two_factor.enabled'));
    }

    public function confirm(Request $request): RedirectResponse
    {
        abort_unless($this->settings->twoFactorEnabled(), 404);

        $request->validate(['code' => ['required', 'string']]);

        $siteUser = $request->user('site_user');
        abort_unless($siteUser instanceof SiteUser && $siteUser->two_factor_secret !== null, 403);

        if (! $this->validCode($siteUser, (string) $request->input('code'))) {
            return back()->withErrors(['code' => __('public_account.two_factor.invalid_code')]);
        }

        $siteUser->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        return back()->with('status', __('public_account.two_factor.confirmed'));
    }

    public function disable(Request $request): RedirectResponse
    {
        $siteUser = $request->user('site_user');
        abort_unless($siteUser instanceof SiteUser, 403);

        $siteUser->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('status', __('public_account.two_factor.disabled'));
    }

    public function qrCode(Request $request): JsonResponse
    {
        $siteUser = $request->user('site_user');
        abort_unless($siteUser instanceof SiteUser && $siteUser->two_factor_secret !== null, 404);

        return response()->json(['svg' => $siteUser->twoFactorQrCodeSvg()]);
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $siteUser = $request->user('site_user');
        abort_unless($siteUser instanceof SiteUser && $siteUser->two_factor_recovery_codes !== null, 404);

        return response()->json(['codes' => $siteUser->recoveryCodes()]);
    }

    public function challenge(SiteUserTwoFactorChallengeRequest $request, TrackSiteUserSessionAction $sessions): RedirectResponse
    {
        $siteUserId = (int) $request->session()->get('site_user.login.id');
        abort_unless($siteUserId > 0, 403);

        $siteUser = SiteUser::query()->findOrFail($siteUserId);

        if (! $siteUser->isActive()) {
            $request->session()->forget(['site_user.login.id', 'site_user.login.remember']);

            return redirect()
                ->route('site-user.login')
                ->with('error', __('public_account.auth.inactive'));
        }

        $code = trim((string) $request->input('code'));
        $recoveryCode = trim((string) $request->input('recovery_code'));

        $valid = $code !== ''
            ? $this->validCode($siteUser, $code)
            : $this->validRecoveryCode($siteUser, $recoveryCode);

        if (! $valid) {
            return back()->withErrors(['code' => __('public_account.two_factor.invalid_code')]);
        }

        auth('site_user')->login($siteUser, (bool) $request->session()->get('site_user.login.remember', false));
        $request->session()->forget(['site_user.login.id', 'site_user.login.remember']);
        $request->session()->regenerate();
        $sessions->track($request, $siteUser);

        $siteUser->forceFill([
            'last_login_at' => now(),
            'last_login_ip_hash' => hash('sha256', (string) $request->ip()),
        ])->save();

        return redirect()->intended(route('site-user.dashboard', absolute: false));
    }

    private function validCode(SiteUser $siteUser, string $code): bool
    {
        if ($siteUser->two_factor_secret === null) {
            return false;
        }

        return $this->provider->verify(
            Fortify::currentEncrypter()->decrypt($siteUser->two_factor_secret),
            $code,
        );
    }

    private function validRecoveryCode(SiteUser $siteUser, string $code): bool
    {
        if ($code === '' || $siteUser->two_factor_recovery_codes === null) {
            return false;
        }

        $codes = $siteUser->recoveryCodes();

        if (! in_array($code, $codes, true)) {
            return false;
        }

        $siteUser->replaceRecoveryCode($code);

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn (): string => RecoveryCode::generate())
            ->all();
    }
}
