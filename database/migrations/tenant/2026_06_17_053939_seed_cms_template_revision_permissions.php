<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('acl_permissions')) {
            return;
        }

        $adminRoleId = Schema::connection($this->connection)->hasTable('acl_roles')
            ? DB::connection($this->connection)->table('acl_roles')->where('key', 'admin')->value('id')
            : null;
        $now = now();

        foreach ($this->permissions() as $permission) {
            DB::connection($this->connection)->table('acl_permissions')->updateOrInsert(
                ['route_name' => $permission['route_name']],
                [
                    'description' => $permission['description'],
                    'menu' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        if (! $adminRoleId || ! Schema::connection($this->connection)->hasTable('acl_permission_role')) {
            return;
        }

        $permissionIds = DB::connection($this->connection)->table('acl_permissions')
            ->whereIn('route_name', array_column($this->permissions(), 'route_name'))
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::connection($this->connection)->table('acl_permission_role')->updateOrInsert(
                ['acl_role_id' => $adminRoleId, 'acl_permission_id' => $permissionId],
                ['active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('acl_permissions')) {
            return;
        }

        $permissionIds = DB::connection($this->connection)->table('acl_permissions')
            ->whereIn('route_name', array_column($this->permissions(), 'route_name'))
            ->pluck('id');

        if (Schema::connection($this->connection)->hasTable('acl_permission_role')) {
            DB::connection($this->connection)->table('acl_permission_role')->whereIn('acl_permission_id', $permissionIds)->delete();
        }

        DB::connection($this->connection)->table('acl_permissions')->whereIn('id', $permissionIds)->delete();
    }

    /**
     * @return array<int, array{route_name: string, description: string}>
     */
    private function permissions(): array
    {
        return [
            ['route_name' => 'admin.cms.templates.revisions.index', 'description' => '[CMS] Template versies bekijken'],
            ['route_name' => 'admin.cms.templates.revisions.restore', 'description' => '[CMS] Template versie herstellen'],
        ];
    }
};
