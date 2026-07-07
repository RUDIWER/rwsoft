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
        $now = now();

        $permissions = [
            [
                'route_name' => 'admin.client-rules.index',
                'description' => '[Admin] Client validatie regels editor openen',
                'module' => 'Screen Builder',
                'action' => 'Client Rules Editor',
                'type' => 'core',
                'menu' => true,
                'url' => 'admin/dev/client-validation-rules',
            ],
            [
                'route_name' => 'admin.client-rules.code',
                'description' => '[Admin] Client validatie rule code laden',
                'module' => 'Screen Builder',
                'action' => 'Code laden',
                'type' => 'core',
                'menu' => false,
                'url' => 'admin/dev/client-validation-rules/code',
            ],
            [
                'route_name' => 'admin.client-rules.save',
                'description' => '[Admin] Client validatie rule versie bewaren',
                'module' => 'Screen Builder',
                'action' => 'Bewaren',
                'type' => 'core',
                'menu' => false,
                'url' => 'admin/dev/client-validation-rules/save',
            ],
            [
                'route_name' => 'admin.client-rules.publish',
                'description' => '[Admin] Client validatie rule versie publiceren',
                'module' => 'Screen Builder',
                'action' => 'Publiceren',
                'type' => 'core',
                'menu' => false,
                'url' => 'admin/dev/client-validation-rules/publish',
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('acl_permissions')->updateOrInsert(
                ['route_name' => $permission['route_name']],
                [
                    'description' => $permission['description'],
                    'module' => $permission['module'],
                    'action' => $permission['action'],
                    'type' => $permission['type'],
                    'menu' => $permission['menu'],
                    'url' => $permission['url'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');

        if (! $adminRoleId) {
            return;
        }

        $permissionIds = DB::table('acl_permissions')
            ->whereIn('route_name', array_column($permissions, 'route_name'))
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
        $routeNames = [
            'admin.client-rules.index',
            'admin.client-rules.code',
            'admin.client-rules.save',
            'admin.client-rules.publish',
        ];

        $permissionIds = DB::table('acl_permissions')
            ->whereIn('route_name', $routeNames)
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('acl_permission_role')
                ->whereIn('acl_permission_id', $permissionIds)
                ->delete();

            DB::table('acl_permissions')
                ->whereIn('id', $permissionIds)
                ->delete();
        }
    }
};
