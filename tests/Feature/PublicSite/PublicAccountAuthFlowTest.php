<?php

namespace Tests\Feature\PublicSite;

use App\Actions\PublicSite\TrackSiteUserSessionAction;
use App\Models\Cms\CmsLanguage;
use App\Models\PublicSite\SiteUser;
use App\Models\PublicSite\SiteUserSession;
use App\Support\PublicSite\PublicAccountSettings;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PublicAccountAuthFlowTest extends PublicCmsTestCase
{
    public function test_site_user_can_login_with_valid_credentials(): void
    {
        $siteUser = $this->createSiteUser([
            'email' => 'login-user@example.test',
            'password' => 'correct-password',
        ]);

        $this
            ->post(route('site-user.login.store'), [
                'email' => 'login-user@example.test',
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('site-user.dashboard', absolute: false));

        $this->assertTrue(auth('site_user')->check());
        $this->assertTrue(auth('site_user')->user()?->is($siteUser));
        $this->assertNotNull($siteUser->fresh()?->last_login_at);
        $this->assertSame(1, $siteUser->sessions()->active()->count());
    }

    public function test_site_user_logout_revokes_current_session(): void
    {
        $siteUser = $this->createSiteUser([
            'email' => 'logout-session-user@example.test',
        ]);
        $session = SiteUserSession::query()->create([
            'site_user_id' => $siteUser->id,
            'session_token_hash' => hash('sha256', 'current-token'),
            'last_activity_at' => now(),
        ]);

        $this
            ->actingAs($siteUser, 'site_user')
            ->withSession([TrackSiteUserSessionAction::SESSION_TOKEN_KEY => 'current-token'])
            ->post(route('site-user.logout'))
            ->assertRedirect(route('site-user.login'))
            ->assertSessionHas('status', __('public_account.auth.signed_out'));

        $this->assertNotNull($session->refresh()->revoked_at);
        $this->assertFalse(auth('site_user')->check());
    }

    public function test_site_user_cannot_login_with_invalid_credentials(): void
    {
        $siteUser = $this->createSiteUser([
            'email' => 'failed-login-user@example.test',
            'password' => 'correct-password',
        ]);

        $this
            ->from(route('site-user.login'))
            ->post(route('site-user.login.store'), [
                'email' => 'failed-login-user@example.test',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('site-user.login'))
            ->assertSessionHasErrors('email');

        $this->assertFalse(auth('site_user')->check());
        $this->assertNull($siteUser->fresh()?->last_login_at);
    }

    public function test_registration_is_blocked_when_disabled(): void
    {
        $this->storeSetting(PublicAccountSettings::GROUP, PublicAccountSettings::REGISTRATION_ENABLED, false);

        $this
            ->post(route('site-user.register.store'), [
                'name' => 'Blocked User',
                'email' => 'blocked-register@example.test',
                'password' => 'Valid-password-123',
                'password_confirmation' => 'Valid-password-123',
            ])
            ->assertRedirect(route('site-user.register'))
            ->assertSessionHas('warning', __('public_account.auth.registration_disabled'));

        $this->assertFalse(SiteUser::query()->where('email', 'blocked-register@example.test')->exists());
        $this->assertFalse(auth('site_user')->check());
    }

    public function test_registration_creates_and_logs_in_site_user_when_enabled(): void
    {
        $this->storeSetting(PublicAccountSettings::GROUP, PublicAccountSettings::REGISTRATION_ENABLED, true);
        $this->storeSetting(PublicAccountSettings::GROUP, PublicAccountSettings::EMAIL_VERIFICATION_REQUIRED, false);

        $this
            ->post(route('site-user.register.store'), [
                'name' => 'Registered User',
                'email' => 'registered-user@example.test',
                'password' => 'Valid-password-123',
                'password_confirmation' => 'Valid-password-123',
            ])
            ->assertRedirect(route('site-user.dashboard'));

        $siteUser = SiteUser::query()->where('email', 'registered-user@example.test')->firstOrFail();

        $this->assertTrue(auth('site_user')->check());
        $this->assertTrue(auth('site_user')->user()?->is($siteUser));
        $this->assertNotNull($siteUser->email_verified_at);
        $this->assertNotNull($siteUser->profile);
        $this->assertSame(1, $siteUser->sessions()->active()->count());
    }

    public function test_revoked_site_user_session_is_signed_out_on_protected_route(): void
    {
        $siteUser = $this->createSiteUser([
            'email' => 'revoked-session-user@example.test',
        ]);

        SiteUserSession::query()->create([
            'site_user_id' => $siteUser->id,
            'session_token_hash' => hash('sha256', 'revoked-token'),
            'last_activity_at' => now(),
            'revoked_at' => now(),
        ]);

        $this
            ->actingAs($siteUser, 'site_user')
            ->withSession([TrackSiteUserSessionAction::SESSION_TOKEN_KEY => 'revoked-token'])
            ->get(route('site-user.dashboard'))
            ->assertRedirect(route('site-user.login'))
            ->assertSessionHas('warning', __('public_account.sessions.revoked'));

        $this->assertFalse(auth('site_user')->check());
    }

    public function test_site_user_can_sign_out_other_devices(): void
    {
        $siteUser = $this->createSiteUser([
            'email' => 'other-devices-user@example.test',
        ]);
        $currentSession = SiteUserSession::query()->create([
            'site_user_id' => $siteUser->id,
            'session_token_hash' => hash('sha256', 'current-token'),
            'last_activity_at' => now(),
        ]);
        $otherSession = SiteUserSession::query()->create([
            'site_user_id' => $siteUser->id,
            'session_token_hash' => hash('sha256', 'other-token'),
            'last_activity_at' => now(),
        ]);

        $this
            ->actingAs($siteUser, 'site_user')
            ->withSession([TrackSiteUserSessionAction::SESSION_TOKEN_KEY => 'current-token'])
            ->post(route('site-user.sessions.logout-other-devices'))
            ->assertRedirect(route('site-user.security'))
            ->assertSessionHas('status', trans_choice('public_account.sessions.other_devices_signed_out', 1, ['count' => 1]));

        $this->assertNull($currentSession->refresh()->revoked_at);
        $this->assertNotNull($otherSession->refresh()->revoked_at);
    }

    public function test_dashboard_requires_site_user_authentication(): void
    {
        $this
            ->get(route('site-user.dashboard'))
            ->assertRedirect(route('site-user.login'));
    }

    public function test_email_verification_required_redirects_to_security_page(): void
    {
        $this->storeSetting(PublicAccountSettings::GROUP, PublicAccountSettings::EMAIL_VERIFICATION_REQUIRED, true);

        $siteUser = $this->createSiteUser([
            'email' => 'unverified-user@example.test',
            'email_verified_at' => null,
        ]);

        $this
            ->actingAs($siteUser, 'site_user')
            ->get(route('site-user.dashboard'))
            ->assertRedirect(route('site-user.security'))
            ->assertSessionHas('warning', __('public_account.auth.email_verification_required'));
    }

    public function test_profile_update_uses_public_site_default_locale_for_flash_message(): void
    {
        $this->storeSetting('general', 'default_locale', 'nl');
        $siteUser = $this->createSiteUser([
            'email' => 'profile-locale-user@example.test',
        ]);

        $this
            ->actingAs($siteUser, 'site_user')
            ->post(route('site-user.profile.update'), [
                'name' => 'Profile Locale User',
                'first_name' => 'Profile',
                'last_name' => 'Locale',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Je profiel is bewaard.');

        $this->assertSame('nl', $siteUser->profile()->first()?->locale);
    }

    public function test_profile_update_uses_referer_locale_when_posting_from_localized_page(): void
    {
        $this->storeSetting('general', 'default_locale', 'nl');
        $this->storeSetting('general', 'multilingual_enabled', true);
        CmsLanguage::query()->updateOrCreate(
            ['locale' => 'fr'],
            [
                'name' => 'French',
                'native_name' => 'Francais',
                'direction' => 'ltr',
                'is_active' => true,
                'sort_order' => 20,
            ],
        );
        $siteUser = $this->createSiteUser([
            'email' => 'profile-referer-locale-user@example.test',
        ]);

        $this
            ->actingAs($siteUser, 'site_user')
            ->withHeader('referer', 'http://localhost/fr/account/profile')
            ->post(route('site-user.profile.update'), [
                'name' => 'Profile Referer Locale User',
                'first_name' => 'Profile',
                'last_name' => 'Referer',
            ])
            ->assertRedirect();

        $this->assertSame('fr', $siteUser->profile()->first()?->locale);
    }

    public function test_password_reset_updates_password_and_dispatches_event(): void
    {
        Event::fake([PasswordReset::class]);

        $siteUser = $this->createSiteUser([
            'email' => 'reset-user@example.test',
            'password' => 'old-password',
            'remember_token' => 'old-token',
        ]);
        SiteUserSession::query()->create([
            'site_user_id' => $siteUser->id,
            'session_token_hash' => hash('sha256', 'reset-token-one'),
            'last_activity_at' => now(),
        ]);
        SiteUserSession::query()->create([
            'site_user_id' => $siteUser->id,
            'session_token_hash' => hash('sha256', 'reset-token-two'),
            'last_activity_at' => now(),
        ]);
        $token = Password::broker('site_users')->createToken($siteUser);

        $this
            ->post(route('site-user.password.store'), [
                'token' => $token,
                'email' => 'reset-user@example.test',
                'password' => 'New-password-123',
                'password_confirmation' => 'New-password-123',
            ])
            ->assertRedirect(route('site-user.login'))
            ->assertSessionHas('status', __('public_account.auth.password_reset'));

        $siteUser->refresh();

        $this->assertTrue(Hash::check('New-password-123', (string) $siteUser->password));
        $this->assertNotSame('old-token', $siteUser->remember_token);
        $this->assertSame(0, $siteUser->sessions()->active()->count());

        Event::assertDispatched(PasswordReset::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSiteUser(array $overrides = []): SiteUser
    {
        $password = (string) ($overrides['password'] ?? 'password');
        unset($overrides['password']);

        return SiteUser::query()->create(array_merge([
            'name' => 'Site User',
            'email' => 'site-user-'.uniqid().'@example.test',
            'password' => $password,
            'status' => 'active',
            'email_verified_at' => now(),
        ], $overrides));
    }
}
