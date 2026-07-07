<?php

namespace App\Http\Controllers\PublicSite;

use App\Actions\PublicSite\StoreSiteUserProfileFieldValuesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\SiteUserProfileRequest;
use App\Models\PublicSite\SiteUser;
use Illuminate\Http\RedirectResponse;

class SiteUserProfileController extends Controller
{
    public function update(SiteUserProfileRequest $request, StoreSiteUserProfileFieldValuesAction $storeProfileFieldValues): RedirectResponse
    {
        $siteUser = $request->user('site_user');
        abort_unless($siteUser instanceof SiteUser, 403);

        $validated = $request->validated();

        $siteUser->forceFill([
            'name' => $validated['name'],
        ])->save();

        $siteUser->profile()->updateOrCreate(
            ['site_user_id' => $siteUser->id],
            [
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'marketing_opt_in' => (bool) ($validated['marketing_opt_in'] ?? false),
                'locale' => app()->getLocale(),
            ],
        );

        $storeProfileFieldValues->handle($siteUser, (array) ($validated['profile_fields'] ?? []), 'profile');

        return back()->with('status', __('public_account.auth.profile_saved'));
    }
}
