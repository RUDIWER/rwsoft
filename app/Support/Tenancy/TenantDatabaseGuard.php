<?php

namespace App\Support\Tenancy;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantDatabaseGuard
{
    public static function ensureTenantConnection(): void
    {
        $tenantDatabase = TenantContext::database();

        if (! TenantContext::isResolved() || $tenantDatabase === null || trim($tenantDatabase) === '') {
            throw new RuntimeException(__('admin_common_ui.errors.tenant_context_inactive'));
        }

        if (DB::getDefaultConnection() !== 'tenant') {
            throw new RuntimeException(__('admin_common_ui.errors.not_tenant_connection'));
        }

        $configuredDatabase = (string) config('database.connections.tenant.database', '');
        if ($configuredDatabase !== $tenantDatabase) {
            throw new RuntimeException(__('admin_common_ui.errors.tenant_database_config_mismatch'));
        }

        $activeDatabase = (string) DB::connection('tenant')->getDatabaseName();
        if ($activeDatabase !== $tenantDatabase) {
            throw new RuntimeException(__('admin_common_ui.errors.tenant_database_connection_mismatch'));
        }

        $configuredPrefix = (string) config('database.connections.tenant.prefix', '');
        $tenantPrefix = (string) (TenantContext::tablePrefix() ?? '');

        if ($configuredPrefix !== $tenantPrefix) {
            throw new RuntimeException(__('admin_common_ui.errors.tenant_table_prefix_config_mismatch'));
        }
    }
}
