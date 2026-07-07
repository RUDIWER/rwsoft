<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('acl_permissions')) {
            return;
        }

        $this->syncPermissions();
    }

    public function down(): void
    {
        // Menu permissions are intentionally kept; this only fixes active ACL data.
    }

    private function syncPermissions(): void
    {
        $now = now();
        $oldRoleAssignments = $this->oldRoleAssignments();

        foreach ($this->permissions() as $permission) {
            DB::connection($this->connection)->table('acl_permissions')->updateOrInsert(
                ['route_name' => $permission['route_name']],
                $this->permissionPayload($permission, $now)
            );
        }

        DB::connection($this->connection)->table('acl_permissions')
            ->where('route_name', 'like', 'admin.cms.block-variants.%')
            ->update(['menu' => false, 'updated_at' => $now]);

        $this->copyOldRoleAssignments($oldRoleAssignments, $now);
        $this->grantAdminRole($now);
        $this->deactivateOldRoleAssignments($now);
    }

    /**
     * @return array<string, mixed>
     */
    private function permissionPayload(array $permission, mixed $now): array
    {
        $payload = [
            'description' => $permission['description'],
            'query_id' => null,
            'menu' => $permission['menu'],
            'url' => $permission['url'],
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::connection($this->connection)->hasColumn('acl_permissions', 'module_id')) {
            $payload['module_id'] = $this->lookupId('acl_permission_modules', 'cms', 2);
        } elseif (Schema::connection($this->connection)->hasColumn('acl_permissions', 'module')) {
            $payload['module'] = 'CMS';
        }

        if (Schema::connection($this->connection)->hasColumn('acl_permissions', 'action_id')) {
            $payload['action_id'] = $this->lookupId('acl_permission_actions', $permission['action_key'], $permission['action_id']);
        } elseif (Schema::connection($this->connection)->hasColumn('acl_permissions', 'action')) {
            $payload['action'] = $permission['action_label'];
        }

        if (Schema::connection($this->connection)->hasColumn('acl_permissions', 'type_id')) {
            $payload['type_id'] = $this->lookupId('acl_permission_types', 'core', 1);
        } elseif (Schema::connection($this->connection)->hasColumn('acl_permissions', 'type')) {
            $payload['type'] = 'core';
        }

        return $payload;
    }

    private function lookupId(string $table, string $key, int $fallback): int
    {
        if (! Schema::connection($this->connection)->hasTable($table)) {
            return $fallback;
        }

        return (int) (DB::connection($this->connection)->table($table)->where('key', $key)->value('id') ?: $fallback);
    }

    /**
     * @return array<int, object>
     */
    private function oldRoleAssignments(): array
    {
        if (! Schema::connection($this->connection)->hasTable('acl_permission_role')) {
            return [];
        }

        return DB::connection($this->connection)->table('acl_permission_role')
            ->select('acl_permission_role.acl_role_id', 'acl_permission_role.active', 'acl_permissions.route_name')
            ->join('acl_permissions', 'acl_permissions.id', '=', 'acl_permission_role.acl_permission_id')
            ->whereIn('acl_permissions.route_name', array_keys($this->oldToNewRoutes()))
            ->get()
            ->all();
    }

    /**
     * @param  array<int, object>  $oldRoleAssignments
     */
    private function copyOldRoleAssignments(array $oldRoleAssignments, mixed $now): void
    {
        if ($oldRoleAssignments === [] || ! Schema::connection($this->connection)->hasTable('acl_permission_role')) {
            return;
        }

        $newPermissionIds = DB::connection($this->connection)->table('acl_permissions')
            ->whereIn('route_name', array_values($this->oldToNewRoutes()))
            ->pluck('id', 'route_name');

        foreach ($oldRoleAssignments as $assignment) {
            $newRoute = $this->oldToNewRoutes()[$assignment->route_name] ?? null;
            $newPermissionId = $newRoute ? $newPermissionIds->get($newRoute) : null;

            if (! $newPermissionId) {
                continue;
            }

            DB::connection($this->connection)->table('acl_permission_role')->updateOrInsert(
                ['acl_role_id' => $assignment->acl_role_id, 'acl_permission_id' => $newPermissionId],
                ['active' => (bool) $assignment->active, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    private function deactivateOldRoleAssignments(mixed $now): void
    {
        if (! Schema::connection($this->connection)->hasTable('acl_permission_role')) {
            return;
        }

        $oldPermissionIds = DB::connection($this->connection)->table('acl_permissions')
            ->whereIn('route_name', array_keys($this->oldToNewRoutes()))
            ->pluck('id');

        if ($oldPermissionIds->isEmpty()) {
            return;
        }

        DB::connection($this->connection)->table('acl_permission_role')
            ->whereIn('acl_permission_id', $oldPermissionIds->all())
            ->update(['active' => false, 'updated_at' => $now]);
    }

    private function grantAdminRole(mixed $now): void
    {
        if (! Schema::connection($this->connection)->hasTable('acl_roles') || ! Schema::connection($this->connection)->hasTable('acl_permission_role')) {
            return;
        }

        $adminRoleId = DB::connection($this->connection)->table('acl_roles')->where('key', 'admin')->value('id');

        if (! $adminRoleId) {
            return;
        }

        $permissionIds = DB::connection($this->connection)->table('acl_permissions')
            ->whereIn('route_name', collect($this->permissions())->pluck('route_name')->all())
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::connection($this->connection)->table('acl_permission_role')->updateOrInsert(
                ['acl_role_id' => $adminRoleId, 'acl_permission_id' => $permissionId],
                ['active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function oldToNewRoutes(): array
    {
        return [
            'admin.cms.block-variants.index' => 'admin.cms.blocks.index',
            'admin.cms.block-variants.create' => 'admin.cms.blocks.create',
            'admin.cms.block-variants.edit' => 'admin.cms.blocks.edit',
            'admin.cms.block-variants.store-new' => 'admin.cms.blocks.store-new',
            'admin.cms.block-variants.store' => 'admin.cms.blocks.store',
            'admin.cms.block-variants.publish' => 'admin.cms.blocks.publish',
            'admin.cms.block-variants.restore-revision' => 'admin.cms.blocks.restore-revision',
        ];
    }

    /**
     * @return array<int, array{route_name: string, description: string, action_key: string, action_id: int, action_label: string, menu: bool, url: string}>
     */
    private function permissions(): array
    {
        return [
            ['route_name' => 'admin.cms.blocks.index', 'description' => '[CMS] Blokken overzicht', 'action_key' => 'index', 'action_id' => 19, 'action_label' => 'Overzicht', 'menu' => true, 'url' => 'admin/cms/blocks'],
            ['route_name' => 'admin.cms.blocks.create', 'description' => '[CMS] Blok toevoegen', 'action_key' => 'add', 'action_id' => 28, 'action_label' => 'Toevoegen', 'menu' => false, 'url' => 'admin/cms/blocks/create'],
            ['route_name' => 'admin.cms.blocks.edit', 'description' => '[CMS] Blok bewerken', 'action_key' => 'edit', 'action_id' => 5, 'action_label' => 'Bewerken', 'menu' => false, 'url' => 'admin/cms/blocks/{block}/edit'],
            ['route_name' => 'admin.cms.blocks.store-new', 'description' => '[CMS] Blok toevoegen bewaren', 'action_key' => 'save', 'action_id' => 4, 'action_label' => 'Bewaren', 'menu' => false, 'url' => 'admin/cms/blocks/store'],
            ['route_name' => 'admin.cms.blocks.store', 'description' => '[CMS] Blok bewaren', 'action_key' => 'save', 'action_id' => 4, 'action_label' => 'Bewaren', 'menu' => false, 'url' => 'admin/cms/blocks/{block}/store'],
            ['route_name' => 'admin.cms.blocks.publish', 'description' => '[CMS] Blok publiceren', 'action_key' => 'publish', 'action_id' => 21, 'action_label' => 'Publiceren', 'menu' => false, 'url' => 'admin/cms/blocks/{block}/publish'],
            ['route_name' => 'admin.cms.blocks.restore-revision', 'description' => '[CMS] Blokrevisie herstellen', 'action_key' => 'restore', 'action_id' => 29, 'action_label' => 'Herstellen', 'menu' => false, 'url' => 'admin/cms/blocks/{block}/revisions/{revision}/restore'],
        ];
    }
};
