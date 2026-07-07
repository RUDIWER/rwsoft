<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $this->seedPermissions();
    }

    /**
     * This seed migration is intentionally forward-only: never remove ACL records automatically.
     */
    public function down(): void
    {
        //
    }

    private function seedPermissions(): void
    {
        if (! Schema::connection($this->connection)->hasTable('acl_permissions')) {
            return;
        }

        $now = now();
        $permissions = $this->permissions();

        foreach ($permissions as $permission) {
            DB::connection($this->connection)->table('acl_permissions')->updateOrInsert(
                ['route_name' => $permission['route_name']],
                [
                    'route_name' => $permission['route_name'],
                    'description' => $permission['description'],
                    'module_id' => $permission['module_id'],
                    'action_id' => $permission['action_id'],
                    'type_id' => 1,
                    'query_id' => null,
                    'menu' => $permission['menu'],
                    'url' => $permission['url'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $this->grantRoles(array_column($permissions, 'route_name'), $now);
    }

    /**
     * @param  array<int, string>  $routeNames
     */
    private function grantRoles(array $routeNames, mixed $now): void
    {
        if (! Schema::connection($this->connection)->hasTable('acl_roles') || ! Schema::connection($this->connection)->hasTable('acl_permission_role')) {
            return;
        }

        $roleIds = DB::connection($this->connection)->table('acl_roles')
            ->whereIn('key', ['admin', 'super_admin'])
            ->pluck('id');
        $permissionIds = DB::connection($this->connection)->table('acl_permissions')
            ->whereIn('route_name', $routeNames)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::connection($this->connection)->table('acl_permission_role')->updateOrInsert(
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

    /**
     * @return array<int, array{route_name: string, description: string, module_id: int, action_id: int, menu: bool, url: string|null}>
     */
    private function permissions(): array
    {
        return [
            ['route_name' => 'admin.translations.index', 'description' => '[Admin] Vertalingen editor openen', 'module_id' => 5, 'action_id' => 25, 'menu' => true, 'url' => 'admin/translations'],
            ['route_name' => 'admin.translations.public.rows', 'description' => '[Admin] Openbare tekstvertalingen laden', 'module_id' => 5, 'action_id' => 17, 'menu' => false, 'url' => 'admin/translations/public/rows'],
            ['route_name' => 'admin.translations.public.update', 'description' => '[Admin] Openbare tekstvertaling bijwerken', 'module_id' => 5, 'action_id' => 16, 'menu' => false, 'url' => 'admin/translations/public/rows/{row}'],
            ['route_name' => 'admin.translations.public.sync', 'description' => '[Admin] Openbare tekstvertalingen synchroniseren', 'module_id' => 5, 'action_id' => 18, 'menu' => false, 'url' => 'admin/translations/public/sync'],
            ['route_name' => 'admin.translations.public.ai-fill', 'description' => '[Admin] Openbare tekstvertalingen met AI aanvullen', 'module_id' => 5, 'action_id' => 15, 'menu' => false, 'url' => 'admin/translations/public/ai-fill'],
            ['route_name' => 'admin.translations.content.rows', 'description' => '[Admin] Contentvertalingen laden', 'module_id' => 2, 'action_id' => 26, 'menu' => false, 'url' => 'admin/translations/content/rows'],
            ['route_name' => 'admin.translations.content.store', 'description' => '[Admin] Contentvertaling aanmaken', 'module_id' => 2, 'action_id' => 31, 'menu' => false, 'url' => 'admin/translations/content'],
            ['route_name' => 'admin.translations.content.bulk-ai', 'description' => '[Admin] Contentvertalingen met AI in bulk aanmaken', 'module_id' => 2, 'action_id' => 31, 'menu' => false, 'url' => 'admin/translations/content/bulk-ai'],
            ['route_name' => 'admin.translations.content.mark-reviewed', 'description' => '[Admin] AI contentvertaling als nagekeken markeren', 'module_id' => 2, 'action_id' => 31, 'menu' => false, 'url' => 'admin/translations/content/mark-reviewed'],
        ];
    }
};
