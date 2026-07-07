<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');

        if (! $adminRoleId) {
            return;
        }

        $now = now();

        foreach ($this->routes() as $routeName => $action) {
            DB::table('acl_permissions')->updateOrInsert(
                ['route_name' => $routeName],
                [
                    'description' => '[Admin] '.$routeName,
                    'module' => 'Query Builder',
                    'action' => $action,
                    'type' => 'core',
                    'menu' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $permissionId = DB::table('acl_permissions')
                ->where('route_name', $routeName)
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

    public function down(): void
    {
        $routeNames = array_keys($this->routes());

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
     * @return array<string, string>
     */
    private function routes(): array
    {
        return [
            'admin.queries.sources.index' => 'Overzicht',
            'admin.queries.sources.create' => 'Bewerken',
            'admin.queries.sources.edit' => 'Bewerken',
            'admin.queries.sources.store-new' => 'Bewaren',
            'admin.queries.sources.store' => 'Bewaren',
            'admin.queries.sources.delete' => 'Verwijderen',
        ];
    }
};
