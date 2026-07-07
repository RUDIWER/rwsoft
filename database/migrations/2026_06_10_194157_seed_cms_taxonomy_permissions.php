<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');
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

            if (! $adminRoleId) {
                continue;
            }

            $permissionId = DB::table('acl_permissions')
                ->where('route_name', $permission['route_name'])
                ->value('id');

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
        $routeNames = collect($this->permissions())->pluck('route_name')->all();
        $permissionIds = DB::table('acl_permissions')
            ->whereIn('route_name', $routeNames)
            ->pluck('id');

        DB::table('acl_permission_role')
            ->whereIn('acl_permission_id', $permissionIds)
            ->delete();

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
            ['route_name' => 'admin.cms.categories.index', 'description' => '[CMS] Categorie overzicht', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.cms.categories.create', 'description' => '[CMS] Categorie toevoegen', 'action' => 'Toevoegen', 'menu' => false],
            ['route_name' => 'admin.cms.categories.edit', 'description' => '[CMS] Categorie bewerken', 'action' => 'Bewerken', 'menu' => false],
            ['route_name' => 'admin.cms.categories.store', 'description' => '[CMS] Categorie bewaren', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.tags.index', 'description' => '[CMS] Tag overzicht', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.cms.tags.create', 'description' => '[CMS] Tag toevoegen', 'action' => 'Toevoegen', 'menu' => false],
            ['route_name' => 'admin.cms.tags.edit', 'description' => '[CMS] Tag bewerken', 'action' => 'Bewerken', 'menu' => false],
            ['route_name' => 'admin.cms.tags.store', 'description' => '[CMS] Tag bewaren', 'action' => 'Bewaren', 'menu' => false],
        ];
    }
};
