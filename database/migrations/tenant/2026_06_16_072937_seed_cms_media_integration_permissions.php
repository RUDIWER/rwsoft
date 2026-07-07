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

        foreach ($this->permissions() as $permission) {
            DB::table('acl_permissions')->updateOrInsert(
                ['route_name' => $permission['route_name']],
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

        $permissionIds = DB::table('acl_permissions')
            ->whereIn('route_name', collect($this->permissions())->pluck('route_name'))
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
     * @param  array{route_name: string, description: string, action: string, action_key: string}  $permission
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
            $data['action_id'] = $this->lookupId('acl_permission_actions', $permission['action_key'], $permission['action']);
            $data['type_id'] = $this->lookupId('acl_permission_types', 'core', 'Core');

            return $data;
        }

        $data['module'] = 'CMS';
        $data['action'] = $permission['action'];
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

    /**
     * @return array<int, array{route_name: string, description: string, action: string, action_key: string}>
     */
    private function permissions(): array
    {
        return [
            ['route_name' => 'admin.cms.media.sort', 'description' => '[CMS] Media sorteren', 'action' => 'Sorteren', 'action_key' => 'sort'],
            ['route_name' => 'admin.cms.media.metadata', 'description' => '[CMS] Media metadata via dialog bewaren', 'action' => 'Bewaren', 'action_key' => 'save'],
            ['route_name' => 'admin.cms.media-folders.update', 'description' => '[CMS] Media map hernoemen', 'action' => 'Bewerken', 'action_key' => 'edit'],
            ['route_name' => 'admin.cms.media-folders.move', 'description' => '[CMS] Media map verplaatsen', 'action' => 'Verplaatsen', 'action_key' => 'move'],
        ];
    }
};
