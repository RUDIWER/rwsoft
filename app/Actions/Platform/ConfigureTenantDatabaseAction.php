<?php

namespace App\Actions\Platform;

use App\Models\Platform\Site;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConfigureTenantDatabaseAction
{
    public function handle(Site $site): void
    {
        Config::set('database.connections.tenant.url', $this->tenantConnectionValue($site, 'url', 'tenant_database_url'));
        Config::set('database.connections.tenant.host', $this->tenantConnectionValue($site, 'host', 'tenant_database_host'));
        Config::set('database.connections.tenant.port', $this->tenantConnectionValue($site, 'port', 'tenant_database_port'));
        Config::set('database.connections.tenant.username', $this->tenantConnectionValue($site, 'username', 'tenant_database_username'));
        Config::set('database.connections.tenant.password', $this->tenantConnectionValue($site, 'password', 'tenant_database_password'));
        Config::set('database.connections.tenant.database', $this->tenantDatabase($site));
        Config::set('database.connections.tenant.prefix', $this->tenantTablePrefix($site));

        DB::purge('tenant');
        DB::setDefaultConnection('tenant');

        TenantContext::setSite($site);
    }

    private function tenantConnectionValue(Site $site, string $connectionKey, string $siteAttribute): mixed
    {
        $value = $site->{$siteAttribute};

        if (filled($value)) {
            return $value;
        }

        return config("tenancy.default_tenant_connection.{$connectionKey}");
    }

    private function tenantDatabase(Site $site): string
    {
        if ($site->usesSharedPrefixedTenantDatabase()) {
            return filled($site->tenant_database)
                ? (string) $site->tenant_database
                : (string) config('tenancy.shared_database');
        }

        return (string) $site->tenant_database;
    }

    private function tenantTablePrefix(Site $site): string
    {
        if (! $site->usesSharedPrefixedTenantDatabase()) {
            return '';
        }

        $prefix = (string) $site->tenant_table_prefix;
        $pattern = (string) config('tenancy.table_prefix_pattern');

        if ($prefix === '' || preg_match($pattern, $prefix) !== 1) {
            throw new InvalidArgumentException(__('admin_common_ui.errors.tenant_table_prefix_invalid'));
        }

        return $prefix;
    }
}
