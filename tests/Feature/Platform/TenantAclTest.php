<?php

namespace Tests\Feature\Platform;

use App\Actions\Platform\SeedTenantAclAction;
use App\Models\Platform\Site;
use App\Models\User;
use App\Support\Security\TenantAcl;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantAclTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'central',
            'database.connections.central' => array_merge(config('database.connections.mysql'), [
                'database' => 'rwsoft',
            ]),
            'database.connections.tenant' => array_merge(config('database.connections.mysql'), [
                'database' => 'rwsoft',
            ]),
        ]);

        DB::purge('central');
        DB::purge('tenant');
        DB::reconnect('central');
        DB::reconnect('tenant');
        DB::connection('tenant')->beginTransaction();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        parent::tearDown();
    }

    public function test_tenant_acl_checks_roles_and_route_permissions_on_tenant_connection(): void
    {
        $superAdminRoleId = DB::connection('tenant')->table('acl_roles')->where('key', 'super_admin')->value('id');
        $superAdminUserIds = $superAdminRoleId
            ? DB::connection('tenant')->table('acl_role_user')->where('acl_role_id', $superAdminRoleId)->pluck('user_id')->all()
            : [];
        $user = User::query()
            ->when($superAdminUserIds !== [], fn ($query) => $query->whereNotIn('id', $superAdminUserIds))
            ->firstOrFail();
        $site = new Site([
            'name' => 'Tenant ACL Test',
            'slug' => 'tenant-acl-test',
            'tenant_database' => 'rwsoft',
            'status' => 'active',
        ]);
        $site->id = 999999;
        TenantContext::setSite($site);

        $roleId = $this->ensureTenantRole('admin');
        $permissionId = $this->ensureTenantPermission('tenant.test.'.uniqid());

        DB::connection('tenant')->table('acl_role_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'acl_role_id' => $roleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::connection('tenant')->table('acl_permission_role')->insert([
            'acl_role_id' => $roleId,
            'acl_permission_id' => $permissionId,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $acl = app(TenantAcl::class);
        $routeName = DB::connection('tenant')->table('acl_permissions')->where('id', $permissionId)->value('route_name');

        $this->assertTrue($acl->hasRoleKey($user, 'admin'));
        $this->assertTrue($acl->canAccessRoute($user, $routeName));
        $this->assertContains($routeName, $acl->allowedRouteNames($user));
    }

    public function test_tenant_acl_seed_includes_cms_theme_menu_permissions(): void
    {
        $user = User::query()->firstOrFail();
        $site = new Site([
            'name' => 'Tenant Theme ACL Test',
            'slug' => 'tenant-theme-acl-test',
            'tenant_database' => 'rwsoft',
            'status' => 'active',
        ]);
        $site->id = 999998;

        app(SeedTenantAclAction::class)->handle($site);

        $routeName = 'admin.cms.themes.index';
        $permission = DB::connection('tenant')
            ->table('acl_permissions')
            ->where('route_name', $routeName)
            ->first();

        $this->assertNotNull($permission);
        $this->assertTrue((bool) $permission->menu);

        $adminRoleId = DB::connection('tenant')->table('acl_roles')->where('key', 'admin')->value('id');

        DB::connection('tenant')->table('acl_role_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'acl_role_id' => $adminRoleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $acl = app(TenantAcl::class);

        $this->assertTrue($acl->canAccessRoute($user, $routeName));
        $this->assertContains($routeName, $acl->allowedRouteNames($user));
    }

    public function test_tenant_acl_seed_includes_cms_block_menu_permissions(): void
    {
        $user = User::query()->firstOrFail();
        $site = new Site([
            'name' => 'Tenant Block ACL Test',
            'slug' => 'tenant-block-acl-test',
            'tenant_database' => 'rwsoft',
            'status' => 'active',
        ]);
        $site->id = 999996;

        app(SeedTenantAclAction::class)->handle($site);

        $routeName = 'admin.cms.blocks.index';
        $permission = DB::connection('tenant')
            ->table('acl_permissions')
            ->where('route_name', $routeName)
            ->first();
        $restoreRouteName = 'admin.cms.blocks.restore-revision';
        $restorePermission = DB::connection('tenant')
            ->table('acl_permissions')
            ->where('route_name', $restoreRouteName)
            ->first();

        $this->assertNotNull($permission);
        $this->assertTrue((bool) $permission->menu);
        $this->assertSame('admin/cms/blocks', $permission->url);
        $this->assertNotNull($restorePermission);
        $this->assertFalse((bool) $restorePermission->menu);
        $this->assertSame('admin/cms/blocks/{block}/revisions/{revision}/restore', $restorePermission->url);

        $adminRoleId = DB::connection('tenant')->table('acl_roles')->where('key', 'admin')->value('id');

        DB::connection('tenant')->table('acl_role_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'acl_role_id' => $adminRoleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $acl = app(TenantAcl::class);

        $this->assertTrue($acl->canAccessRoute($user, $routeName));
        $this->assertTrue($acl->canAccessRoute($user, $restoreRouteName));
        $this->assertContains($routeName, $acl->allowedRouteNames($user));
        $this->assertContains($restoreRouteName, $acl->allowedRouteNames($user));
        $this->assertFalse($acl->canAccessRoute($user, 'admin.cms.block-variants.index'));
        $this->assertNotContains('admin.cms.block-variants.index', $acl->allowedRouteNames($user));
    }

    public function test_tenant_acl_seed_grants_layout_code_block_permission_to_admin_role(): void
    {
        $user = User::query()->firstOrFail();
        $site = new Site([
            'name' => 'Tenant Layout Code ACL Test',
            'slug' => 'tenant-layout-code-acl-test',
            'tenant_database' => 'rwsoft',
            'status' => 'active',
        ]);
        $site->id = 999997;

        app(SeedTenantAclAction::class)->handle($site);

        $routeName = 'admin.cms.layouts.code-blocks.manage';
        $permission = DB::connection('tenant')
            ->table('acl_permissions')
            ->where('route_name', $routeName)
            ->first();

        $this->assertNotNull($permission);
        $this->assertFalse((bool) $permission->menu);

        $adminRoleId = DB::connection('tenant')->table('acl_roles')->where('key', 'admin')->value('id');

        DB::connection('tenant')->table('acl_role_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'acl_role_id' => $adminRoleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $acl = app(TenantAcl::class);

        $this->assertTrue($acl->canAccessRoute($user, $routeName));
        $this->assertContains($routeName, $acl->allowedRouteNames($user));
    }

    private function ensureTenantRole(string $key): int
    {
        $roleId = DB::connection('tenant')->table('acl_roles')->where('key', $key)->value('id');

        if ($roleId) {
            return (int) $roleId;
        }

        return (int) DB::connection('tenant')->table('acl_roles')->insertGetId([
            'key' => $key,
            'name' => ucfirst($key),
            'description' => 'Tenant ACL test role',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureTenantPermission(string $routeName): int
    {
        return (int) DB::connection('tenant')->table('acl_permissions')->insertGetId([
            'route_name' => $routeName,
            'description' => '[Test] '.$routeName,
            'module_id' => $this->ensureTenantPermissionTaxonomyId('acl_permission_modules', 'cms', 'CMS'),
            'action_id' => $this->ensureTenantPermissionTaxonomyId('acl_permission_actions', 'manage', 'Beheer'),
            'type_id' => $this->ensureTenantPermissionTaxonomyId('acl_permission_types', 'core', 'Core'),
            'query_id' => null,
            'menu' => false,
            'url' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureTenantPermissionTaxonomyId(string $table, string $key, string $name): int
    {
        $id = DB::connection('tenant')->table($table)->where('key', $key)->value('id');

        if ($id) {
            return (int) $id;
        }

        return (int) DB::connection('tenant')->table($table)->insertGetId([
            'key' => $key,
            'name' => $name,
            'sort_order' => 0,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
