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

        $roles = [
            ['key' => 'super_admin', 'name' => 'Super Admin', 'description' => 'Volledige toegang op het backoffice.'],
            ['key' => 'admin', 'name' => 'Admin', 'description' => 'Standaard beheerder met route-gebaseerde rechten.'],
            ['key' => 'editor', 'name' => 'Editor', 'description' => 'Kan content en gebruikersdata beheren binnen toegekende rechten.'],
        ];

        foreach ($roles as $role) {
            DB::table('acl_roles')->updateOrInsert(
                ['key' => $role['key']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');

        if (! $adminRoleId) {
            return;
        }

        foreach ($this->adminRoutes() as $routeName) {
            DB::table('acl_permissions')->updateOrInsert(
                ['route_name' => $routeName],
                [
                    'description' => '[Admin] '.$routeName,
                    'module' => $this->resolveModule($routeName),
                    'action' => $this->resolveAction($routeName),
                    'type' => 'core',
                    'menu' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $permissionId = DB::table('acl_permissions')->where('route_name', $routeName)->value('id');

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

        foreach (DB::table('users')->pluck('id') as $userId) {
            DB::table('acl_role_user')->updateOrInsert(
                [
                    'user_id' => $userId,
                    'acl_role_id' => $adminRoleId,
                ],
                [
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
        $routeNames = $this->adminRoutes();

        $permissionIds = DB::table('acl_permissions')
            ->whereIn('route_name', $routeNames)
            ->pluck('id');

        DB::table('acl_permission_role')
            ->whereIn('acl_permission_id', $permissionIds)
            ->delete();

        DB::table('acl_permissions')
            ->whereIn('id', $permissionIds)
            ->delete();

        DB::table('acl_role_user')->delete();

        DB::table('acl_roles')
            ->whereIn('key', ['super_admin', 'admin', 'editor'])
            ->delete();
    }

    /**
     * @return array<int, string>
     */
    private function adminRoutes(): array
    {
        return [
            'admin',
            'admin.users',
            'admin.users.store',
            'admin.users.edit',
            'admin.roles',
            'admin.roles.store',
            'admin.roles.edit',
            'admin.permissions',
            'admin.permissions.store',
            'admin.permissions.edit',
        ];
    }

    private function resolveModule(string $routeName): string
    {
        if ($routeName === 'admin') {
            return 'Dashboard';
        }

        $parts = explode('.', $routeName);

        return ucfirst($parts[1] ?? 'Admin');
    }

    private function resolveAction(string $routeName): string
    {
        $parts = explode('.', $routeName);
        $action = end($parts);

        return match ($action) {
            'store' => 'Bewaren',
            'edit' => 'Bewerken',
            default => 'Overzicht',
        };
    }
};
