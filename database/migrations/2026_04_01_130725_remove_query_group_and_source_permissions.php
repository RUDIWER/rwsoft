<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        $routeNames = [
            'admin.queries.groups.index',
            'admin.queries.groups.create',
            'admin.queries.groups.edit',
            'admin.queries.groups.store-new',
            'admin.queries.groups.store',
            'admin.queries.groups.delete',
            'admin.queries.sources.index',
            'admin.queries.sources.create',
            'admin.queries.sources.edit',
            'admin.queries.sources.store-new',
            'admin.queries.sources.store',
            'admin.queries.sources.delete',
        ];

        $permissionIds = DB::table('acl_permissions')
            ->whereIn('route_name', $routeNames)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($permissionIds === []) {
            return;
        }

        if (Schema::hasTable('acl_permission_role')) {
            DB::table('acl_permission_role')
                ->whereIn('acl_permission_id', $permissionIds)
                ->delete();
        }

        DB::table('acl_permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }
    }
};
