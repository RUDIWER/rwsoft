<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('acl_permissions')->updateOrInsert(
            ['route_name' => 'admin.db-diagram.table-create-crud'],
            [
                'description' => '[Admin] Database CRUD applicatie genereren',
                'module' => 'DB Diagram',
                'action' => 'Aanmaken',
                'type' => 'core',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/table/{table}/create-crud',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');
        $permissionId = DB::table('acl_permissions')
            ->where('route_name', 'admin.db-diagram.table-create-crud')
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

    public function down(): void
    {
        $permissionIds = DB::table('acl_permissions')
            ->where('route_name', 'admin.db-diagram.table-create-crud')
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
};
