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
        $moduleId = $this->lookupId('acl_permission_modules', 'cms', 2);
        $typeId = $this->lookupId('acl_permission_types', 'core', 1);

        foreach ($this->permissions() as $permission) {
            DB::table('acl_permissions')->updateOrInsert(
                ['route_name' => $permission['route_name']],
                [
                    'description' => $permission['description'],
                    'module_id' => $moduleId,
                    'action_id' => $this->lookupId('acl_permission_actions', $permission['action_key'], $permission['action_id']),
                    'type_id' => $typeId,
                    'query_id' => null,
                    'menu' => false,
                    'url' => $permission['url'],
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

    private function lookupId(string $table, string $key, int $fallback): int
    {
        if (! Schema::hasTable($table)) {
            return $fallback;
        }

        return (int) (DB::table($table)->where('key', $key)->value('id') ?: $fallback);
    }

    /**
     * @return array<int, array{route_name: string, description: string, action_key: string, action_id: int, url: string}>
     */
    private function permissions(): array
    {
        return [
            ['route_name' => 'admin.cms.color-palette.index', 'description' => '[CMS] Kleurenpalet laden', 'action_key' => 'view', 'action_id' => 2, 'url' => 'admin/cms/color-palette'],
            ['route_name' => 'admin.cms.color-palette.store', 'description' => '[CMS] Kleur bewaren', 'action_key' => 'save', 'action_id' => 4, 'url' => 'admin/cms/color-palette'],
            ['route_name' => 'admin.cms.color-palette.destroy', 'description' => '[CMS] Kleur verwijderen', 'action_key' => 'delete', 'action_id' => 32, 'url' => 'admin/cms/color-palette/{item}'],
        ];
    }
};
