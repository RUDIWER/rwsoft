<?php

namespace Tests\Feature\Cms;

use App\Actions\Admin\Cms\SitePackages\BuildCmsSitePackageZipAction;
use App\Actions\Admin\Cms\Starters\BuildExampleCmsStarterZipAction;
use App\Actions\Admin\Cms\Starters\ImportCmsStarterZipAction;
use App\Http\Middleware\AuthAdminUsers;
use App\Http\Middleware\AuthorizeAdminRoute;
use App\Http\Middleware\EnsureSiteMembership;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\ResolveTenantSite;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class CmsStarterImportBackofficeTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.mysql.database' => 'rwsoft',
            'database.connections.central.driver' => 'mysql',
            'database.connections.central.host' => config('database.connections.mysql.host'),
            'database.connections.central.port' => config('database.connections.mysql.port'),
            'database.connections.central.database' => 'rwsoft',
            'database.connections.central.username' => config('database.connections.mysql.username'),
            'database.connections.central.password' => config('database.connections.mysql.password'),
            'database.connections.tenant.driver' => 'mysql',
            'database.connections.tenant.host' => config('database.connections.mysql.host'),
            'database.connections.tenant.port' => config('database.connections.mysql.port'),
            'database.connections.tenant.database' => 'rwsoft_site_rwsoft',
            'database.connections.tenant.username' => config('database.connections.mysql.username'),
            'database.connections.tenant.password' => config('database.connections.mysql.password'),
        ]);

        DB::purge('central');
        DB::purge('tenant');
        DB::reconnect('central');
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');
        DB::connection('central')->beginTransaction();
        DB::connection('tenant')->beginTransaction();

        $this->withoutMiddleware([
            AuthAdminUsers::class,
            AuthorizeAdminRoute::class,
            EnsureSiteMembership::class,
            EnsureTwoFactorIsEnabled::class,
            ResolveTenantSite::class,
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }

        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_starter_zip_can_be_imported_from_settings(): void
    {
        $user = $this->createAdminUser();
        $starterKey = 'feature-starter-'.uniqid();
        $file = $this->zipFile([
            'manifest.json' => json_encode([
                'type' => 'rwsoft-cms-starter',
                'key' => $starterKey,
                'name' => 'Feature starter',
                'modules' => ['layouts'],
            ], JSON_THROW_ON_ERROR),
            'layouts.json' => json_encode([
                [
                    'import_key' => 'layout.main',
                    'name' => 'Feature starter layout',
                    'locale' => 'nl',
                    'is_active' => true,
                    'is_default' => true,
                    'sections' => [],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.settings.starter-import'), [
                'starter_zip' => $file,
            ])
            ->assertRedirect(route('admin.cms.settings.edit', ['tab' => 'starter']))
            ->assertSessionHas('status', __('cms_admin_ui.flash.starter_imported'))
            ->assertSessionHas('flash_details.starter_import.imported.layouts', 1)
            ->assertSessionDoesntHaveErrors();

        $layout = CmsLayout::query()
            ->where('import_key', 'starter:'.$starterKey.':layout.main')
            ->firstOrFail();

        $this->assertFalse((bool) $layout->is_active);
        $this->assertFalse((bool) $layout->is_default);
    }

    public function test_starter_import_rejects_invalid_zip_manifest(): void
    {
        $user = $this->createAdminUser();
        $starterKey = 'invalid-feature-starter-'.uniqid();
        $file = $this->zipFile([
            'manifest.json' => json_encode([
                'type' => 'invalid-starter',
                'key' => $starterKey,
                'modules' => ['layouts'],
            ], JSON_THROW_ON_ERROR),
            'layouts.json' => json_encode([
                [
                    'import_key' => 'layout.main',
                    'name' => 'Invalid starter layout',
                    'locale' => 'nl',
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this
            ->actingAs($user)
            ->from(route('admin.cms.settings.edit', ['tab' => 'starter']))
            ->post(route('admin.cms.settings.starter-import'), [
                'starter_zip' => $file,
            ])
            ->assertRedirect(route('admin.cms.settings.edit', ['tab' => 'starter']))
            ->assertSessionHasErrors(['starter_zip']);

        $this->assertFalse(
            CmsLayout::query()->where('import_key', 'starter:'.$starterKey.':layout.main')->exists()
        );
    }

    public function test_example_starter_zip_can_be_downloaded_from_settings(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->get(route('admin.cms.settings.starter-example'))
            ->assertOk()
            ->assertDownload('rwsoft-example-starter.zip')
            ->assertHeader('content-type', 'application/zip');
    }

    public function test_selected_cms_records_can_be_exported_as_starter_zip(): void
    {
        $user = $this->createAdminUser();
        $example = app(BuildExampleCmsStarterZipAction::class)->handle();
        $this->temporaryFiles[] = $example['path'];

        app(ImportCmsStarterZipAction::class)->handle(
            new UploadedFile($example['path'], $example['filename'], 'application/zip', null, true)
        );

        $layout = CmsLayout::query()->where('import_key', 'starter:example-starter:layout.main')->firstOrFail();
        $template = CmsTemplate::query()->where('import_key', 'starter:example-starter:template.page-detail')->firstOrFail();
        $page = CmsPage::query()->where('settings->starter_import_key', 'starter:example-starter:page.home')->firstOrFail();
        $menu = CmsMenu::query()->where('settings->starter_import_key', 'starter:example-starter:menu.header')->firstOrFail();

        $this
            ->actingAs($user)
            ->get(route('admin.cms.settings.starter-export', [
                'starter_key' => 'feature-export',
                'starter_name' => 'Feature Export',
                'layout_id' => $layout->id,
                'template_id' => $template->id,
                'page_id' => $page->id,
                'menu_id' => $menu->id,
            ]))
            ->assertOk()
            ->assertDownload('feature-export.zip')
            ->assertHeader('content-type', 'application/zip');
    }

    public function test_cms_site_package_can_be_exported_from_settings(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->get(route('admin.cms.settings.site-package-export', [
                'package_key' => 'feature-site-package',
                'package_name' => 'Feature Site Package',
                'modules' => ['layouts', 'templates', 'pages', 'menus'],
            ]))
            ->assertOk()
            ->assertDownload('feature-site-package.zip')
            ->assertHeader('content-type', 'application/zip');
    }

    public function test_cms_site_package_import_requires_empty_site_from_settings(): void
    {
        $user = $this->createAdminUser();
        $example = app(BuildExampleCmsStarterZipAction::class)->handle();
        $this->temporaryFiles[] = $example['path'];

        app(ImportCmsStarterZipAction::class)->handle(
            new UploadedFile($example['path'], $example['filename'], 'application/zip', null, true)
        );

        $export = app(BuildCmsSitePackageZipAction::class)->handle([
            'package_key' => 'feature-site-import',
            'package_name' => 'Feature Site Import',
            'modules' => ['layouts', 'templates', 'pages', 'menus'],
        ]);
        $this->temporaryFiles[] = $export['path'];

        $this
            ->actingAs($user)
            ->post(route('admin.cms.settings.site-package-import'), [
                'site_package_zip' => new UploadedFile($export['path'], $export['filename'], 'application/zip', null, true),
            ])
            ->assertSessionHasErrors('site_package_zip');

        $this->assertFalse(CmsPage::query()
            ->where('settings->site_package_import_key', 'site-package:feature-site-import:page.home')
            ->exists());
    }

    public function test_cms_site_package_can_be_previewed_without_importing_from_settings(): void
    {
        $user = $this->createAdminUser();
        $example = app(BuildExampleCmsStarterZipAction::class)->handle();
        $this->temporaryFiles[] = $example['path'];

        app(ImportCmsStarterZipAction::class)->handle(
            new UploadedFile($example['path'], $example['filename'], 'application/zip', null, true)
        );

        $export = app(BuildCmsSitePackageZipAction::class)->handle([
            'package_key' => 'feature-site-preview',
            'package_name' => 'Feature Site Preview',
            'modules' => ['layouts', 'templates', 'pages', 'menus'],
        ]);
        $this->temporaryFiles[] = $export['path'];

        $this
            ->actingAs($user)
            ->post(route('admin.cms.settings.site-package-preview'), [
                'site_package_zip' => new UploadedFile($export['path'], $export['filename'], 'application/zip', null, true),
            ])
            ->assertRedirect(route('admin.cms.settings.edit', ['tab' => 'starter']))
            ->assertSessionHas('status', __('cms_admin_ui.flash.site_package_previewed'))
            ->assertSessionHas('flash_details.site_package_preview.modules.pages')
            ->assertSessionDoesntHaveErrors();

        $this->assertFalse(
            CmsPage::query()
                ->where('settings->site_package_import_key', 'site-package:feature-site-preview:page.home')
                ->exists()
        );
    }

    public function test_cms_site_package_can_be_activated_from_settings(): void
    {
        $user = $this->createAdminUser();
        $example = app(BuildExampleCmsStarterZipAction::class)->handle();
        $this->temporaryFiles[] = $example['path'];

        app(ImportCmsStarterZipAction::class)->handle(
            new UploadedFile($example['path'], $example['filename'], 'application/zip', null, true)
        );

        $export = app(BuildCmsSitePackageZipAction::class)->handle([
            'package_key' => 'feature-site-activate',
            'package_name' => 'Feature Site Activate',
            'modules' => ['layouts', 'templates', 'pages', 'menus'],
        ]);
        $this->temporaryFiles[] = $export['path'];

        app(ImportCmsStarterZipAction::class)->handle(
            new UploadedFile($export['path'], $export['filename'], 'application/zip', null, true),
            [
                'config' => 'cms_site_packages.import',
                'manifest_type' => config('cms_site_packages.manifest_type'),
                'importable_modules' => config('cms_site_packages.importable_modules', []),
                'import_prefix' => 'site-package',
                'import_marker_key' => 'site_package_import_key',
                'allow_code_blocks' => config('cms_site_packages.import.allow_code_blocks_by_default', false),
            ],
        );

        $this
            ->actingAs($user)
            ->post(route('admin.cms.settings.site-package-activate'), [
                'package_key' => 'feature-site-activate',
                'modules' => ['layouts', 'templates', 'pages', 'menus'],
                'publish_pages' => true,
                'publish_blogs' => false,
            ])
            ->assertRedirect(route('admin.cms.settings.edit', ['tab' => 'starter']))
            ->assertSessionHas('status', __('cms_admin_ui.flash.site_package_activated'))
            ->assertSessionHas('flash_details.site_package_activation.activated.pages')
            ->assertSessionDoesntHaveErrors();

        $layout = CmsLayout::query()
            ->where('import_key', 'site-package:feature-site-activate:layout.main')
            ->firstOrFail();
        $template = CmsTemplate::query()
            ->where('import_key', 'site-package:feature-site-activate:template.page-detail')
            ->firstOrFail();
        $page = CmsPage::query()
            ->where('settings->site_package_import_key', 'site-package:feature-site-activate:page.home')
            ->firstOrFail();
        $menu = CmsMenu::query()
            ->where('settings->site_package_import_key', 'site-package:feature-site-activate:menu.header')
            ->firstOrFail();

        $this->assertTrue((bool) $layout->is_active);
        $this->assertTrue((bool) $template->is_active);
        $this->assertSame('published', $page->status);
        $this->assertNotNull($page->published_at);
        $this->assertFalse((bool) $page->is_home);
        $this->assertTrue((bool) $menu->is_active);
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('cms-starter-import-test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);

        $central = DB::connection('central');
        $roleId = $central->table('acl_roles')->where('key', 'super_admin')->value('id');

        if (! $roleId) {
            $roleId = $central->table('acl_roles')->insertGetId([
                'key' => 'super_admin',
                'name' => 'Super administrator',
                'description' => 'Test super admin role',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $central->table('acl_role_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'acl_role_id' => $roleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $user;
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function zipFile(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cms-starter-feature-');
        $this->assertIsString($path);
        $this->temporaryFiles[] = $path;

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));

        foreach ($entries as $name => $contents) {
            $this->assertTrue($zip->addFromString($name, $contents));
        }

        $this->assertTrue($zip->close());

        return new UploadedFile($path, 'starter.zip', 'application/zip', null, true);
    }
}
