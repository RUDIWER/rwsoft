<?php

namespace Tests\Unit\Cms;

use App\Actions\Admin\Cms\Starters\BuildExampleCmsStarterZipAction;
use App\Actions\Admin\Cms\Starters\ImportCmsStarterZipAction;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class ImportCmsStarterZipActionTest extends TestCase
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
            'database.connections.tenant.driver' => 'mysql',
            'database.connections.tenant.host' => config('database.connections.mysql.host'),
            'database.connections.tenant.port' => config('database.connections.mysql.port'),
            'database.connections.tenant.database' => 'rwsoft_site_rwsoft',
            'database.connections.tenant.username' => config('database.connections.mysql.username'),
            'database.connections.tenant.password' => config('database.connections.mysql.password'),
        ]);

        DB::purge('tenant');
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');
        DB::connection('tenant')->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_it_reads_a_valid_starter_manifest_and_module_counts(): void
    {
        $file = $this->zipFile([
            'manifest.json' => json_encode([
                'type' => 'rwsoft-cms-starter',
                'name' => 'Starter',
                'modules' => ['forms'],
            ], JSON_THROW_ON_ERROR),
            'forms.json' => json_encode([
                ['import_key' => 'form.contact'],
                ['import_key' => 'form.newsletter'],
            ], JSON_THROW_ON_ERROR),
        ]);

        $result = app(ImportCmsStarterZipAction::class)->handle($file);

        $this->assertSame('rwsoft-cms-starter', $result['manifest']['type']);
        $this->assertSame([
            'forms' => 2,
        ], $result['modules']);
    }

    public function test_it_imports_layouts_and_templates_as_inactive_records(): void
    {
        $file = $this->zipFile([
            'manifest.json' => json_encode([
                'type' => 'rwsoft-cms-starter',
                'key' => 'unit-starter',
                'modules' => ['layouts', 'templates'],
            ], JSON_THROW_ON_ERROR),
            'layouts.json' => json_encode([
                [
                    'import_key' => 'layout.main',
                    'name' => 'Main Layout',
                    'locale' => 'nl',
                    'is_active' => true,
                    'is_default' => true,
                    'sections' => [],
                ],
            ], JSON_THROW_ON_ERROR),
            'templates.json' => json_encode([
                [
                    'import_key' => 'template.page-detail',
                    'layout_import_key' => 'layout.main',
                    'name' => 'Page Detail',
                    'locale' => 'nl',
                    'template_class' => 'page',
                    'template_key' => 'page.detail',
                    'is_active' => true,
                    'is_default' => true,
                    'sections' => [],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $result = app(ImportCmsStarterZipAction::class)->handle($file);

        $layout = CmsLayout::query()->where('import_key', 'starter:unit-starter:layout.main')->firstOrFail();
        $template = CmsTemplate::query()->where('import_key', 'starter:unit-starter:template.page-detail')->firstOrFail();

        $this->assertSame(['layouts' => 1, 'templates' => 1, 'pages' => 0, 'menus' => 0, 'site' => 0, 'languages' => 0, 'public_texts' => 0, 'redirects' => 0, 'forms' => 0, 'taxonomies' => 0, 'blogs' => 0, 'media' => 0, 'themes' => 0], $result['imported']);
        $this->assertFalse((bool) $layout->is_active);
        $this->assertFalse((bool) $layout->is_default);
        $this->assertFalse((bool) $template->is_active);
        $this->assertFalse((bool) $template->is_default);
        $this->assertSame($layout->id, $template->layout_id);
    }

    public function test_it_imports_pages_as_drafts_and_maps_templates(): void
    {
        $slug = 'starter-home-'.uniqid();

        CmsPage::query()->create([
            'title' => 'Existing Home',
            'slug' => $slug,
            'locale' => 'nl',
            'status' => 'published',
            'content_blocks' => [],
            'noindex' => false,
            'is_home' => true,
            'is_searchable' => true,
            'sort_order' => 0,
        ]);

        $file = $this->zipFile([
            'manifest.json' => json_encode([
                'type' => 'rwsoft-cms-starter',
                'key' => 'page-starter',
                'modules' => ['layouts', 'templates', 'pages'],
            ], JSON_THROW_ON_ERROR),
            'layouts.json' => json_encode([
                [
                    'import_key' => 'layout.main',
                    'name' => 'Main Layout',
                    'locale' => 'nl',
                    'sections' => [],
                ],
            ], JSON_THROW_ON_ERROR),
            'templates.json' => json_encode([
                [
                    'import_key' => 'template.page-detail',
                    'layout_import_key' => 'layout.main',
                    'name' => 'Page Detail',
                    'locale' => 'nl',
                    'template_class' => 'page',
                    'template_key' => 'page.detail',
                    'sections' => [],
                ],
            ], JSON_THROW_ON_ERROR),
            'pages.json' => json_encode([
                [
                    'import_key' => 'page.home',
                    'detail_template_import_key' => 'template.page-detail',
                    'title' => 'Home',
                    'slug' => $slug,
                    'locale' => 'nl',
                    'status' => 'published',
                    'is_home' => true,
                    'published_at' => '2026-01-01 12:00:00',
                    'sections' => [],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $result = app(ImportCmsStarterZipAction::class)->handle($file);

        $page = CmsPage::query()
            ->where('settings->starter_import_key', 'starter:page-starter:page.home')
            ->firstOrFail();
        $template = CmsTemplate::query()->where('import_key', 'starter:page-starter:template.page-detail')->firstOrFail();

        $this->assertSame(1, $result['imported']['pages']);
        $this->assertSame('draft', $page->status);
        $this->assertNull($page->published_at);
        $this->assertFalse((bool) $page->is_home);
        $this->assertSame($slug.'-2', $page->slug);
        $this->assertSame($template->id, $page->detail_template_id);
    }

    public function test_it_rejects_pages_with_unknown_template_mapping(): void
    {
        $file = $this->zipFile([
            'manifest.json' => json_encode([
                'type' => 'rwsoft-cms-starter',
                'key' => 'bad-page-starter',
                'modules' => ['layouts', 'pages'],
            ], JSON_THROW_ON_ERROR),
            'layouts.json' => json_encode([
                [
                    'import_key' => 'layout.main',
                    'name' => 'Main Layout',
                    'locale' => 'nl',
                ],
            ], JSON_THROW_ON_ERROR),
            'pages.json' => json_encode([
                [
                    'import_key' => 'page.home',
                    'layout_import_key' => 'layout.main',
                    'detail_template_import_key' => 'template.missing',
                    'title' => 'Home',
                    'slug' => 'home',
                    'locale' => 'nl',
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        try {
            app(ImportCmsStarterZipAction::class)->handle($file);
            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException) {
            $this->assertFalse(
                CmsPage::query()->where('settings->starter_import_key', 'starter:bad-page-starter:page.home')->exists()
            );
        }
    }

    public function test_it_imports_menus_inactive_and_links_items_to_imported_pages(): void
    {
        $location = 'starter-menu-'.uniqid();
        CmsMenu::query()->create([
            'title' => 'Existing menu',
            'location' => $location,
            'is_active' => true,
        ]);

        $file = $this->zipFile([
            'manifest.json' => json_encode([
                'type' => 'rwsoft-cms-starter',
                'key' => 'menu-starter',
                'modules' => ['layouts', 'templates', 'pages', 'menus'],
            ], JSON_THROW_ON_ERROR),
            'layouts.json' => json_encode([
                [
                    'import_key' => 'layout.main',
                    'name' => 'Main Layout',
                    'locale' => 'nl',
                    'sections' => [],
                ],
            ], JSON_THROW_ON_ERROR),
            'templates.json' => json_encode([
                [
                    'import_key' => 'template.page-detail',
                    'layout_import_key' => 'layout.main',
                    'name' => 'Page Detail',
                    'locale' => 'nl',
                    'template_class' => 'page',
                    'template_key' => 'page.detail',
                    'sections' => [],
                ],
            ], JSON_THROW_ON_ERROR),
            'pages.json' => json_encode([
                [
                    'import_key' => 'page.home',
                    'layout_import_key' => 'layout.main',
                    'detail_template_import_key' => 'template.page-detail',
                    'title' => 'Home',
                    'slug' => 'menu-starter-home-'.uniqid(),
                    'locale' => 'nl',
                    'sections' => [],
                ],
            ], JSON_THROW_ON_ERROR),
            'menus.json' => json_encode([
                [
                    'import_key' => 'menu.header',
                    'title' => 'Header menu',
                    'location' => $location,
                    'is_active' => true,
                    'items' => [
                        [
                            'import_key' => 'menu.header.home',
                            'type' => 'page',
                            'page_import_key' => 'page.home',
                            'label' => 'Home',
                            'locale' => 'nl',
                        ],
                        [
                            'import_key' => 'menu.header.contact',
                            'type' => 'custom',
                            'label' => 'Contact',
                            'url' => '/contact',
                            'locale' => 'nl',
                            'parent_import_key' => 'menu.header.home',
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $result = app(ImportCmsStarterZipAction::class)->handle($file);

        $menu = CmsMenu::query()
            ->where('settings->starter_import_key', 'starter:menu-starter:menu.header')
            ->firstOrFail();
        $page = CmsPage::query()
            ->where('settings->starter_import_key', 'starter:menu-starter:page.home')
            ->firstOrFail();
        $homeItem = CmsMenuItem::query()
            ->where('metadata->starter_import_key', 'starter:menu-starter:menu.header.home')
            ->firstOrFail();
        $contactItem = CmsMenuItem::query()
            ->where('metadata->starter_import_key', 'starter:menu-starter:menu.header.contact')
            ->firstOrFail();

        $this->assertSame(1, $result['imported']['menus']);
        $this->assertFalse((bool) $menu->is_active);
        $this->assertSame([], $menu->placements);
        $this->assertSame($page->id, $homeItem->cms_page_id);
        $this->assertSame('page', $homeItem->type);
        $this->assertSame('custom', $contactItem->type);
        $this->assertSame('/contact', $contactItem->url);
        $this->assertSame($homeItem->id, $contactItem->parent_id);
    }

    public function test_example_starter_zip_roundtrips_through_the_importer(): void
    {
        $export = app(BuildExampleCmsStarterZipAction::class)->handle();
        $this->temporaryFiles[] = $export['path'];

        $result = app(ImportCmsStarterZipAction::class)->handle(
            new UploadedFile($export['path'], $export['filename'], 'application/zip', null, true)
        );

        $layout = CmsLayout::query()->where('import_key', 'starter:example-starter:layout.main')->firstOrFail();
        $template = CmsTemplate::query()->where('import_key', 'starter:example-starter:template.page-detail')->firstOrFail();
        $page = CmsPage::query()->where('settings->starter_import_key', 'starter:example-starter:page.home')->firstOrFail();
        $menu = CmsMenu::query()->where('settings->starter_import_key', 'starter:example-starter:menu.header')->firstOrFail();
        $homeItem = CmsMenuItem::query()->where('metadata->starter_import_key', 'starter:example-starter:menu.header.home')->firstOrFail();

        $this->assertSame(['layouts' => 1, 'templates' => 1, 'pages' => 1, 'menus' => 1, 'site' => 0, 'languages' => 0, 'public_texts' => 0, 'redirects' => 0, 'forms' => 0, 'taxonomies' => 0, 'blogs' => 0, 'media' => 0, 'themes' => 0], $result['imported']);
        $this->assertFalse((bool) $layout->is_active);
        $this->assertFalse((bool) $layout->is_default);
        $this->assertFalse((bool) $template->is_active);
        $this->assertFalse((bool) $template->is_default);
        $this->assertSame('draft', $page->status);
        $this->assertNull($page->published_at);
        $this->assertFalse((bool) $page->is_home);
        $this->assertFalse((bool) $menu->is_active);
        $this->assertSame($layout->id, $template->layout_id);
        $this->assertSame($template->id, $page->detail_template_id);
        $this->assertSame($page->id, $homeItem->cms_page_id);

        $templateBlockTypes = $template->sections()
            ->with('placements.block')
            ->get()
            ->flatMap(fn ($section) => $section->placements->map(fn ($placement) => $placement->block?->type))
            ->values()
            ->all();
        $pageBlockTypes = $page->sections()
            ->with('placements.block')
            ->get()
            ->flatMap(fn ($section) => $section->placements->map(fn ($placement) => $placement->block?->type))
            ->values()
            ->all();

        $this->assertContains('content_slot', $templateBlockTypes);
        $this->assertContains('text', $pageBlockTypes);
        $this->assertContains('steps', $pageBlockTypes);
        $this->assertContains('icon_list', $pageBlockTypes);
        $this->assertContains('faq', $pageBlockTypes);
    }

    public function test_it_rejects_menu_items_with_unknown_page_mapping(): void
    {
        $file = $this->zipFile([
            'manifest.json' => json_encode([
                'type' => 'rwsoft-cms-starter',
                'key' => 'bad-menu-starter',
                'modules' => ['menus'],
            ], JSON_THROW_ON_ERROR),
            'menus.json' => json_encode([
                [
                    'import_key' => 'menu.header',
                    'title' => 'Header menu',
                    'items' => [
                        [
                            'import_key' => 'menu.header.home',
                            'type' => 'page',
                            'page_import_key' => 'page.missing',
                            'label' => 'Home',
                            'locale' => 'nl',
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        try {
            app(ImportCmsStarterZipAction::class)->handle($file);
            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException) {
            $this->assertFalse(
                CmsMenu::query()->where('settings->starter_import_key', 'starter:bad-menu-starter:menu.header')->exists()
            );
        }
    }

    public function test_it_rejects_unknown_block_types_before_import_commit(): void
    {
        $file = $this->zipFile([
            'manifest.json' => json_encode([
                'type' => 'rwsoft-cms-starter',
                'key' => 'unsafe-starter',
                'modules' => ['layouts', 'templates'],
            ], JSON_THROW_ON_ERROR),
            'layouts.json' => json_encode([
                [
                    'import_key' => 'layout.main',
                    'name' => 'Main Layout',
                    'locale' => 'nl',
                ],
            ], JSON_THROW_ON_ERROR),
            'templates.json' => json_encode([
                [
                    'import_key' => 'template.page-detail',
                    'layout_import_key' => 'layout.main',
                    'name' => 'Page Detail',
                    'locale' => 'nl',
                    'template_class' => 'page',
                    'template_key' => 'page.detail',
                    'sections' => [
                        'content' => [
                            [
                                'placements' => [
                                    [
                                        'block' => [
                                            'type' => 'unknown_block',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        try {
            app(ImportCmsStarterZipAction::class)->handle($file);
            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException) {
            $this->assertFalse(CmsLayout::query()->where('import_key', 'starter:unsafe-starter:layout.main')->exists());
        }
    }

    public function test_it_blocks_path_traversal_entries(): void
    {
        $file = $this->zipFile([
            'manifest.json' => json_encode(['type' => 'rwsoft-cms-starter'], JSON_THROW_ON_ERROR),
            '../evil.json' => '{}',
        ]);

        $this->expectException(ValidationException::class);

        app(ImportCmsStarterZipAction::class)->handle($file);
    }

    public function test_it_blocks_disallowed_extensions(): void
    {
        $file = $this->zipFile([
            'manifest.json' => json_encode(['type' => 'rwsoft-cms-starter'], JSON_THROW_ON_ERROR),
            'theme/scripts/app.js' => 'alert(1);',
        ]);

        $this->expectException(ValidationException::class);

        app(ImportCmsStarterZipAction::class)->handle($file);
    }

    public function test_it_rejects_unknown_manifest_modules(): void
    {
        $file = $this->zipFile([
            'manifest.json' => json_encode([
                'type' => 'rwsoft-cms-starter',
                'modules' => ['layouts', 'php'],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->expectException(ValidationException::class);

        app(ImportCmsStarterZipAction::class)->handle($file);
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function zipFile(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cms-starter-');
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
