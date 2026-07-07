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
        $this->seedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->deletePermissions();
    }

    private function seedPermissions(): void
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
                    'menu' => $permission['menu'],
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

    private function deletePermissions(): void
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
     * @return array<int, array{route_name: string, description: string, action: string, menu: bool}>
     */
    private function permissions(): array
    {
        return [
            ['route_name' => 'admin.cms.themes.index', 'description' => '[CMS] Thema overzicht', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.cms.themes.create', 'description' => '[CMS] Thema toevoegen', 'action' => 'Toevoegen', 'menu' => false],
            ['route_name' => 'admin.cms.themes.edit', 'description' => '[CMS] Thema bewerken', 'action' => 'Bewerken', 'menu' => false],
            ['route_name' => 'admin.cms.themes.store-new', 'description' => '[CMS] Thema toevoegen bewaren', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.themes.store', 'description' => '[CMS] Thema bewaren', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.themes.publish', 'description' => '[CMS] Thema publiceren', 'action' => 'Publiceren', 'menu' => false],
            ['route_name' => 'admin.cms.themes.activate', 'description' => '[CMS] Thema activeren', 'action' => 'Activeren', 'menu' => false],
            ['route_name' => 'admin.cms.themes.delete', 'description' => '[CMS] Thema verwijderen', 'action' => 'Verwijderen', 'menu' => false],
            ['route_name' => 'admin.cms.themes.preview', 'description' => '[CMS] Thema preview', 'action' => 'Preview', 'menu' => false],
            ['route_name' => 'admin.cms.themes.download', 'description' => '[CMS] Thema downloaden', 'action' => 'Downloaden', 'menu' => false],
            ['route_name' => 'admin.cms.themes.import', 'description' => '[CMS] Thema importeren', 'action' => 'Importeren', 'menu' => false],
            ['route_name' => 'admin.cms.themes.restore-version', 'description' => '[CMS] Thema versie herstellen', 'action' => 'Herstellen', 'menu' => false],
        ];
    }
};
