<?php

namespace App\Actions\Platform;

use App\Models\Platform\Site;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProvisionSiteDatabaseAction
{
    public function __construct(
        private readonly ConfigureTenantDatabaseAction $configureTenantDatabase,
        private readonly SeedTenantAclAction $seedTenantAcl,
        private readonly SeedTenantCmsDefaultsAction $seedTenantCmsDefaults,
    ) {}

    public function handle(Site $site): bool
    {
        $site->forceFill([
            'status' => 'provisioning',
            'provisioning_error' => null,
        ])->save();

        try {
            if ($site->tenantProvisioningMode() === 'create_database') {
                $this->createDatabase($site->tenant_database);
            }

            $this->assertTenantConnectionReady($site);

            Artisan::call('tenants:migrate', [
                '--site' => $site->id,
                '--force' => true,
            ]);

            $this->seedTenantAcl->handle($site);
            $this->seedTenantCmsDefaults->handle($site);

            $site->forceFill([
                'status' => 'active',
                'provisioned_at' => now(),
                'provisioning_error' => null,
            ])->save();

            return true;
        } catch (Throwable $exception) {
            $site->forceFill([
                'status' => 'failed',
                'provisioning_error' => $exception->getMessage(),
            ])->save();

            return false;
        }
    }

    private function createDatabase(string $database): void
    {
        $quotedDatabase = str_replace('`', '``', $database);

        DB::connection('central')->statement("CREATE DATABASE IF NOT EXISTS `{$quotedDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    private function assertTenantConnectionReady(Site $site): void
    {
        if ($site->usesSharedPrefixedTenantDatabase()) {
            $this->assertTenantTablePrefix($site);
        }

        $this->configureTenantDatabase->handle($site);
        DB::connection('tenant')->getPdo();
    }

    private function assertTenantTablePrefix(Site $site): void
    {
        $prefix = (string) $site->tenant_table_prefix;
        $pattern = (string) config('tenancy.table_prefix_pattern');

        if ($prefix === '' || preg_match($pattern, $prefix) !== 1) {
            throw new \InvalidArgumentException(__('admin_common_ui.errors.tenant_table_prefix_invalid'));
        }
    }
}
