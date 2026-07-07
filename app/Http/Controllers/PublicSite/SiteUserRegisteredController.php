<?php

namespace App\Http\Controllers\PublicSite;

use App\Actions\PublicSite\StoreSiteUserProfileFieldValuesAction;
use App\Actions\PublicSite\TrackSiteUserSessionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\SiteUserRegisterRequest;
use App\Models\PublicSite\SiteUser;
use App\Support\PublicSite\PublicAccountSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;

class SiteUserRegisteredController extends Controller
{
    public function __construct(private readonly PublicAccountSettings $settings) {}

    public function store(SiteUserRegisterRequest $request, StoreSiteUserProfileFieldValuesAction $storeProfileFieldValues, TrackSiteUserSessionAction $sessions): RedirectResponse
    {
        if (! $this->settings->registrationEnabled()) {
            return redirect()
                ->route('site-user.register')
                ->with('warning', __('public_account.auth.registration_disabled'));
        }

        $validated = $request->validated();

        $siteUser = SiteUser::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'status' => 'active',
            'email_verified_at' => $this->settings->emailVerificationRequired() ? null : now(),
        ]);

        $siteUser->profile()->create([
            'locale' => app()->getLocale(),
        ]);

        $storeProfileFieldValues->handle($siteUser, (array) ($validated['profile_fields'] ?? []), 'register');

        event(new Registered($siteUser));

        auth('site_user')->login($siteUser);
        $request->session()->regenerate();
        $sessions->track($request, $siteUser);

        return redirect()->route('site-user.dashboard');
    }
}
