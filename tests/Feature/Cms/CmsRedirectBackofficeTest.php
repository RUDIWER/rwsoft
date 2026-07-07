<?php

namespace Tests\Feature\Cms;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Cms\CmsRedirect;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CmsRedirectBackofficeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'rwsoft',
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_redirect_pages_render_inertia_pages(): void
    {
        $user = $this->createAdminUser();
        $redirect = $this->createRedirect();

        $this
            ->actingAs($user)
            ->get(route('admin.cms.redirects.index'), $this->inertiaHeaders('/admin/cms/redirects'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Redirects/Index');

        $this
            ->actingAs($user)
            ->get(route('admin.cms.redirects.create'), $this->inertiaHeaders('/admin/cms/redirects/create'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Redirects/Edit');

        $this
            ->actingAs($user)
            ->get(route('admin.cms.redirects.edit', ['id' => $redirect->id]), $this->inertiaHeaders('/admin/cms/redirects/'.$redirect->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Redirects/Edit')
            ->assertJsonPath('props.redirectItem.id', $redirect->id);
    }

    public function test_redirect_can_be_stored(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.redirects.store', ['id' => 0]), $this->redirectPayload([
                'source_path' => '/oude-url',
                'target_url' => '/nieuwe-url',
                'status_code' => 308,
                'locale' => 'nl',
            ]))
            ->assertRedirect(route('admin.cms.redirects.index'))
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $redirect = CmsRedirect::query()->where('source_path', '/oude-url')->first();

        $this->assertNotNull($redirect);
        $this->assertSame('/nieuwe-url', $redirect?->target_url);
        $this->assertSame(308, $redirect?->status_code);
        $this->assertSame('nl', $redirect?->locale);
    }

    public function test_redirect_source_path_is_unique_per_locale(): void
    {
        $user = $this->createAdminUser();

        $this->createRedirect([
            'source_path' => '/dubbel',
            'locale' => 'nl',
        ]);

        $this
            ->actingAs($user)
            ->from(route('admin.cms.redirects.create'))
            ->post(route('admin.cms.redirects.store', ['id' => 0]), $this->redirectPayload([
                'source_path' => '/dubbel',
                'locale' => 'nl',
            ]))
            ->assertRedirect(route('admin.cms.redirects.create'))
            ->assertSessionHasErrors(['source_path']);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.redirects.store', ['id' => 0]), $this->redirectPayload([
                'source_path' => '/dubbel',
                'locale' => 'fr',
            ]))
            ->assertRedirect(route('admin.cms.redirects.index'))
            ->assertSessionDoesntHaveErrors(['source_path']);
    }

    public function test_redirect_rejects_invalid_paths_and_targets(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->from(route('admin.cms.redirects.create'))
            ->post(route('admin.cms.redirects.store', ['id' => 0]), $this->redirectPayload([
                'source_path' => 'zonder-slash',
                'target_url' => 'javascript:alert(1)',
            ]))
            ->assertRedirect(route('admin.cms.redirects.create'))
            ->assertSessionHasErrors(['source_path', 'target_url']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createRedirect(array $overrides = []): CmsRedirect
    {
        return CmsRedirect::query()->create($this->redirectPayload(array_merge([
            'source_path' => '/oude-pagina-'.uniqid(),
        ], $overrides)));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function redirectPayload(array $overrides = []): array
    {
        return array_merge([
            'source_path' => '/oude-pagina',
            'target_url' => '/nieuwe-pagina',
            'status_code' => 301,
            'locale' => null,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ], $overrides);
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

    private function createAdminUser(): User
    {
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('cms-redirect-test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);

        $roleId = DB::table('acl_roles')->where('key', 'super_admin')->value('id');

        if (! $roleId) {
            $roleId = DB::table('acl_roles')->insertGetId([
                'key' => 'super_admin',
                'name' => 'Super administrator',
                'description' => 'Test super admin role',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('acl_role_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'acl_role_id' => $roleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $user;
    }
}
