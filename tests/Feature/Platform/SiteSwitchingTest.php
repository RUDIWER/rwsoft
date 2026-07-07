<?php

namespace Tests\Feature\Platform;

use App\Actions\Platform\CreateSiteSwitchTokenAction;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Platform\Site;
use App\Models\Platform\SiteSwitchToken;
use App\Models\Platform\SiteUserMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiteSwitchingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'central',
            'database.connections.central' => array_merge(config('database.connections.mysql'), [
                'database' => 'rwsoft',
            ]),
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

    public function test_login_redirects_to_site_switcher_by_default(): void
    {
        $user = $this->createUser([
            'email' => 'login-switcher-'.uniqid().'@example.com',
        ]);

        $this
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('site-switcher.index', absolute: false));
    }

    public function test_site_switcher_renders_active_memberships(): void
    {
        $user = $this->createUser();
        $site = $this->createActiveSiteWithDomain('switcher-render');
        $this->createMembership($site, $user);

        $this
            ->actingAs($user)
            ->get(route('site-switcher.index'), $this->inertiaHeaders('/site-switcher'))
            ->assertOk()
            ->assertJsonPath('component', 'Auth/SiteSwitcher')
            ->assertJsonFragment([
                'name' => $site->name,
                'slug' => $site->slug,
                'primary_domain' => $site->primaryDomain()->first()?->host,
            ]);
    }

    public function test_site_switch_creates_token_and_redirects_to_primary_domain(): void
    {
        $user = $this->createUser();
        $site = $this->createActiveSiteWithDomain('switch-token');
        $domain = $site->primaryDomain()->first()?->host;
        $this->createMembership($site, $user);

        $this
            ->actingAs($user)
            ->post(route('site-switcher.switch', ['site' => $site->id]))
            ->assertRedirectContains('https://'.$domain.'/auth/site-switch?token=');

        $this->assertSame(1, SiteSwitchToken::query()->where('site_id', $site->id)->where('user_id', $user->id)->count());
    }

    public function test_site_switch_token_callback_logs_user_into_resolved_site(): void
    {
        $user = $this->createUser();
        $site = $this->createActiveSiteWithDomain('callback');
        $domain = $site->primaryDomain()->first()?->host;
        $membership = $this->createMembership($site, $user);
        $request = Request::create('/site-switcher/'.$site->id.'/switch', 'POST');
        $plainToken = app(CreateSiteSwitchTokenAction::class)->handle($user, $site, $request);

        $this
            ->get('https://'.$domain.'/auth/site-switch?token='.$plainToken)
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);

        $switchToken = SiteSwitchToken::query()
            ->where('site_id', $site->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($switchToken?->used_at);
        $this->assertNotNull($membership->fresh()?->last_accessed_at);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'two_factor_secret' => encrypt('site-switch-test-secret'),
            'two_factor_confirmed_at' => now(),
        ], $overrides));
    }

    private function createActiveSiteWithDomain(string $prefix): Site
    {
        $slug = $prefix.'-'.uniqid();

        $site = Site::query()->create([
            'name' => 'Site '.$slug,
            'slug' => $slug,
            'tenant_database' => 'rwsoft_site_'.str_replace('-', '_', $slug),
            'status' => 'active',
            'provisioned_at' => now(),
        ]);

        $site->domains()->create([
            'host' => $slug.'.example.test',
            'is_primary' => true,
            'force_https' => true,
        ]);

        return $site;
    }

    private function createMembership(Site $site, User $user): SiteUserMembership
    {
        return SiteUserMembership::query()->create([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(string $path): array
    {
        $request = Request::create($path, 'GET');
        $version = app(HandleInertiaRequests::class)->version($request);

        return [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) ($version ?? ''),
        ];
    }
}
