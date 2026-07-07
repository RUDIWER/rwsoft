<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Platform\Site;
use App\Models\Platform\SiteUserMembership;
use App\Models\User;
use App\Support\Localization\AdminLocaleResolver;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminLocaleResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'central',
            'database.connections.central' => array_merge(config('database.connections.mysql'), [
                'database' => 'rwsoft',
            ]),
            'app.locale' => 'en',
            'app.available_locales' => ['en', 'nl', 'fr'],
        ]);

        DB::purge('central');
        DB::reconnect('central');
        DB::connection('central')->beginTransaction();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }

        parent::tearDown();
    }

    public function test_admin_membership_locale_overrides_tenant_default_locale(): void
    {
        $user = User::factory()->create();
        $site = $this->createSite();
        TenantContext::setSite($site);

        $this->setTenantDefaultLocale('fr');
        SiteUserMembership::query()->create([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'is_active' => true,
            'admin_locale' => 'nl',
        ]);

        $request = Request::create('/admin/users', 'GET');
        $request->setUserResolver(static fn (): User => $user);

        $this->assertSame('nl', app(AdminLocaleResolver::class)->resolveForRequest($request));
    }

    public function test_admin_uses_tenant_default_locale_without_membership_override(): void
    {
        $user = User::factory()->create();
        $site = $this->createSite();
        TenantContext::setSite($site);

        $this->setTenantDefaultLocale('fr');
        SiteUserMembership::query()->create([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $request = Request::create('/admin/users', 'GET');
        $request->setUserResolver(static fn (): User => $user);

        $this->assertSame('fr', app(AdminLocaleResolver::class)->resolveForRequest($request));
    }

    public function test_platform_locale_uses_session_and_not_tenant_default(): void
    {
        $this->setTenantDefaultLocale('fr');

        $request = Request::create('/platform', 'GET');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->put('locale', 'nl');

        $this->assertSame('nl', app(AdminLocaleResolver::class)->resolveForRequest($request));
    }

    private function createSite(): Site
    {
        return Site::query()->create([
            'name' => 'Locale Test Site',
            'slug' => 'locale-test-'.uniqid(),
            'tenant_database' => 'rwsoft_locale_test',
            'status' => 'active',
            'provisioned_at' => now(),
        ]);
    }

    private function setTenantDefaultLocale(string $locale): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => AdminLocaleResolver::ADMIN_DEFAULT_SETTING_KEY],
            [
                'value' => $locale,
                'is_encrypted' => false,
            ],
        );
    }
}
