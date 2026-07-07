<?php

namespace App\Console\Commands\Platform;

use App\Actions\Platform\ConfigureTenantDatabaseAction;
use App\Models\Platform\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MigrateTenantsCommand extends Command
{
    protected $signature = 'tenants:migrate {--site= : ID van een specifieke site} {--force : Forceer migraties zonder confirmatie}';

    protected $description = 'Voer tenant migraties uit voor een of meerdere site databases.';

    public function handle(ConfigureTenantDatabaseAction $configureTenantDatabase): int
    {
        $path = database_path('migrations/tenant');

        if (! is_dir($path)) {
            $this->warn('Er bestaat nog geen database/migrations/tenant map. Geen tenant migraties uitgevoerd.');

            return self::SUCCESS;
        }

        $sites = Site::query()
            ->when($this->option('site'), fn ($query, mixed $siteId) => $query->whereKey((int) $siteId))
            ->orderBy('id')
            ->get();

        if ($sites->isEmpty()) {
            $this->warn('Geen sites gevonden voor tenant migraties.');

            return self::SUCCESS;
        }

        foreach ($sites as $site) {
            $this->line("Tenant migraties voor [{$site->name}] ({$site->tenant_database})...");

            $configureTenantDatabase->handle($site);

            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => (bool) $this->option('force'),
            ]);

            $this->output->write(Artisan::output());
        }

        return self::SUCCESS;
    }
}
