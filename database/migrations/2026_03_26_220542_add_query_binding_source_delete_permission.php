<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $routeName = 'admin.queries.sources.delete';

        DB::table('acl_permissions')->updateOrInsert(
            ['route_name' => $routeName],
            [
                'description' => '[Admin] '.$routeName,
                'module' => 'Query Builder',
                'action' => 'Verwijderen',
                'type' => 'core',
                'menu' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permissionId = DB::table('acl_permissions')
            ->where('route_name', $routeName)
            ->value('id');
        $adminRoleId = DB::table('acl_roles')
            ->where('key', 'admin')
            ->value('id');

        if (! $permissionId || ! $adminRoleId) {
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
        $routeName = 'admin.queries.sources.delete';
        $permissionId = DB::table('acl_permissions')
            ->where('route_name', $routeName)
            ->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('acl_permission_role')
            ->where('acl_permission_id', $permissionId)
            ->delete();

        DB::table('acl_permissions')
            ->where('id', $permissionId)
            ->delete();
    }
};
