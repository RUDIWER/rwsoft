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
        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');
        $now = now();

        foreach ($this->permissions() as $permission) {
            DB::table('acl_permissions')->updateOrInsert(
                ['route_name' => $permission['route_name']],
                [
                    'description' => $permission['description'],
                    'module' => 'CMS',
                    'action' => $permission['action'],
                    'type' => 'core',
                    'menu' => $permission['menu'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            if (! $adminRoleId) {
                continue;
            }

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $routeNames = collect($this->permissions())->pluck('route_name')->all();
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
     * @return array<int, array{route_name: string, description: string, action: string, menu: bool}>
     */
    private function permissions(): array
    {
        return [
            ['route_name' => 'admin.cms.pages.index', 'description' => '[CMS] Pagina overzicht', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.cms.pages.create', 'description' => '[CMS] Pagina toevoegen', 'action' => 'Toevoegen', 'menu' => false],
            ['route_name' => 'admin.cms.pages.edit', 'description' => '[CMS] Pagina bewerken', 'action' => 'Bewerken', 'menu' => false],
            ['route_name' => 'admin.cms.pages.store', 'description' => '[CMS] Pagina bewaren', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.posts.index', 'description' => '[CMS] Blog overzicht', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.cms.posts.create', 'description' => '[CMS] Blog toevoegen', 'action' => 'Toevoegen', 'menu' => false],
            ['route_name' => 'admin.cms.posts.edit', 'description' => '[CMS] Blog bewerken', 'action' => 'Bewerken', 'menu' => false],
            ['route_name' => 'admin.cms.posts.store', 'description' => '[CMS] Blog bewaren', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.taxonomy.index', 'description' => '[CMS] Taxonomie beheer', 'action' => 'Beheer', 'menu' => true],
            ['route_name' => 'admin.cms.media.index', 'description' => '[CMS] Media overzicht', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.cms.media.store', 'description' => '[CMS] Media bewaren', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.menus.index', 'description' => '[CMS] Menu overzicht', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.cms.menus.store', 'description' => '[CMS] Menu bewaren', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.redirects.index', 'description' => '[CMS] Redirect overzicht', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.cms.redirects.store', 'description' => '[CMS] Redirect bewaren', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.forms.index', 'description' => '[CMS] Formulieren overzicht', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.cms.forms.store', 'description' => '[CMS] Formulier bewaren', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.form-submissions.index', 'description' => '[CMS] Inzendingen overzicht', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.cms.settings.edit', 'description' => '[CMS] Instellingen bewerken', 'action' => 'Bewerken', 'menu' => true],
            ['route_name' => 'admin.cms.settings.store', 'description' => '[CMS] Instellingen bewaren', 'action' => 'Bewaren', 'menu' => false],
        ];
    }
};
