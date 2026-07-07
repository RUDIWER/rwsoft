<?php

namespace App\Http\Controllers\PublicSite;

use App\Actions\PublicSite\TrackSiteUserSessionAction;
use App\Http\Controllers\Controller;
use App\Models\PublicSite\SiteUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SiteUserEmailVerificationController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $siteUser = $request->user('site_user');
        abort_unless($siteUser instanceof SiteUser, 403);

        if (! $siteUser->hasVerifiedEmail()) {
            $siteUser->sendEmailVerificationNotification();
        }

        return back()->with('status', __('public_account.auth.verification_sent'));
    }

    public function verify(Request $request, int $id, string $hash, TrackSiteUserSessionAction $sessions): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $siteUser = SiteUser::query()->findOrFail($id);

        abort_unless(hash_equals((string) $hash, sha1($siteUser->getEmailForVerification())), 403);

        if (! $siteUser->hasVerifiedEmail()) {
            $siteUser->markEmailAsVerified();
        }

        if (! auth('site_user')->check()) {
            auth('site_user')->login($siteUser);
            $request->session()->regenerate();
        }

        $sessions->track($request, $siteUser);

        return redirect()
            ->route('site-user.dashboard')
            ->with('status', __('public_account.auth.email_verified'));
    }
}
