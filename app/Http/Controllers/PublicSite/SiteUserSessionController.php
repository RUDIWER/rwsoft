<?php

namespace App\Http\Controllers\PublicSite;

use App\Actions\PublicSite\TrackSiteUserSessionAction;
use App\Http\Controllers\Controller;
use App\Models\PublicSite\SiteUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SiteUserSessionController extends Controller
{
    public function destroyOtherDevices(Request $request, TrackSiteUserSessionAction $sessions): RedirectResponse
    {
        $siteUser = $request->user('site_user');
        abort_unless($siteUser instanceof SiteUser, 403);

        $revoked = $sessions->revokeOtherSessions($request, $siteUser);

        return redirect()
            ->route('site-user.security')
            ->with('status', trans_choice('public_account.sessions.other_devices_signed_out', $revoked, ['count' => $revoked]));
    }
}
