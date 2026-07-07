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

        foreach ($this->permissions() as $permission) {
            DB::table('acl_permissions')->updateOrInsert(
                ['route_name' => $permission['route_name']],
                [
                    'description' => $permission['description'],
                    'module' => 'CMS',
                    'action' => $permission['action'],
                    'type' => 'core',
                    'menu' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        if (! Schema::hasTable('acl_roles') || ! Schema::hasTable('acl_permission_role')) {
            return;
        }

        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');

        if (! $adminRoleId) {
            return;
        }

        $permissionIds = DB::table('acl_permissions')
            ->whereIn('route_name', collect($this->permissions())->pluck('route_name')->all())
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
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
            ->whereIn('route_name', collect($this->permissions())->pluck('route_name')->all())
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

    /**
     * @return array<int, array{route_name: string, description: string, action: string}>
     */
    private function permissions(): array
    {
        return [
            ['route_name' => 'admin.cms.layouts.translations.store', 'description' => '[CMS] Layout vertaling maken', 'action' => 'Vertalen'],
            ['route_name' => 'admin.cms.layouts.code-blocks.manage', 'description' => '[CMS] Layout codeblocks beheren', 'action' => 'Codeblocks beheren'],
        ];
    }
};
