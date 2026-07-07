<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
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
                ['acl_role_id' => $adminRoleId, 'acl_permission_id' => $permissionId],
                ['active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        $routeNames = collect($this->permissions())->pluck('route_name')->all();
        $permissionIds = DB::table('acl_permissions')->whereIn('route_name', $routeNames)->pluck('id');

        DB::table('acl_permission_role')->whereIn('acl_permission_id', $permissionIds)->delete();
        DB::table('acl_permissions')->whereIn('id', $permissionIds)->delete();
    }

    /**
     * @return array<int, array{route_name: string, description: string, action: string, menu: bool}>
     */
    private function permissions(): array
    {
        return [
            ['route_name' => 'admin.cms.languages.index', 'description' => '[CMS] Talen overzicht', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.cms.languages.create', 'description' => '[CMS] Taal toevoegen', 'action' => 'Toevoegen', 'menu' => false],
            ['route_name' => 'admin.cms.languages.edit', 'description' => '[CMS] Taal bewerken', 'action' => 'Bewerken', 'menu' => false],
            ['route_name' => 'admin.cms.languages.store', 'description' => '[CMS] Taal bewaren', 'action' => 'Bewaren', 'menu' => false],
        ];
    }
};
