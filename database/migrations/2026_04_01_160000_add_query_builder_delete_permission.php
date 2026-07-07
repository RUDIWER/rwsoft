<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acl_permissions') || ! Schema::hasTable('acl_roles') || ! Schema::hasTable('acl_permission_role')) {
            return;
        }

        $now = now();

        DB::table('acl_permissions')->updateOrInsert(
            ['route_name' => 'admin.queries.builder.delete'],
            [
                'description' => '[Admin] admin.queries.builder.delete',
                'module' => 'Query Builder',
                'action' => 'Verwijderen',
                'type' => 'core',
                'menu' => false,
                'url' => 'admin/dev/query-builder/{query}/delete',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');
        $permissionId = DB::table('acl_permissions')
            ->where('route_name', 'admin.queries.builder.delete')
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
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        $permissionId = DB::table('acl_permissions')
            ->where('route_name', 'admin.queries.builder.delete')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        if (Schema::hasTable('acl_permission_role')) {
            DB::table('acl_permission_role')
                ->where('acl_permission_id', $permissionId)
                ->delete();
        }

        DB::table('acl_permissions')
            ->where('id', $permissionId)
            ->delete();
    }
};
