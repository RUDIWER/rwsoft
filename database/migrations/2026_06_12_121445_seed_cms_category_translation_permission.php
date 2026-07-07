<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        $now = now();
        $adminRoleId = Schema::hasTable('acl_roles')
            ? DB::table('acl_roles')->where('key', 'admin')->value('id')
            : null;

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

        if (! $adminRoleId || ! Schema::hasTable('acl_permission_role')) {
            return;
        }

        $permissionId = DB::table('acl_permissions')
            ->where('route_name', 'admin.cms.categories.translations.store')
            ->value('id');

        if (! $permissionId) {
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        $permissionIds = DB::table('acl_permissions')
            ->where('route_name', 'admin.cms.categories.translations.store')
            ->pluck('id');

        if (Schema::hasTable('acl_permission_role')) {
            DB::table('acl_permission_role')
                ->whereIn('acl_permission_id', $permissionIds)
                ->delete();
        }

        DB::table('acl_permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }
};
