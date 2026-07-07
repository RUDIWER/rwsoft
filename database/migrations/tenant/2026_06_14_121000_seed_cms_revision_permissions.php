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
        $adminRoleId = DB::connection($this->connection)->table('acl_roles')->where('key', 'admin')->value('id');
        $now = now();

        foreach ($this->permissions() as $permission) {
            DB::connection($this->connection)->table('acl_permissions')->updateOrInsert(
                ['route_name' => $permission['route_name']],
                array_merge($permission, [
                    'module' => 'CMS',
                    'type' => 'core',
                    'menu' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        if (! $adminRoleId) {
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

    public function down(): void
    {
        $permissionIds = DB::connection($this->connection)->table('acl_permissions')
            ->whereIn('route_name', array_column($this->permissions(), 'route_name'))
            ->pluck('id');

        DB::connection($this->connection)->table('acl_permission_role')->whereIn('acl_permission_id', $permissionIds)->delete();
        DB::connection($this->connection)->table('acl_permissions')->whereIn('id', $permissionIds)->delete();
    }

    /**
     * @return array<int, array{route_name: string, description: string, action: string}>
     */
    private function permissions(): array
    {
        return [
            ['route_name' => 'admin.cms.health.index', 'description' => '[CMS] Kwaliteit bekijken', 'action' => 'Kwaliteit bekijken'],
            ['route_name' => 'admin.cms.pages.revisions.index', 'description' => '[CMS] Pagina versies bekijken', 'action' => 'Versies bekijken'],
            ['route_name' => 'admin.cms.pages.revisions.restore', 'description' => '[CMS] Pagina versie herstellen', 'action' => 'Versie herstellen'],
            ['route_name' => 'admin.cms.layouts.revisions.index', 'description' => '[CMS] Layout versies bekijken', 'action' => 'Versies bekijken'],
            ['route_name' => 'admin.cms.layouts.revisions.restore', 'description' => '[CMS] Layout versie herstellen', 'action' => 'Versie herstellen'],
            ['route_name' => 'admin.cms.posts.revisions.index', 'description' => '[CMS] Bericht versies bekijken', 'action' => 'Versies bekijken'],
            ['route_name' => 'admin.cms.posts.revisions.restore', 'description' => '[CMS] Bericht versie herstellen', 'action' => 'Versie herstellen'],
            ['route_name' => 'admin.cms.menus.revisions.index', 'description' => '[CMS] Menu versies bekijken', 'action' => 'Versies bekijken'],
            ['route_name' => 'admin.cms.menus.revisions.restore', 'description' => '[CMS] Menu versie herstellen', 'action' => 'Versie herstellen'],
            ['route_name' => 'admin.cms.forms.revisions.index', 'description' => '[CMS] Formulier versies bekijken', 'action' => 'Versies bekijken'],
            ['route_name' => 'admin.cms.forms.revisions.restore', 'description' => '[CMS] Formulier versie herstellen', 'action' => 'Versie herstellen'],
            ['route_name' => 'admin.cms.categories.revisions.index', 'description' => '[CMS] Categorie versies bekijken', 'action' => 'Versies bekijken'],
            ['route_name' => 'admin.cms.categories.revisions.restore', 'description' => '[CMS] Categorie versie herstellen', 'action' => 'Versie herstellen'],
            ['route_name' => 'admin.cms.tags.revisions.index', 'description' => '[CMS] Tag versies bekijken', 'action' => 'Versies bekijken'],
            ['route_name' => 'admin.cms.tags.revisions.restore', 'description' => '[CMS] Tag versie herstellen', 'action' => 'Versie herstellen'],
        ];
    }
};
