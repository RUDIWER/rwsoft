<?php

namespace Tests\Feature\Query\Concerns;

use App\Http\Middleware\ResolveTenantSite;
use App\Models\Platform\Site;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

trait SetsUpQueryTenant
{
    protected function setUpSetsUpQueryTenant(): void
    {
        $this->artisan('migrate:fresh');
        $this->app[Kernel::class]->setArtisan(null);

        $this->setUpQueryTenant();
    }

    protected function setUpQueryTenant(): void
    {
        $this->withoutMiddleware(ResolveTenantSite::class);

        $defaultConnectionName = 'sqlite';
        $defaultConnection = DB::connection($defaultConnectionName);

        config([
            'database.connections.central' => config("database.connections.{$defaultConnectionName}"),
            'database.connections.tenant' => config("database.connections.{$defaultConnectionName}"),
        ]);

        DB::purge('central');
        DB::purge('tenant');

        $centralConnection = DB::connection('central');
        $centralConnection->setPdo($defaultConnection->getPdo());
        $centralConnection->setReadPdo($defaultConnection->getPdo());

        $tenantConnection = DB::connection('tenant');
        $tenantConnection->setPdo($defaultConnection->getPdo());
        $tenantConnection->setReadPdo($defaultConnection->getPdo());

        DB::setDefaultConnection('tenant');

        DB::connection('central')->table('sites')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Query Test Site',
                'slug' => 'query-test-site',
                'tenant_database' => (string) $tenantConnection->getDatabaseName(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $site = new Site;
        $site->forceFill([
            'id' => 1,
            'name' => 'Query Test Site',
            'slug' => 'query-test-site',
            'tenant_database' => (string) $tenantConnection->getDatabaseName(),
            'status' => 'active',
        ]);
        $site->exists = true;

        TenantContext::setSite($site);

        $this->beforeApplicationDestroyed(static function (): void {
            TenantContext::clear();
        });
    }

    protected function tearDownSetsUpQueryTenant(): void
    {
        TenantContext::clear();
    }

    protected function grantQueryTestSiteMembership(User $user): void
    {
        DB::connection('central')->table('site_user_memberships')->updateOrInsert(
            [
                'site_id' => 1,
                'user_id' => $user->id,
            ],
            [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
