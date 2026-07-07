<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');

        DB::table('acl_permissions')->updateOrInsert(
            ['route_name' => 'admin.cms.categories.translations.store'],
            [
                'description' => '[CMS] Categorie vertaling aanmaken',
                'module' => 'CMS',
                'action' => 'Vertalen',
                'type' => 'core',
                'menu' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        if (! $adminRoleId) {
            return;
        }

        $permissionId = DB::table('acl_permissions')
            ->where('route_name', 'admin.cms.categories.translations.store')
            ->value('id');

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
            ->where('route_name', 'admin.cms.categories.translations.store')
            ->pluck('id');

        DB::table('acl_permission_role')
            ->whereIn('acl_permission_id', $permissionIds)
            ->delete();

        DB::table('acl_permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }
};
