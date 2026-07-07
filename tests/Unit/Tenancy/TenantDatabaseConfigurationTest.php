<?php

namespace Tests\Unit\Tenancy;

use App\Actions\Platform\ConfigureTenantDatabaseAction;
use App\Models\Platform\Site;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantDatabaseGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class TenantDatabaseConfigurationTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_configure_tenant_database_sets_default_connection_to_tenant(): void
    {
        $site = new Site([
            'name' => 'Tenant Config Test',
            'slug' => 'tenant-config-test',
            'tenant_database' => 'rwsoft_tenant_config_test',
            'status' => 'active',
        ]);
        $site->id = 123456;

        app(ConfigureTenantDatabaseAction::class)->handle($site);

        $this->assertSame('tenant', DB::getDefaultConnection());
        $this->assertSame('rwsoft_tenant_config_test', config('database.connections.tenant.database'));
        $this->assertSame('', config('database.connections.tenant.prefix'));
        $this->assertSame(123456, TenantContext::siteId());
    }

    public function test_configure_shared_prefixed_tenant_database_sets_connection_prefix(): void
    {
        $site = new Site([
            'name' => 'Shared Prefix Test',
            'slug' => 'shared-prefix-test',
            'tenant_database' => 'rwsoft_shared',
            'tenant_table_prefix' => 't_shared_',
            'tenant_database_mode' => 'shared_prefixed',
            'tenant_provisioning_mode' => 'shared_prefixed',
            'status' => 'active',
        ]);
        $site->id = 123458;

        app(ConfigureTenantDatabaseAction::class)->handle($site);

        $this->assertSame('tenant', DB::getDefaultConnection());
        $this->assertSame('rwsoft_shared', config('database.connections.tenant.database'));
        $this->assertSame('t_shared_', config('database.connections.tenant.prefix'));
        $this->assertSame('t_shared_', TenantContext::tablePrefix());
    }

    public function test_configure_shared_prefixed_tenant_database_rejects_invalid_prefix(): void
    {
        $site = new Site([
            'name' => 'Invalid Prefix Test',
            'slug' => 'invalid-prefix-test',
            'tenant_database' => 'rwsoft_shared',
            'tenant_table_prefix' => '123_bad',
            'tenant_database_mode' => 'shared_prefixed',
            'tenant_provisioning_mode' => 'shared_prefixed',
            'status' => 'active',
        ]);
        $site->id = 123459;

        $this->expectException(InvalidArgumentException::class);

        app(ConfigureTenantDatabaseAction::class)->handle($site);
    }

    public function test_user_model_stays_on_central_connection(): void
    {
        $this->assertSame('central', (new User)->getConnectionName());
    }

    public function test_tenant_database_guard_rejects_missing_tenant_context(): void
    {
        TenantContext::clear();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('admin_common_ui.errors.tenant_context_inactive'));

        TenantDatabaseGuard::ensureTenantConnection();
    }

    public function test_tenant_database_guard_accepts_matching_tenant_connection(): void
    {
        config([
            'database.connections.tenant' => array_merge(config('database.connections.mysql'), [
                'database' => 'rwsoft',
            ]),
        ]);

        DB::purge('tenant');

        $site = new Site([
            'name' => 'Tenant Guard Test',
            'slug' => 'tenant-guard-test',
            'tenant_database' => 'rwsoft',
            'status' => 'active',
        ]);
        $site->id = 123457;

        TenantContext::setSite($site);
        DB::setDefaultConnection('tenant');

        TenantDatabaseGuard::ensureTenantConnection();

        $this->assertSame(123457, TenantContext::siteId());
    }

    public function test_tenant_database_guard_rejects_prefix_mismatch(): void
    {
        config([
            'database.connections.tenant' => array_merge(config('database.connections.mysql'), [
                'database' => 'rwsoft',
                'prefix' => 'wrong_',
            ]),
        ]);

        DB::purge('tenant');

        $site = new Site([
            'name' => 'Tenant Guard Prefix Test',
            'slug' => 'tenant-guard-prefix-test',
            'tenant_database' => 'rwsoft',
            'tenant_table_prefix' => 't_guard_',
            'tenant_database_mode' => 'shared_prefixed',
            'tenant_provisioning_mode' => 'shared_prefixed',
            'status' => 'active',
        ]);
        $site->id = 123460;

        TenantContext::setSite($site);
        DB::setDefaultConnection('tenant');

        $this->expectException(RuntimeException::class);

        TenantDatabaseGuard::ensureTenantConnection();
    }

    public function test_public_cms_routes_require_tenant_resolver(): void
    {
        $route = Route::getRoutes()->getByName('cms.public.home');

        $this->assertNotNull($route);
        $this->assertContains('tenant.resolve', $route?->gatherMiddleware() ?? []);
    }

    public function test_public_cms_pdf_routes_require_tenant_resolver_and_throttle(): void
    {
        foreach (['cms.public.pdf.home', 'cms.public.pdf.blogs.show', 'cms.public.localized.pdf.page'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route);
            $middleware = $route?->gatherMiddleware() ?? [];

            $this->assertContains('tenant.resolve', $middleware);
            $this->assertContains('throttle:30,1', $middleware);
        }
    }
}
