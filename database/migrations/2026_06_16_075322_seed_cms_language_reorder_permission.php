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

        $now = now();

        if (Schema::hasTable('acl_permission_actions')) {
            DB::table('acl_permission_actions')->updateOrInsert(
                ['key' => 'sort'],
                [
                    'name' => 'Sorteren',
                    'sort_order' => 330,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $sortActionId = Schema::hasTable('acl_permission_actions')
            ? DB::table('acl_permission_actions')->where('key', 'sort')->value('id')
            : null;

        DB::table('acl_permissions')->updateOrInsert(
            ['route_name' => 'admin.cms.languages.reorder'],
            array_filter([
                'description' => '[CMS] Talen sorteren',
                'module_id' => Schema::hasColumn('acl_permissions', 'module_id') ? 2 : null,
                'action_id' => Schema::hasColumn('acl_permissions', 'action_id') ? $sortActionId : null,
                'type_id' => Schema::hasColumn('acl_permissions', 'type_id') ? 1 : null,
                'module' => Schema::hasColumn('acl_permissions', 'module') ? 'CMS' : null,
                'action' => Schema::hasColumn('acl_permissions', 'action') ? 'Sorteren' : null,
                'type' => Schema::hasColumn('acl_permissions', 'type') ? 'core' : null,
                'query_id' => null,
                'menu' => false,
                'url' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], fn (mixed $value): bool => $value !== null)
        );

        $this->grantAdminRole();
    }

    public function down(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        $permissionId = DB::table('acl_permissions')
            ->where('route_name', 'admin.cms.languages.reorder')
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

    private function grantAdminRole(): void
    {
        if (! Schema::hasTable('acl_roles') || ! Schema::hasTable('acl_permission_role')) {
            return;
        }

        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');
        $permissionId = DB::table('acl_permissions')
            ->where('route_name', 'admin.cms.languages.reorder')
            ->value('id');

        if (! $adminRoleId || ! $permissionId) {
            return;
        }

        $now = now();

        DB::table('acl_permission_role')->updateOrInsert(
            ['acl_role_id' => $adminRoleId, 'acl_permission_id' => $permissionId],
            ['active' => true, 'created_at' => $now, 'updated_at' => $now],
        );
    }
};
