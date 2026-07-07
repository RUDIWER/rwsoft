<?php

namespace Tests\Feature\PublicSite;

use App\Models\PublicSite\SiteUser;
use App\Support\PublicSite\PublicAccountSettings;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class PublicAccountTwoFactorFlowTest extends PublicCmsTestCase
{
    public function test_two_factor_enable_is_not_available_when_disabled_in_settings(): void
    {
        $this->storeSetting(PublicAccountSettings::GROUP, PublicAccountSettings::TWO_FACTOR_MODE, 'disabled');
        $siteUser = $this->createSiteUser();

        $this
            ->actingAs($siteUser, 'site_user')
            ->post(route('site-user.two-factor.enable'))
            ->assertNotFound();

        $this->assertNull($siteUser->fresh()?->two_factor_secret);
    }

    public function test_two_factor_can_be_enabled_and_confirmed_when_optional(): void
    {
        $this->fakeTwoFactorProvider();
        $this->storeSetting(PublicAccountSettings::GROUP, PublicAccountSettings::TWO_FACTOR_MODE, 'optional');
        $siteUser = $this->createSiteUser();

        $this
            ->actingAs($siteUser, 'site_user')
            ->post(route('site-user.two-factor.enable'))
            ->assertRedirect()
            ->assertSessionHas('status', __('public_account.two_factor.enabled'));

        $siteUser->refresh();

        $this->assertNotNull($siteUser->two_factor_secret);
        $this->assertNotNull($siteUser->two_factor_recovery_codes);
        $this->assertNull($siteUser->two_factor_confirmed_at);

        $this
            ->actingAs($siteUser, 'site_user')
            ->post(route('site-user.two-factor.confirm'), ['code' => '123456'])
            ->assertRedirect()
            ->assertSessionHas('status', __('public_account.two_factor.confirmed'));

        $this->assertNotNull($siteUser->fresh()?->two_factor_confirmed_at);
    }

    public function test_required_two_factor_redirects_unprepared_account_to_security_page(): void
    {
        $this->storeSetting(PublicAccountSettings::GROUP, PublicAccountSettings::TWO_FACTOR_MODE, 'required');
        $siteUser = $this->createSiteUser();

        $this
            ->actingAs($siteUser, 'site_user')
            ->get(route('site-user.dashboard'))
            ->assertRedirect(route('site-user.security'))
            ->assertSessionHas('warning', __('public_account.two_factor.required'));
    }

    public function test_login_with_confirmed_two_factor_requires_challenge(): void
    {
        $this->fakeTwoFactorProvider();
        $siteUser = $this->createSiteUser([
            'email' => 'two-factor-user@example.test',
            'password' => 'correct-password',
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('secret'),
            'two_factor_confirmed_at' => now(),
        ]);

        $this
            ->post(route('site-user.login.store'), [
                'email' => 'two-factor-user@example.test',
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('site-user.two-factor.challenge'));

        $this->assertFalse(auth('site_user')->check());
        $this->assertSame($siteUser->id, (int) session('site_user.login.id'));

        $this
            ->post(route('site-user.two-factor.challenge.store'), ['code' => '123456'])
            ->assertRedirect(route('site-user.dashboard', absolute: false));

        $this->assertTrue(auth('site_user')->check());
        $this->assertTrue(auth('site_user')->user()?->is($siteUser));
        $this->assertNull(session('site_user.login.id'));
        $this->assertNotNull($siteUser->fresh()?->last_login_at);
        $this->assertSame(1, $siteUser->sessions()->active()->count());
    }

    public function test_two_factor_challenge_does_not_login_deactivated_pending_account(): void
    {
        $this->fakeTwoFactorProvider();
        $siteUser = $this->createSiteUser([
            'email' => 'deactivated-two-factor-user@example.test',
            'password' => 'correct-password',
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('secret'),
            'two_factor_confirmed_at' => now(),
        ]);

        $this
            ->post(route('site-user.login.store'), [
                'email' => 'deactivated-two-factor-user@example.test',
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('site-user.two-factor.challenge'));

        $siteUser->forceFill(['status' => 'inactive'])->save();

        $this
            ->post(route('site-user.two-factor.challenge.store'), ['code' => '123456'])
            ->assertRedirect(route('site-user.login'))
            ->assertSessionHas('error', __('public_account.auth.inactive'));

        $this->assertFalse(auth('site_user')->check());
        $this->assertNull(session('site_user.login.id'));
    }

    private function fakeTwoFactorProvider(): void
    {
        $this->app->bind(TwoFactorAuthenticationProvider::class, fn (): object => new class implements TwoFactorAuthenticationProvider
        {
            public function generateSecretKey(): string
            {
                return 'secret';
            }

            public function qrCodeUrl($companyName, $companyEmail, $secret): string
            {
                return 'otpauth://totp/test';
            }

            public function verify($secret, $code): bool
            {
                return $secret === 'secret' && $code === '123456';
            }
        });
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
