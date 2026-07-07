<?php

namespace App\Console\Commands\Platform;

use App\Actions\Admin\Cms\InstallPublicAccountModuleAction;
use App\Actions\Platform\ConfigureTenantDatabaseAction;
use App\Models\Platform\Site;
use Illuminate\Console\Command;

class InstallPublicAccountModuleCommand extends Command
{
    protected $signature = 'cms:install-public-account {--site= : ID van een specifieke site}';

    protected $description = 'Installeer de public-account CMS module in een of meerdere tenant-sites.';

    public function handle(ConfigureTenantDatabaseAction $configureTenantDatabase, InstallPublicAccountModuleAction $installPublicAccountModule): int
    {
        $sites = Site::query()
            ->when($this->option('site'), fn ($query, mixed $siteId) => $query->whereKey((int) $siteId))
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        if ($sites->isEmpty()) {
            $this->warn('Geen actieve sites gevonden voor public-account installatie.');

            return self::SUCCESS;
        }

        foreach ($sites as $site) {
            $this->line("Public-account module installeren voor [{$site->name}] ({$site->tenant_database})...");
            $configureTenantDatabase->handle($site);
            $result = $installPublicAccountModule->handle();
            $this->info("Klaar: {$result['pages']} nieuwe pagina's, {$result['blocks']} systeemblokken, {$result['forms']} systeemformulieren, {$result['profile_fields']} profielvelden en {$result['templates']} systeemtemplates gesynchroniseerd.");
        }

        return self::SUCCESS;
    }
}
