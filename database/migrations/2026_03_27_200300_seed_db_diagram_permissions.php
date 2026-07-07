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

        foreach ($this->permissions() as $permission) {
            DB::table('acl_permissions')->updateOrInsert(
                ['route_name' => $permission['route_name']],
                [
                    'description' => '[Admin] '.$permission['description'],
                    'module' => 'DB Diagram',
                    'action' => $permission['action'],
                    'type' => 'core',
                    'menu' => $permission['menu'],
                    'url' => $permission['url'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

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

    public function down(): void
    {
        $routeNames = array_map(static fn (array $permission): string => $permission['route_name'], $this->permissions());

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

    /**
     * @return array<int, array{route_name: string, description: string, action: string, menu: bool, url: string}>
     */
    private function permissions(): array
    {
        return [
            [
                'route_name' => 'admin.db-diagram',
                'description' => 'Database diagram',
                'action' => 'Overzicht',
                'menu' => true,
                'url' => 'admin/dev/db-diagram',
            ],
            [
                'route_name' => 'admin.db-diagram.sql-editor',
                'description' => 'Database SQL editor openen',
                'action' => 'Overzicht',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/sql',
            ],
            [
                'route_name' => 'admin.db-diagram.sql-execute',
                'description' => 'Database SQL readonly uitvoeren',
                'action' => 'Run',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/sql/execute',
            ],
            [
                'route_name' => 'admin.db-diagram.sql-execute-destructive',
                'description' => 'Database SQL destructief uitvoeren',
                'action' => 'Run',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/sql/execute-destructive',
            ],
            [
                'route_name' => 'admin.db-diagram.table-data',
                'description' => 'Database tabel data ophalen',
                'action' => 'Overzicht',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/table/{table}/data',
            ],
            [
                'route_name' => 'admin.db-diagram.table-export-sql',
                'description' => 'Database tabel SQL export',
                'action' => 'Export',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/table/{table}/export-sql',
            ],
            [
                'route_name' => 'admin.db-diagram.backup-full.start',
                'description' => 'Database volledige backup starten',
                'action' => 'Export',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/backup/full/start',
            ],
            [
                'route_name' => 'admin.db-diagram.backup-full.status',
                'description' => 'Database volledige backup status',
                'action' => 'Overzicht',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/backup/full/status/{id}',
            ],
            [
                'route_name' => 'admin.db-diagram.backup-full.download',
                'description' => 'Database volledige backup downloaden',
                'action' => 'Export',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/backup/full/download/{id}',
            ],
            [
                'route_name' => 'admin.database-logs',
                'description' => 'Database backup logs',
                'action' => 'Overzicht',
                'menu' => false,
                'url' => 'admin/dev/database-logs',
            ],
            [
                'route_name' => 'admin.db-diagram.table-create',
                'description' => 'Database record create form',
                'action' => 'Bewerken',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/table/{table}/create',
            ],
            [
                'route_name' => 'admin.db-diagram.table-edit-form',
                'description' => 'Database record edit form',
                'action' => 'Bewerken',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/table/{table}/{id}/edit',
            ],
            [
                'route_name' => 'admin.db-diagram.table-store',
                'description' => 'Database record opslaan',
                'action' => 'Bewaren',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/table/{table}/store',
            ],
            [
                'route_name' => 'admin.db-diagram.table-update-form',
                'description' => 'Database record update opslaan',
                'action' => 'Bewaren',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/table/{table}/{id}/store',
            ],
            [
                'route_name' => 'admin.db-diagram.table-analyze-update',
                'description' => 'Database update analyse',
                'action' => 'Inspecteren',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/table/{table}/analyze-update',
            ],
            [
                'route_name' => 'admin.db-diagram.table-analyze-delete',
                'description' => 'Database delete analyse',
                'action' => 'Inspecteren',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/table/{table}/{id}/analyze-delete',
            ],
            [
                'route_name' => 'admin.db-diagram.table-edit',
                'description' => 'Database inline update',
                'action' => 'Bewaren',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/table/{table}/{id}',
            ],
            [
                'route_name' => 'admin.db-diagram.table-delete',
                'description' => 'Database record verwijderen',
                'action' => 'Verwijderen',
                'menu' => false,
                'url' => 'admin/dev/db-diagram/table/{table}/{id}',
            ],
        ];
    }
};
