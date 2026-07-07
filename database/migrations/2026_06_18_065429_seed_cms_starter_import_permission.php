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

        DB::table('acl_permissions')->updateOrInsert(
            ['route_name' => 'admin.cms.settings.starter-import'],
            $this->permissionData($now),
        );

        if (! Schema::hasTable('acl_roles') || ! Schema::hasTable('acl_permission_role')) {
            return;
        }

        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');
        $permissionId = DB::table('acl_permissions')->where('route_name', 'admin.cms.settings.starter-import')->value('id');

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
            ],
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        $permissionId = DB::table('acl_permissions')->where('route_name', 'admin.cms.settings.starter-import')->value('id');

        if (! $permissionId) {
            return;
        }

        if (Schema::hasTable('acl_permission_role')) {
            DB::table('acl_permission_role')
                ->where('acl_permission_id', $permissionId)
                ->delete();
        }

        DB::table('acl_permissions')->where('id', $permissionId)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function permissionData(mixed $now): array
    {
        $data = [
            'description' => '[CMS] Starter-site importeren',
            'menu' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('acl_permissions', 'module_id')) {
            $data['module_id'] = $this->lookupId('acl_permission_modules', 'cms', 'CMS');
            $data['action_id'] = $this->lookupId('acl_permission_actions', 'import', 'Importeren');
            $data['type_id'] = $this->lookupId('acl_permission_types', 'core', 'Core');

            return $data;
        }

        $data['module'] = 'CMS';
        $data['action'] = 'Importeren';
        $data['type'] = 'core';

        return $data;
    }

    private function lookupId(string $table, string $key, string $name): ?int
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        DB::table($table)->updateOrInsert(
            ['key' => $key],
            [
                'name' => $name,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return DB::table($table)->where('key', $key)->value('id');
    }
};
