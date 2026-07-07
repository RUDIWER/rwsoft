<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');
        $now = now();

        DB::table('acl_permissions')->updateOrInsert(
            ['route_name' => 'admin.cms.pages.destroy'],
            [
                'description' => '[CMS] Pagina verwijderen',
                'module' => 'CMS',
                'action' => 'Verwijderen',
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
            ->where('route_name', 'admin.cms.pages.destroy')
            ->value('id');

        DB::table('acl_permission_role')->updateOrInsert(
            ['acl_role_id' => $adminRoleId, 'acl_permission_id' => $permissionId],
            ['active' => true, 'created_at' => $now, 'updated_at' => $now],
        );
    }

    public function down(): void
    {
        $permissionIds = DB::table('acl_permissions')
            ->where('route_name', 'admin.cms.pages.destroy')
            ->pluck('id');

        DB::table('acl_permission_role')->whereIn('acl_permission_id', $permissionIds)->delete();
        DB::table('acl_permissions')->whereIn('id', $permissionIds)->delete();
    }
};
