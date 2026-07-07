<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     *
     * @var string
     */
    protected $connection = 'tenant';

    public function up(): void
    {
        $now = now();
        $routeName = 'admin.cms.health.index';

        DB::connection($this->connection)->table('acl_permissions')->updateOrInsert(
            ['route_name' => $routeName],
            [
                'route_name' => $routeName,
                'description' => '[CMS] Kwaliteit bekijken',
                'module' => 'CMS',
                'action' => 'Kwaliteit bekijken',
                'type' => 'core',
                'menu' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $adminRoleId = DB::connection($this->connection)->table('acl_roles')->where('key', 'admin')->value('id');
        $permissionId = DB::connection($this->connection)->table('acl_permissions')->where('route_name', $routeName)->value('id');

        if (! $adminRoleId || ! $permissionId) {
            return;
        }

        DB::connection($this->connection)->table('acl_permission_role')->updateOrInsert(
            ['acl_role_id' => $adminRoleId, 'acl_permission_id' => $permissionId],
            ['active' => true, 'created_at' => $now, 'updated_at' => $now],
        );
    }

    /**
     * This seed migration is intentionally forward-only: never remove ACL records automatically.
     */
    public function down(): void
    {
        //
    }
};
