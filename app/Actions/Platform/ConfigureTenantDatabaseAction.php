<?php

namespace App\Actions\Platform;

use App\Models\Platform\Site;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ConfigureTenantDatabaseAction
{
    public function handle(Site $site): void
    {
        Config::set('database.connections.tenant.database', $site->tenant_database);
        DB::purge('tenant');
        DB::setDefaultConnection('tenant');

        TenantContext::setSite($site);
    }
}
