<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = DB::table('acl_permissions')
            ->where('route_name', 'admin.db-diagram.table-view')
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('acl_permission_role')
            ->whereIn('acl_permission_id', $permissionIds)
            ->delete();

        DB::table('acl_permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }

    public function down(): void
    {
        $now = now();

        DB::table('acl_permissions')->updateOrInsert(
            ['route_name' => 'admin.db-diagram.table-view'],
            [
                'description' => '[Admin] Database tabel tonen',
                'module' => 'DB Diagram',
                'action' => 'Overzicht',
                'type' => 'core',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/table/{table}',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');
        $permissionId = DB::table('acl_permissions')
            ->where('route_name', 'admin.db-diagram.table-view')
            ->value('id');

        if (! $adminRoleId || ! $permissionId) {
            return;
        }

        DB::table('acl_permission_role')->updateOrInsert(
            [
                'acl_role_id' => $adminRoleId,
                'acl_permission_id' => $permissionId,
            ],
            [
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
};
