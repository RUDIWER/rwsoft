<?php

namespace App\Console\Commands\Platform;

use App\Actions\Platform\SeedTenantAclAction;
use App\Models\Platform\Site;
use Illuminate\Console\Command;

class SeedTenantAclCommand extends Command
{
    protected $signature = 'tenants:seed-acl {--site= : ID van een specifieke site}';

    protected $description = 'Synchroniseer tenant rollen en route-permissies voor site databases.';

    public function handle(SeedTenantAclAction $seedTenantAcl): int
    {
        $sites = Site::query()
            ->when($this->option('site'), fn ($query, mixed $siteId) => $query->whereKey((int) $siteId))
            ->orderBy('id')
            ->get();

        if ($sites->isEmpty()) {
            $this->warn('Geen sites gevonden voor tenant ACL seeding.');

            return self::SUCCESS;
        }

        foreach ($sites as $site) {
            $this->line("Tenant ACL synchroniseren voor [{$site->name}] ({$site->tenant_database})...");
            $seedTenantAcl->handle($site);
        }

        return self::SUCCESS;
    }
}
