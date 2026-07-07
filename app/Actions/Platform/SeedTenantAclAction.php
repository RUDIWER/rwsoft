<?php

namespace App\Actions\Platform;

use App\Models\Platform\Site;
use App\Models\Platform\SiteUserMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedTenantAclAction
{
    public function __construct(private readonly ConfigureTenantDatabaseAction $configureTenantDatabase) {}

    public function handle(Site $site): void
    {
        $this->configureTenantDatabase->handle($site);

        if (! $this->tenantAclTablesExist()) {
            return;
        }

        DB::connection('tenant')->transaction(function () use ($site): void {
            $this->seedRoles();
            $this->seedPermissionModules();
            $this->seedPermissionActions();
            $this->seedPermissionTypes();
            $this->seedPermissions();
            $this->grantAdminPermissions();
            $this->deactivateDeprecatedPermissions();
            $this->grantCurrentMembershipsAdminRole($site);
        });
    }

    private function tenantAclTablesExist(): bool
    {
        return Schema::connection('tenant')->hasTable('acl_roles')
            && Schema::connection('tenant')->hasTable('acl_permission_modules')
            && Schema::connection('tenant')->hasTable('acl_permission_actions')
            && Schema::connection('tenant')->hasTable('acl_permission_types')
            && Schema::connection('tenant')->hasTable('acl_permissions')
            && Schema::connection('tenant')->hasTable('acl_permission_role')
            && Schema::connection('tenant')->hasTable('acl_role_user');
    }

    private function seedRoles(): void
    {
        $now = now();
        $roles = [
            ['key' => 'super_admin', 'name' => 'Super Admin', 'description' => 'Volledige toegang binnen deze site.'],
            ['key' => 'admin', 'name' => 'Admin', 'description' => 'Standaard beheerder binnen deze site.'],
            ['key' => 'editor', 'name' => 'Editor', 'description' => 'Contentbeheerder binnen toegekende rechten.'],
        ];

        foreach ($roles as $role) {
            DB::connection('tenant')->table('acl_roles')->updateOrInsert(
                ['key' => $role['key']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedPermissionModules(): void
    {
        $now = now();

        foreach ((array) config('acl_permissions.modules', []) as $module) {
            DB::connection('tenant')->table('acl_permission_modules')->updateOrInsert(
                ['id' => (int) $module['id']],
                [
                    'key' => (string) $module['key'],
                    'name' => (string) $module['name'],
                    'sort_order' => (int) $module['sort_order'],
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedPermissionActions(): void
    {
        $now = now();

        foreach ((array) config('acl_permissions.actions', []) as $action) {
            DB::connection('tenant')->table('acl_permission_actions')->updateOrInsert(
                ['id' => (int) $action['id']],
                [
                    'key' => (string) $action['key'],
                    'name' => (string) $action['name'],
                    'sort_order' => (int) $action['sort_order'],
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedPermissionTypes(): void
    {
        $now = now();

        foreach ((array) config('acl_permissions.types', []) as $type) {
            DB::connection('tenant')->table('acl_permission_types')->updateOrInsert(
                ['id' => (int) $type['id']],
                [
                    'key' => (string) $type['key'],
                    'name' => (string) $type['name'],
                    'sort_order' => (int) $type['sort_order'],
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedPermissions(): void
    {
        $now = now();
        $permissions = $this->centralPermissions();

        foreach ($permissions as $permission) {
            DB::connection('tenant')->table('acl_permissions')->updateOrInsert(
                ['route_name' => $permission->route_name],
                [
                    'description' => $permission->description,
                    'module_id' => $permission->module_id,
                    'action_id' => $permission->action_id,
                    'type_id' => $permission->type_id,
                    'query_id' => $permission->query_id,
                    'menu' => (bool) $permission->menu,
                    'url' => $permission->url,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function grantAdminPermissions(): void
    {
        $now = now();
        $adminRoleId = DB::connection('tenant')->table('acl_roles')->where('key', 'admin')->value('id');

        if (! $adminRoleId) {
            return;
        }

        $permissionIds = DB::connection('tenant')->table('acl_permissions')->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::connection('tenant')->table('acl_permission_role')->updateOrInsert(
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

    private function deactivateDeprecatedPermissions(): void
    {
        $now = now();
        $deprecatedPermissionIds = DB::connection('tenant')
            ->table('acl_permissions')
            ->where('route_name', 'like', 'admin.cms.block-variants.%')
            ->pluck('id');

        if ($deprecatedPermissionIds->isEmpty()) {
            return;
        }

        DB::connection('tenant')
            ->table('acl_permissions')
            ->whereIn('id', $deprecatedPermissionIds->all())
            ->update(['menu' => false, 'updated_at' => $now]);

        DB::connection('tenant')
            ->table('acl_permission_role')
            ->whereIn('acl_permission_id', $deprecatedPermissionIds->all())
            ->update(['active' => false, 'updated_at' => $now]);
    }

    private function grantCurrentMembershipsAdminRole(Site $site): void
    {
        $now = now();
        $adminRoleId = DB::connection('tenant')->table('acl_roles')->where('key', 'admin')->value('id');

        if (! $adminRoleId) {
            return;
        }

        $userIds = SiteUserMembership::query()
            ->where('site_id', $site->id)
            ->where('is_active', true)
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            DB::connection('tenant')->table('acl_role_user')->updateOrInsert(
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

    private function centralPermissions()
    {
        $fallbackPermissions = collect($this->fallbackPermissions());

        if (! Schema::connection('central')->hasTable('acl_permissions')) {
            return $fallbackPermissions;
        }

        $columns = [
            'route_name',
            'description',
            'query_id',
            'menu',
            'url',
        ];

        if (Schema::connection('central')->hasColumn('acl_permissions', 'module_id')) {
            $columns[] = 'module_id';
        }

        if (Schema::connection('central')->hasColumn('acl_permissions', 'action_id')) {
            $columns[] = 'action_id';
        }

        if (Schema::connection('central')->hasColumn('acl_permissions', 'type_id')) {
            $columns[] = 'type_id';
        }

        if (Schema::connection('central')->hasColumn('acl_permissions', 'type')) {
            $columns[] = 'type';
        }

        return DB::connection('central')
            ->table('acl_permissions')
            ->orderBy('route_name')
            ->get($columns)
            ->merge($fallbackPermissions)
            ->map(fn (object $permission): object => $this->normalizePermissionLookupIds($permission))
            ->unique('route_name')
            ->values();
    }

    private function normalizePermissionLookupIds(object $permission): object
    {
        $permission->module_id = $permission->module_id ?? $this->lookupIdByName('modules', $permission->module ?? null);
        $permission->action_id = $permission->action_id ?? $this->lookupIdByName('actions', $permission->action ?? null);
        $permission->type_id = $permission->type_id ?? $this->lookupIdByName('types', $permission->type ?? null);

        unset($permission->module, $permission->action, $permission->type);

        return $permission;
    }

    private function lookupIdByName(string $type, mixed $name): ?int
    {
        $normalizedName = trim((string) $name);

        if ($normalizedName === '') {
            return null;
        }

        foreach ((array) config("acl_permissions.{$type}", []) as $item) {
            if (
                strcasecmp((string) ($item['key'] ?? ''), $normalizedName) === 0
                || strcasecmp((string) ($item['name'] ?? ''), $normalizedName) === 0
            ) {
                return (int) $item['id'];
            }
        }

        return null;
    }

    /**
     * @return array<int, object>
     */
    private function fallbackPermissions(): array
    {
        return collect([
            ['route_name' => 'admin', 'description' => '[Admin] admin', 'module' => 'Dashboard', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.translations.index', 'description' => '[Admin] Vertalingen editor openen', 'module' => 'Menu configuratie', 'action' => 'Talen editor', 'menu' => true, 'url' => 'admin/translations'],
            ['route_name' => 'admin.translations.public.rows', 'description' => '[Admin] Openbare tekstvertalingen laden', 'module' => 'Menu configuratie', 'action' => 'Openbare teksten laden', 'menu' => false, 'url' => 'admin/translations/public/rows'],
            ['route_name' => 'admin.translations.public.update', 'description' => '[Admin] Openbare tekstvertaling bijwerken', 'module' => 'Menu configuratie', 'action' => 'Openbare teksten bijwerken', 'menu' => false, 'url' => 'admin/translations/public/rows/{row}'],
            ['route_name' => 'admin.translations.public.sync', 'description' => '[Admin] Openbare tekstvertalingen synchroniseren', 'module' => 'Menu configuratie', 'action' => 'Openbare teksten sync', 'menu' => false, 'url' => 'admin/translations/public/sync'],
            ['route_name' => 'admin.translations.public.ai-fill', 'description' => '[Admin] Openbare tekstvertalingen met AI aanvullen', 'module' => 'Menu configuratie', 'action' => 'Openbare teksten AI', 'menu' => false, 'url' => 'admin/translations/public/ai-fill'],
            ['route_name' => 'admin.translations.content.rows', 'description' => '[Admin] Contentvertalingen laden', 'module' => 'CMS', 'action' => 'Talen laden', 'menu' => false, 'url' => 'admin/translations/content/rows'],
            ['route_name' => 'admin.translations.content.store', 'description' => '[Admin] Contentvertaling aanmaken', 'module' => 'CMS', 'action' => 'Vertalen', 'menu' => false, 'url' => 'admin/translations/content'],
            ['route_name' => 'admin.translations.content.bulk-ai', 'description' => '[Admin] Contentvertalingen met AI in bulk aanmaken', 'module' => 'CMS', 'action' => 'Vertalen', 'menu' => false, 'url' => 'admin/translations/content/bulk-ai'],
            ['route_name' => 'admin.translations.content.mark-reviewed', 'description' => '[Admin] AI contentvertaling als nagekeken markeren', 'module' => 'CMS', 'action' => 'Vertalen', 'menu' => false, 'url' => 'admin/translations/content/mark-reviewed'],
            ['route_name' => 'admin.cms.health.index', 'description' => '[CMS] Kwaliteit bekijken', 'module' => 'CMS', 'action' => 'Kwaliteit bekijken', 'menu' => false],
            ['route_name' => 'admin.cms.health.public-account.repair', 'description' => '[CMS] Public Account herstellen', 'module' => 'CMS', 'action' => 'Herstellen', 'menu' => false, 'url' => 'admin/cms/health/public-account/repair'],
            ['route_name' => 'admin.cms.languages.reorder', 'description' => '[CMS] Talen sorteren', 'module' => 'CMS', 'action' => 'Sorteren', 'menu' => false],
            ['route_name' => 'admin.cms.country-flags.preview', 'description' => '[CMS] Systeemvlag preview bekijken', 'module' => 'CMS', 'action' => 'Bekijken', 'menu' => false],
            ['route_name' => 'admin.cms.country-flags.copy', 'description' => '[CMS] Systeemvlag naar media kopieren', 'module' => 'CMS', 'action' => 'Kopieren', 'menu' => false],
            ['route_name' => 'admin.cms.media.sort', 'description' => '[CMS] Media sorteren', 'module' => 'CMS', 'action' => 'Sorteren', 'menu' => false],
            ['route_name' => 'admin.cms.media.metadata', 'description' => '[CMS] Media metadata via dialog bewaren', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.media.edit-copy', 'description' => '[CMS] Media bewerkte kopie bewaren', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.media-folders.update', 'description' => '[CMS] Media map hernoemen', 'module' => 'CMS', 'action' => 'Bewerken', 'menu' => false],
            ['route_name' => 'admin.cms.media-folders.move', 'description' => '[CMS] Media map verplaatsen', 'module' => 'CMS', 'action' => 'Verplaatsen', 'menu' => false],
            ['route_name' => 'admin.cms.downloads.index', 'description' => '[CMS] Downloads overzicht', 'module' => 'CMS', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.cms.downloads.store', 'description' => '[CMS] Download uploaden', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.downloads.edit', 'description' => '[CMS] Download bewerken', 'module' => 'CMS', 'action' => 'Bewerken', 'menu' => false],
            ['route_name' => 'admin.cms.downloads.update', 'description' => '[CMS] Download bewaren', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.downloads.replace-file', 'description' => '[CMS] Downloadbestand vervangen', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.downloads.destroy', 'description' => '[CMS] Download verwijderen', 'module' => 'CMS', 'action' => 'Verwijderen', 'menu' => false],
            ['route_name' => 'admin.cms.download-groups.index', 'description' => '[CMS] Downloadgroepen overzicht', 'module' => 'CMS', 'action' => 'Overzicht', 'menu' => false],
            ['route_name' => 'admin.cms.download-groups.store', 'description' => '[CMS] Downloadgroep bewaren', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.download-groups.update', 'description' => '[CMS] Downloadgroep bijwerken', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.download-folders.store', 'description' => '[CMS] Downloadmap maken', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.download-folders.update', 'description' => '[CMS] Downloadmap bijwerken', 'module' => 'CMS', 'action' => 'Bewerken', 'menu' => false],
            ['route_name' => 'admin.cms.download-folders.move', 'description' => '[CMS] Downloadmap verplaatsen', 'module' => 'CMS', 'action' => 'Verplaatsen', 'menu' => false],
            ['route_name' => 'admin.cms.pages.revisions.index', 'description' => '[CMS] Pagina versies bekijken', 'module' => 'CMS', 'action' => 'Versies bekijken', 'menu' => false],
            ['route_name' => 'admin.cms.pages.revisions.restore', 'description' => '[CMS] Pagina versie herstellen', 'module' => 'CMS', 'action' => 'Versie herstellen', 'menu' => false],
            ['route_name' => 'admin.cms.layouts.index', 'description' => '[CMS] Layout overzicht', 'module' => 'CMS', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.cms.layouts.create', 'description' => '[CMS] Layout toevoegen', 'module' => 'CMS', 'action' => 'Toevoegen', 'menu' => false],
            ['route_name' => 'admin.cms.layouts.edit', 'description' => '[CMS] Layout bewerken', 'module' => 'CMS', 'action' => 'Bewerken', 'menu' => false],
            ['route_name' => 'admin.cms.layouts.store', 'description' => '[CMS] Layout bewaren', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.section-preview', 'description' => '[CMS] Sectie live preview laden', 'module' => 'CMS', 'action' => 'Preview bekijken', 'menu' => false],
            ['route_name' => 'admin.cms.layouts.destroy', 'description' => '[CMS] Layout verwijderen', 'module' => 'CMS', 'action' => 'Verwijderen', 'menu' => false],
            ['route_name' => 'admin.cms.layouts.translations.store', 'description' => '[CMS] Layout vertaling maken', 'module' => 'CMS', 'action' => 'Vertalen', 'menu' => false],
            ['route_name' => 'admin.cms.layouts.revisions.index', 'description' => '[CMS] Layout versies bekijken', 'module' => 'CMS', 'action' => 'Versies bekijken', 'menu' => false],
            ['route_name' => 'admin.cms.layouts.revisions.restore', 'description' => '[CMS] Layout versie herstellen', 'module' => 'CMS', 'action' => 'Versie herstellen', 'menu' => false],
            ['route_name' => 'admin.cms.templates.index', 'description' => '[CMS] Template overzicht', 'module' => 'CMS', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.cms.templates.create', 'description' => '[CMS] Template toevoegen', 'module' => 'CMS', 'action' => 'Toevoegen', 'menu' => false],
            ['route_name' => 'admin.cms.templates.edit', 'description' => '[CMS] Template bewerken', 'module' => 'CMS', 'action' => 'Bewerken', 'menu' => false],
            ['route_name' => 'admin.cms.templates.preview', 'description' => '[CMS] Template preview bekijken', 'module' => 'CMS', 'action' => 'Preview bekijken', 'menu' => false],
            ['route_name' => 'admin.cms.templates.revisions.index', 'description' => '[CMS] Template versies bekijken', 'module' => 'CMS', 'action' => 'Versies bekijken', 'menu' => false],
            ['route_name' => 'admin.cms.templates.revisions.restore', 'description' => '[CMS] Template versie herstellen', 'module' => 'CMS', 'action' => 'Versie herstellen', 'menu' => false],
            ['route_name' => 'admin.cms.templates.translations.store', 'description' => '[CMS] Template vertaling maken', 'module' => 'CMS', 'action' => 'Vertalen', 'menu' => false],
            ['route_name' => 'admin.cms.templates.store', 'description' => '[CMS] Template bewaren', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.templates.destroy', 'description' => '[CMS] Template verwijderen', 'module' => 'CMS', 'action' => 'Verwijderen', 'menu' => false],
            ['route_name' => 'admin.cms.posts.revisions.index', 'description' => '[CMS] Bericht versies bekijken', 'module' => 'CMS', 'action' => 'Versies bekijken', 'menu' => false],
            ['route_name' => 'admin.cms.posts.revisions.restore', 'description' => '[CMS] Bericht versie herstellen', 'module' => 'CMS', 'action' => 'Versie herstellen', 'menu' => false],
            ['route_name' => 'admin.cms.menus.revisions.index', 'description' => '[CMS] Menu versies bekijken', 'module' => 'CMS', 'action' => 'Versies bekijken', 'menu' => false],
            ['route_name' => 'admin.cms.menus.revisions.restore', 'description' => '[CMS] Menu versie herstellen', 'module' => 'CMS', 'action' => 'Versie herstellen', 'menu' => false],
            ['route_name' => 'admin.cms.forms.revisions.index', 'description' => '[CMS] Formulier versies bekijken', 'module' => 'CMS', 'action' => 'Versies bekijken', 'menu' => false],
            ['route_name' => 'admin.cms.forms.revisions.restore', 'description' => '[CMS] Formulier versie herstellen', 'module' => 'CMS', 'action' => 'Versie herstellen', 'menu' => false],
            ['route_name' => 'admin.cms.form-submissions.index', 'description' => '[CMS] Inzendingen overzicht', 'module' => 'CMS', 'action' => 'Overzicht', 'menu' => true, 'url' => 'admin/cms/form-submissions'],
            ['route_name' => 'admin.cms.mail-templates.index', 'description' => '[CMS] Mail templates overzicht', 'module' => 'CMS', 'action' => 'Overzicht', 'menu' => true, 'url' => 'admin/cms/mail-templates'],
            ['route_name' => 'admin.cms.mail-templates.create', 'description' => '[CMS] Mail template toevoegen', 'module' => 'CMS', 'action' => 'Toevoegen', 'menu' => false, 'url' => 'admin/cms/mail-templates/create'],
            ['route_name' => 'admin.cms.mail-templates.edit', 'description' => '[CMS] Mail template bewerken', 'module' => 'CMS', 'action' => 'Bewerken', 'menu' => false, 'url' => 'admin/cms/mail-templates/{id}/edit'],
            ['route_name' => 'admin.cms.mail-templates.store', 'description' => '[CMS] Mail template bewaren', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false, 'url' => 'admin/cms/mail-templates/{id}/store'],
            ['route_name' => 'admin.cms.mail-templates.revisions.index', 'description' => '[CMS] Mail template versies bekijken', 'module' => 'CMS', 'action' => 'Versies', 'menu' => false, 'url' => 'admin/cms/mail-templates/{mailTemplate}/revisions'],
            ['route_name' => 'admin.cms.mail-templates.revisions.restore', 'description' => '[CMS] Mail template versie herstellen', 'module' => 'CMS', 'action' => 'Herstellen', 'menu' => false, 'url' => 'admin/cms/mail-templates/{mailTemplate}/revisions/{revision}/restore'],
            ['route_name' => 'admin.cms.emails.index', 'description' => '[CMS] E-mails overzicht', 'module' => 'CMS', 'action' => 'Overzicht', 'menu' => true, 'url' => 'admin/cms/emails'],
            ['route_name' => 'admin.cms.emails.create', 'description' => '[CMS] E-mail toevoegen', 'module' => 'CMS', 'action' => 'Toevoegen', 'menu' => false, 'url' => 'admin/cms/emails/create'],
            ['route_name' => 'admin.cms.emails.edit', 'description' => '[CMS] E-mail bewerken', 'module' => 'CMS', 'action' => 'Bewerken', 'menu' => false, 'url' => 'admin/cms/emails/{id}/edit'],
            ['route_name' => 'admin.cms.emails.store', 'description' => '[CMS] E-mail bewaren', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false, 'url' => 'admin/cms/emails/{id}/store'],
            ['route_name' => 'admin.cms.emails.translations.store', 'description' => '[CMS] E-mailvertaling aanmaken', 'module' => 'CMS', 'action' => 'Vertaling', 'menu' => false, 'url' => 'admin/cms/emails/{id}/translations'],
            ['route_name' => 'admin.cms.emails.revisions.index', 'description' => '[CMS] E-mail versies bekijken', 'module' => 'CMS', 'action' => 'Versies', 'menu' => false, 'url' => 'admin/cms/emails/{email}/revisions'],
            ['route_name' => 'admin.cms.emails.revisions.restore', 'description' => '[CMS] E-mail versie herstellen', 'module' => 'CMS', 'action' => 'Herstellen', 'menu' => false, 'url' => 'admin/cms/emails/{email}/revisions/{revision}/restore'],
            ['route_name' => 'admin.cms.emails.preview', 'description' => '[CMS] E-mail preview', 'module' => 'CMS', 'action' => 'Preview', 'menu' => false, 'url' => 'admin/cms/emails/{id}/preview'],
            ['route_name' => 'admin.cms.emails.test-send', 'description' => '[CMS] E-mail test versturen', 'module' => 'CMS', 'action' => 'Versturen', 'menu' => false, 'url' => 'admin/cms/emails/{id}/test-send'],
            ['route_name' => 'admin.cms.site-users.index', 'description' => '[CMS] Website accounts overzicht', 'module' => 'CMS', 'action' => 'Overzicht', 'menu' => true, 'url' => 'admin/cms/site-users'],
            ['route_name' => 'admin.cms.site-users.settings.store', 'description' => '[CMS] Website account instellingen bewaren', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false, 'url' => 'admin/cms/site-users/settings'],
            ['route_name' => 'admin.cms.site-users.activate', 'description' => '[CMS] Website account activeren', 'module' => 'CMS', 'action' => 'Activeren', 'menu' => false, 'url' => 'admin/cms/site-users/{siteUser}/activate'],
            ['route_name' => 'admin.cms.site-users.deactivate', 'description' => '[CMS] Website account deactiveren', 'module' => 'CMS', 'action' => 'Deactiveren', 'menu' => false, 'url' => 'admin/cms/site-users/{siteUser}/deactivate'],
            ['route_name' => 'admin.cms.site-users.reset-two-factor', 'description' => '[CMS] Website account 2FA resetten', 'module' => 'CMS', 'action' => '2FA resetten', 'menu' => false, 'url' => 'admin/cms/site-users/{siteUser}/reset-two-factor'],
            ['route_name' => 'admin.cms.categories.revisions.index', 'description' => '[CMS] Categorie versies bekijken', 'module' => 'CMS', 'action' => 'Versies bekijken', 'menu' => false],
            ['route_name' => 'admin.cms.categories.revisions.restore', 'description' => '[CMS] Categorie versie herstellen', 'module' => 'CMS', 'action' => 'Versie herstellen', 'menu' => false],
            ['route_name' => 'admin.cms.tags.revisions.index', 'description' => '[CMS] Tag versies bekijken', 'module' => 'CMS', 'action' => 'Versies bekijken', 'menu' => false],
            ['route_name' => 'admin.cms.tags.revisions.restore', 'description' => '[CMS] Tag versie herstellen', 'module' => 'CMS', 'action' => 'Versie herstellen', 'menu' => false],
            ['route_name' => 'admin.cms.color-palette.index', 'description' => '[CMS] Kleurenpalet laden', 'module' => 'CMS', 'action' => 'Bekijken', 'menu' => false],
            ['route_name' => 'admin.cms.color-palette.store', 'description' => '[CMS] Kleur bewaren', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.color-palette.destroy', 'description' => '[CMS] Kleur verwijderen', 'module' => 'CMS', 'action' => 'Verwijderen', 'menu' => false],
            ['route_name' => 'admin.cms.layouts.code-blocks.manage', 'description' => '[CMS] Layout codeblocks beheren', 'module' => 'CMS', 'action' => 'Codeblocks beheren', 'menu' => false],
            ['route_name' => 'admin.cms.blocks.index', 'description' => '[CMS] Blokken overzicht', 'module' => 'CMS', 'action' => 'Overzicht', 'menu' => true, 'url' => 'admin/cms/blocks'],
            ['route_name' => 'admin.cms.blocks.create', 'description' => '[CMS] Blok toevoegen', 'module' => 'CMS', 'action' => 'Toevoegen', 'menu' => false, 'url' => 'admin/cms/blocks/create'],
            ['route_name' => 'admin.cms.blocks.edit', 'description' => '[CMS] Blok bewerken', 'module' => 'CMS', 'action' => 'Bewerken', 'menu' => false, 'url' => 'admin/cms/blocks/{block}/edit'],
            ['route_name' => 'admin.cms.blocks.store-new', 'description' => '[CMS] Blok toevoegen bewaren', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false, 'url' => 'admin/cms/blocks/store'],
            ['route_name' => 'admin.cms.blocks.store', 'description' => '[CMS] Blok bewaren', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false, 'url' => 'admin/cms/blocks/{block}/store'],
            ['route_name' => 'admin.cms.blocks.publish', 'description' => '[CMS] Blok publiceren', 'module' => 'CMS', 'action' => 'Publiceren', 'menu' => false, 'url' => 'admin/cms/blocks/{block}/publish'],
            ['route_name' => 'admin.cms.blocks.restore-revision', 'description' => '[CMS] Blokrevisie herstellen', 'module' => 'CMS', 'action' => 'Herstellen', 'menu' => false, 'url' => 'admin/cms/blocks/{block}/revisions/{revision}/restore'],
            ['route_name' => 'admin.cms.block-placements.style-revisions.index', 'description' => '[CMS] Block stijlrevisies bekijken', 'module' => 'CMS', 'action' => 'Bekijken', 'menu' => false],
            ['route_name' => 'admin.cms.block-placements.style-revisions.publish', 'description' => '[CMS] Block stijlrevisie publiceren', 'module' => 'CMS', 'action' => 'Publiceren', 'menu' => false],
            ['route_name' => 'admin.cms.block-placements.style-revisions.republish', 'description' => '[CMS] Block stijlrevisie herpubliceren', 'module' => 'CMS', 'action' => 'Publiceren', 'menu' => false],
            ['route_name' => 'admin.cms.themes.index', 'description' => '[CMS] Thema overzicht', 'module' => 'CMS', 'action' => 'Overzicht', 'menu' => true],
            ['route_name' => 'admin.cms.themes.create', 'description' => '[CMS] Thema toevoegen', 'module' => 'CMS', 'action' => 'Toevoegen', 'menu' => false],
            ['route_name' => 'admin.cms.themes.edit', 'description' => '[CMS] Thema bewerken', 'module' => 'CMS', 'action' => 'Bewerken', 'menu' => false],
            ['route_name' => 'admin.cms.themes.store-new', 'description' => '[CMS] Thema toevoegen bewaren', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.themes.store', 'description' => '[CMS] Thema bewaren', 'module' => 'CMS', 'action' => 'Bewaren', 'menu' => false],
            ['route_name' => 'admin.cms.themes.publish', 'description' => '[CMS] Thema publiceren', 'module' => 'CMS', 'action' => 'Publiceren', 'menu' => false],
            ['route_name' => 'admin.cms.themes.activate', 'description' => '[CMS] Thema activeren', 'module' => 'CMS', 'action' => 'Activeren', 'menu' => false],
            ['route_name' => 'admin.cms.themes.delete', 'description' => '[CMS] Thema verwijderen', 'module' => 'CMS', 'action' => 'Verwijderen', 'menu' => false],
            ['route_name' => 'admin.cms.themes.preview', 'description' => '[CMS] Thema preview', 'module' => 'CMS', 'action' => 'Preview', 'menu' => false],
            ['route_name' => 'admin.cms.themes.download', 'description' => '[CMS] Thema downloaden', 'module' => 'CMS', 'action' => 'Downloaden', 'menu' => false],
            ['route_name' => 'admin.cms.themes.import', 'description' => '[CMS] Thema importeren', 'module' => 'CMS', 'action' => 'Importeren', 'menu' => false],
            ['route_name' => 'admin.cms.themes.restore-version', 'description' => '[CMS] Thema versie herstellen', 'module' => 'CMS', 'action' => 'Herstellen', 'menu' => false],
            ['route_name' => 'admin.cms.settings.modules.install', 'description' => '[CMS] Module installeren', 'module' => 'CMS', 'action' => 'Installeren', 'menu' => false],
            ['route_name' => 'admin.cms.settings.modules.demo-data', 'description' => '[CMS] Module demodata installeren', 'module' => 'CMS', 'action' => 'Installeren', 'menu' => false],
            ['route_name' => 'admin.cms.settings.starter-example', 'description' => '[CMS] Starter-site voorbeeld downloaden', 'module' => 'CMS', 'action' => 'Downloaden', 'menu' => false],
            ['route_name' => 'admin.cms.settings.starter-export', 'description' => '[CMS] Starter-site exporteren', 'module' => 'CMS', 'action' => 'Exporteren', 'menu' => false],
            ['route_name' => 'admin.cms.settings.starter-import', 'description' => '[CMS] Starter-site importeren', 'module' => 'CMS', 'action' => 'Importeren', 'menu' => false],
            ['route_name' => 'admin.cms.settings.site-package-export', 'description' => '[CMS] Site package exporteren', 'module' => 'CMS', 'action' => 'Exporteren', 'menu' => false],
            ['route_name' => 'admin.cms.settings.site-package-preview', 'description' => '[CMS] Site package preview', 'module' => 'CMS', 'action' => 'Preview', 'menu' => false],
            ['route_name' => 'admin.cms.settings.site-package-import', 'description' => '[CMS] Site package importeren', 'module' => 'CMS', 'action' => 'Importeren', 'menu' => false],
            ['route_name' => 'admin.cms.settings.site-package-activate', 'description' => '[CMS] Site package activeren', 'module' => 'CMS', 'action' => 'Activeren', 'menu' => false],
            ['route_name' => 'admin.cms.search-console.connect', 'description' => '[CMS] Connect Google Search Console', 'module' => 'CMS', 'action' => 'Beheer', 'menu' => false],
            ['route_name' => 'admin.cms.search-console.callback', 'description' => '[CMS] Complete Google Search Console connection', 'module' => 'CMS', 'action' => 'Beheer', 'menu' => false],
            ['route_name' => 'admin.cms.search-console.disconnect', 'description' => '[CMS] Disconnect Google Search Console', 'module' => 'CMS', 'action' => 'Beheer', 'menu' => false],
            ['route_name' => 'admin.cms.search-console.test', 'description' => '[CMS] Test Google Search Console connection', 'module' => 'CMS', 'action' => 'Inspecteren', 'menu' => false],
            ['route_name' => 'admin.cms.statistics.visits', 'description' => '[CMS] Load visitor statistics', 'module' => 'CMS', 'action' => 'Inspecteren', 'menu' => false],
            ['route_name' => 'admin.cms.statistics.search-console', 'description' => '[CMS] Load Search Console statistics', 'module' => 'CMS', 'action' => 'Inspecteren', 'menu' => false],
        ])
            ->map(fn (array $permission): object => (object) array_merge([
                'type_id' => 1,
                'query_id' => null,
                'url' => null,
            ], $permission))
            ->all();
    }
}
