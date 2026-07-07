<?php

namespace App\Actions\Platform;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

class TestTenantDatabaseConnectionAction
{
    /**
     * @param  array<string, mixed>  $values
     * @return array{ok: bool, message: string}
     */
    public function handle(array $values): array
    {
        $connection = array_merge((array) config('database.connections.tenant', []), [
            'url' => filled($values['tenant_database_url'] ?? null)
                ? (string) $values['tenant_database_url']
                : config('tenancy.default_tenant_connection.url'),
            'host' => filled($values['tenant_database_host'] ?? null)
                ? (string) $values['tenant_database_host']
                : config('tenancy.default_tenant_connection.host'),
            'port' => filled($values['tenant_database_port'] ?? null)
                ? (int) $values['tenant_database_port']
                : config('tenancy.default_tenant_connection.port'),
            'database' => (string) $values['tenant_database'],
            'username' => filled($values['tenant_database_username'] ?? null)
                ? (string) $values['tenant_database_username']
                : config('tenancy.default_tenant_connection.username'),
            'password' => filled($values['tenant_database_password'] ?? null)
                ? (string) $values['tenant_database_password']
                : config('tenancy.default_tenant_connection.password'),
            'prefix' => '',
        ]);

        Config::set('database.connections.tenant_connection_test', $connection);
        DB::purge('tenant_connection_test');

        try {
            DB::connection('tenant_connection_test')->getPdo();

            return [
                'ok' => true,
                'message' => __('admin_common_ui.platform.sites.connection_test.success'),
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'message' => __('admin_common_ui.platform.sites.connection_test.failed'),
            ];
        } finally {
            DB::disconnect('tenant_connection_test');
            DB::purge('tenant_connection_test');
            Config::set('database.connections.tenant_connection_test', null);
        }
    }
}
