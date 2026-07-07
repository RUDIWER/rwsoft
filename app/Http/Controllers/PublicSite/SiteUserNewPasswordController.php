<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\SiteUserResetPasswordRequest;
use App\Models\PublicSite\SiteUser;
use App\Models\PublicSite\SiteUserSession;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SiteUserNewPasswordController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function store(SiteUserResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::broker('site_users')->reset(
            $request->validated(),
            function (SiteUser $siteUser, string $password): void {
                $siteUser->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                SiteUserSession::query()
                    ->where('site_user_id', $siteUser->id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);

                event(new PasswordReset($siteUser));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        return redirect()
            ->route('site-user.login')
            ->with('status', __('public_account.auth.password_reset'));
    }
}
