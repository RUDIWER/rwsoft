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
                'route_name' => 'admin.translations.index',
                'description' => '[Admin] Vertalingen editor openen',
                'module' => 'Menu configuratie',
                'action' => 'Talen editor',
                'type' => 'core',
                'menu' => true,
                'url' => 'admin/dev/translations',
            ],
            [
                'route_name' => 'admin.translations.rows',
                'description' => '[Admin] Vertaalrijen laden',
                'module' => 'Menu configuratie',
                'action' => 'Talen laden',
                'type' => 'core',
                'menu' => false,
                'url' => 'admin/dev/translations/rows',
            ],
            [
                'route_name' => 'admin.translations.update',
                'description' => '[Admin] Vertaling inline bijwerken',
                'module' => 'Menu configuratie',
                'action' => 'Talen bijwerken',
                'type' => 'core',
                'menu' => false,
                'url' => 'admin/dev/translations/rows/{row}',
            ],
            [
                'route_name' => 'admin.translations.sync',
                'description' => '[Admin] Ontbrekende vertalingen synchroniseren',
                'module' => 'Menu configuratie',
                'action' => 'Talen sync',
                'type' => 'core',
                'menu' => false,
                'url' => 'admin/dev/translations/sync',
            ],
            [
                'route_name' => 'admin.translations.add-locale',
                'description' => '[Admin] Nieuwe taal toevoegen',
                'module' => 'Menu configuratie',
                'action' => 'Taal toevoegen',
                'type' => 'core',
                'menu' => false,
                'url' => 'admin/dev/translations/add-locale',
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

        $roles = DB::table('acl_roles')
            ->whereIn('key', ['admin', 'super_admin'])
            ->pluck('id')
            ->filter();

        if ($roles->isEmpty()) {
            return;
        }

        $permissionIds = DB::table('acl_permissions')
            ->whereIn('route_name', array_column($permissions, 'route_name'))
            ->pluck('id');

        foreach ($roles as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('acl_permission_role')->updateOrInsert(
                    [
                        'acl_role_id' => $roleId,
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $routeNames = [
            'admin.translations.index',
            'admin.translations.rows',
            'admin.translations.update',
            'admin.translations.sync',
            'admin.translations.add-locale',
        ];

        $permissionIds = DB::table('acl_permissions')
            ->whereIn('route_name', $routeNames)
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('acl_permission_role')
            ->whereIn('acl_permission_id', $permissionIds)
            ->delete();

        DB::table('acl_permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }
};
