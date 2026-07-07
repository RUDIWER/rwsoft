<?php

namespace App\Actions\Platform;

use App\Models\Platform\Site;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProvisionSiteDatabaseAction
{
    public function __construct(
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
            $this->createDatabase($site->tenant_database);

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
}
