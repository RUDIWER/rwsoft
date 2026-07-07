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
            ['route_name' => 'admin.cms.media.edit', 'description' => '[CMS] Media bewerken', 'action' => 'Bewerken', 'menu' => false],
            ['route_name' => 'admin.cms.media.update', 'description' => '[CMS] Media metadata bewaren', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.media.destroy', 'description' => '[CMS] Media verwijderen', 'action' => 'Verwijderen', 'menu' => false],
        ];
    }
};
