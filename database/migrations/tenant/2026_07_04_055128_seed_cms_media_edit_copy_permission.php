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
        DB::table('acl_permissions')->updateOrInsert(
            ['route_name' => 'admin.cms.media.edit-copy'],
            $this->permissionData($now),
        );

        if (! Schema::hasTable('acl_roles') || ! Schema::hasTable('acl_permission_role')) {
            return;
        }

        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');
        $permissionId = DB::table('acl_permissions')->where('route_name', 'admin.cms.media.edit-copy')->value('id');

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        $permissionId = DB::table('acl_permissions')->where('route_name', 'admin.cms.media.edit-copy')->value('id');

        if ($permissionId && Schema::hasTable('acl_permission_role')) {
            DB::table('acl_permission_role')->where('acl_permission_id', $permissionId)->delete();
        }

        DB::table('acl_permissions')->where('route_name', 'admin.cms.media.edit-copy')->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function permissionData(mixed $now): array
    {
        $data = [
            'description' => '[CMS] Media bewerkte kopie bewaren',
            'menu' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('acl_permissions', 'module_id')) {
            $data['module_id'] = $this->lookupId('acl_permission_modules', 'cms', 'CMS');
            $data['action_id'] = $this->lookupId('acl_permission_actions', 'save', 'Bewaren');
            $data['type_id'] = $this->lookupId('acl_permission_types', 'core', 'Core');

            return $data;
        }

        $data['module'] = 'CMS';
        $data['action'] = 'Bewaren';
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
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return DB::table($table)->where('key', $key)->value('id');
    }
};
