<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');

        if (! $adminRoleId) {
            return;
        }

        $now = now();
        $routeName = 'admin.run.dashboard';

        DB::table('acl_permissions')->updateOrInsert(
            ['route_name' => $routeName],
            [
                'description' => '[Admin] '.$routeName,
                'module' => 'Run',
                'action' => 'Overzicht',
                'type' => 'core',
                'menu' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permissionId = DB::table('acl_permissions')->where('route_name', $routeName)->value('id');

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
        $permissionId = DB::table('acl_permissions')
            ->where('route_name', 'admin.run.dashboard')
            ->value('id');

        if ($permissionId) {
            DB::table('acl_permission_role')
                ->where('acl_permission_id', $permissionId)
                ->delete();

            DB::table('acl_permissions')
                ->where('id', $permissionId)
                ->delete();
        }
    }
};
