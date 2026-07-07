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

        foreach ($this->permissions() as $routeName => $permission) {
            DB::table('acl_permissions')->updateOrInsert(
                ['route_name' => $routeName],
                $this->permissionData($permission, $now),
            );
        }

        if (! Schema::hasTable('acl_roles') || ! Schema::hasTable('acl_permission_role')) {
            return;
        }

        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');

        if (! $adminRoleId) {
            return;
        }

        foreach (array_keys($this->permissions()) as $routeName) {
            $permissionId = DB::table('acl_permissions')->where('route_name', $routeName)->value('id');

            if (! $permissionId) {
                continue;
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
    }

    public function down(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        $permissionIds = DB::table('acl_permissions')
            ->whereIn('route_name', array_keys($this->permissions()))
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('acl_permission_role')) {
            DB::table('acl_permission_role')
                ->whereIn('acl_permission_id', $permissionIds)
                ->delete();
        }

        DB::table('acl_permissions')->whereIn('id', $permissionIds)->delete();
    }

    /**
     * @return array<string, array{description: string, action_key: string, action_name: string}>
     */
    private function permissions(): array
    {
        return [
            'admin.cms.settings.site-package-export' => [
                'description' => '[CMS] Site package exporteren',
                'action_key' => 'export',
                'action_name' => 'Exporteren',
            ],
            'admin.cms.settings.site-package-import' => [
                'description' => '[CMS] Site package importeren',
                'action_key' => 'import',
                'action_name' => 'Importeren',
            ],
        ];
    }

    /**
     * @param  array{description: string, action_key: string, action_name: string}  $permission
     * @return array<string, mixed>
     */
    private function permissionData(array $permission, mixed $now): array
    {
        $data = [
            'description' => $permission['description'],
            'menu' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('acl_permissions', 'module_id')) {
            $data['module_id'] = $this->lookupId('acl_permission_modules', 'cms', 'CMS');
            $data['action_id'] = $this->lookupId('acl_permission_actions', $permission['action_key'], $permission['action_name']);
            $data['type_id'] = $this->lookupId('acl_permission_types', 'core', 'Core');

            return $data;
        }

        $data['module'] = 'CMS';
        $data['action'] = $permission['action_name'];
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
