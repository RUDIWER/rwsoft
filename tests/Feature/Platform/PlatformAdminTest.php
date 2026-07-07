<?php

namespace Tests\Feature\Platform;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Platform\Site;
use App\Models\Platform\SiteDomain;
use App\Models\Platform\SiteUserMembership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
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
        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }

        parent::tearDown();
    }

    public function test_platform_make_admin_promotes_existing_user(): void
    {
        $user = User::factory()->create([
            'email' => 'platform-admin-'.uniqid().'@example.com',
            'is_platform_admin' => false,
        ]);

        $this
            ->artisan('platform:make-admin', ['email' => $user->email])
            ->assertSuccessful();

        $user->refresh();

        $this->assertTrue((bool) $user->is_platform_admin);
    }

    public function test_platform_make_admin_rejects_missing_user(): void
    {
        $this
            ->artisan('platform:make-admin', ['email' => 'missing-'.uniqid().'@example.com'])
            ->assertFailed();
    }

    public function test_platform_dashboard_requires_platform_admin(): void
    {
        $user = $this->createUser(['is_platform_admin' => false]);

        $this
            ->actingAs($user)
            ->get(route('platform.dashboard'))
            ->assertForbidden();
    }

    public function test_platform_dashboard_renders_for_platform_admin(): void
    {
        $user = $this->createUser(['is_platform_admin' => true]);

        $this
            ->actingAs($user)
            ->get(route('platform.dashboard'), $this->inertiaHeaders('/platform'))
            ->assertOk()
            ->assertJsonPath('component', 'Platform/Dashboard');
    }

    public function test_platform_admin_can_create_site_with_domain_and_membership(): void
    {
        $platformAdmin = $this->createUser(['is_platform_admin' => true]);
        $siteAdmin = $this->createUser([
            'email' => 'site-admin-'.uniqid().'@example.com',
        ]);
        $slug = 'school-'.uniqid();
        $domain = $slug.'.example.test';

        $this
            ->actingAs($platformAdmin)
            ->post(route('platform.sites.store', ['id' => 0]), [
                'name' => 'School '.strtoupper($slug),
                'slug' => $slug,
                'primary_domain' => 'https://'.$domain.'/admin',
                'first_admin_email' => $siteAdmin->email,
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $site = Site::query()
            ->where('slug', $slug)
            ->first();

        $this->assertNotNull($site);
        $this->assertSame('rwsoft_site_'.str_replace('-', '_', $slug), $site?->tenant_database);
        $this->assertSame('draft', $site?->status);

        $domainRecord = SiteDomain::query()
            ->where('site_id', $site?->id)
            ->where('host', $domain)
            ->first();

        $this->assertNotNull($domainRecord);
        $this->assertTrue((bool) $domainRecord?->is_primary);
        $this->assertTrue((bool) $domainRecord?->force_https);

        $membership = SiteUserMembership::query()
            ->where('site_id', $site?->id)
            ->where('user_id', $siteAdmin->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertTrue((bool) $membership?->is_active);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'two_factor_secret' => encrypt('platform-test-secret'),
            'two_factor_confirmed_at' => now(),
        ], $overrides));
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
            'Referer' => $path,
        ];
    }
}
