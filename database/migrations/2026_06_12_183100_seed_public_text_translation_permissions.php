<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            [
                'route_name' => 'admin.translations.public.rows',
                'description' => '[Admin] Openbare site vertaalrijen laden',
                'module' => 'Menu configuratie',
                'action' => 'Openbare teksten laden',
                'type' => 'core',
                'menu' => false,
                'url' => 'admin/translations/public/rows',
            ],
            [
                'route_name' => 'admin.translations.public.update',
                'description' => '[Admin] Openbare site vertaling inline bijwerken',
                'module' => 'Menu configuratie',
                'action' => 'Openbare teksten bijwerken',
                'type' => 'core',
                'menu' => false,
                'url' => 'admin/translations/public/rows/{row}',
            ],
            [
                'route_name' => 'admin.translations.public.sync',
                'description' => '[Admin] Openbare site vertalingen synchroniseren',
                'module' => 'Menu configuratie',
                'action' => 'Openbare teksten sync',
                'type' => 'core',
                'menu' => false,
                'url' => 'admin/translations/public/sync',
            ],
            [
                'route_name' => 'admin.translations.public.ai-fill',
                'description' => '[Admin] Openbare site vertalingen met AI aanvullen',
                'module' => 'Menu configuratie',
                'action' => 'Openbare teksten AI',
                'type' => 'core',
                'menu' => false,
                'url' => 'admin/translations/public/ai-fill',
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
                ],
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
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        // Permission seed migrations are intentionally forward-only in this project phase.
    }
};
