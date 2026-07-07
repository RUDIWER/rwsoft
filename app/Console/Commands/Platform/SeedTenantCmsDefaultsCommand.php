<?php

namespace App\Console\Commands\Platform;

use App\Actions\Platform\SeedTenantCmsDefaultsAction;
use App\Models\Platform\Site;
use Illuminate\Console\Command;

class SeedTenantCmsDefaultsCommand extends Command
{
    protected $signature = 'tenants:seed-cms-defaults {--site= : ID van een specifieke site}';

    protected $description = 'Maak ontbrekende basis CMS instellingen en homepage aan voor site databases.';

    public function handle(SeedTenantCmsDefaultsAction $seedTenantCmsDefaults): int
    {
        $sites = Site::query()
            ->when($this->option('site'), fn ($query, mixed $siteId) => $query->whereKey((int) $siteId))
            ->orderBy('id')
            ->get();

        if ($sites->isEmpty()) {
            $this->warn('Geen sites gevonden voor tenant CMS defaults.');

            return self::SUCCESS;
        }

        foreach ($sites as $site) {
            $this->line("Tenant CMS defaults synchroniseren voor [{$site->name}] ({$site->tenant_database})...");
            $seedTenantCmsDefaults->handle($site);
        }

        return self::SUCCESS;
    }
}
