<?php

namespace Tests\Unit\Tenancy;

use App\Actions\Platform\ConfigureTenantDatabaseAction;
use App\Models\Platform\Site;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantDatabaseGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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
        $this->assertSame(123456, TenantContext::siteId());
    }

    public function test_user_model_stays_on_central_connection(): void
    {
        $this->assertSame('central', (new User)->getConnectionName());
    }

    public function test_tenant_database_guard_rejects_missing_tenant_context(): void
    {
        TenantContext::clear();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant context is niet actief.');

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
