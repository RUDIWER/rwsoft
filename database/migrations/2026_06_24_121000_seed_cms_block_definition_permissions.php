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
        $this->removeOldVariantPermissions();
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
                    'menu' => $permission['menu'],
                    'url' => $permission['url'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $this->grantAdminRole($now);
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

    private function removeOldVariantPermissions(): void
    {
        $permissionIds = DB::table('acl_permissions')
            ->where('route_name', 'like', 'admin.cms.block-variants.%')
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('acl_permission_role')) {
            DB::table('acl_permission_role')
                ->whereIn('acl_permission_id', $permissionIds)
                ->delete();
        }

        DB::table('acl_permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }

    private function grantAdminRole(mixed $now): void
    {
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
                ['acl_role_id' => $adminRoleId, 'acl_permission_id' => $permissionId],
                ['active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    private function lookupId(string $table, string $key, int $fallback): int
    {
        if (! Schema::hasTable($table)) {
            return $fallback;
        }

        return (int) (DB::table($table)->where('key', $key)->value('id') ?: $fallback);
    }

    /**
     * @return array<int, array{route_name: string, description: string, action_key: string, action_id: int, menu: bool, url: string}>
     */
    private function permissions(): array
    {
        return [
            ['route_name' => 'admin.cms.blocks.index', 'description' => '[CMS] Blokken overzicht', 'action_key' => 'index', 'action_id' => 19, 'menu' => true, 'url' => 'admin/cms/blocks'],
            ['route_name' => 'admin.cms.blocks.create', 'description' => '[CMS] Blok toevoegen', 'action_key' => 'add', 'action_id' => 28, 'menu' => false, 'url' => 'admin/cms/blocks/create'],
            ['route_name' => 'admin.cms.blocks.edit', 'description' => '[CMS] Blok bewerken', 'action_key' => 'edit', 'action_id' => 5, 'menu' => false, 'url' => 'admin/cms/blocks/{block}/edit'],
            ['route_name' => 'admin.cms.blocks.store-new', 'description' => '[CMS] Blok toevoegen bewaren', 'action_key' => 'save', 'action_id' => 4, 'menu' => false, 'url' => 'admin/cms/blocks/store'],
            ['route_name' => 'admin.cms.blocks.store', 'description' => '[CMS] Blok bewaren', 'action_key' => 'save', 'action_id' => 4, 'menu' => false, 'url' => 'admin/cms/blocks/{block}/store'],
            ['route_name' => 'admin.cms.blocks.publish', 'description' => '[CMS] Blok publiceren', 'action_key' => 'publish', 'action_id' => 21, 'menu' => false, 'url' => 'admin/cms/blocks/{block}/publish'],
            ['route_name' => 'admin.cms.blocks.restore-revision', 'description' => '[CMS] Blokrevisie herstellen', 'action_key' => 'restore', 'action_id' => 29, 'menu' => false, 'url' => 'admin/cms/blocks/{block}/revisions/{revision}/restore'],
        ];
    }
};
