<?php

namespace Tests\Unit\Cms;

use App\Actions\Admin\Cms\Starters\BuildCmsStarterZipFromSelectionAction;
use App\Actions\Admin\Cms\Starters\BuildExampleCmsStarterZipAction;
use App\Actions\Admin\Cms\Starters\ImportCmsStarterZipAction;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class BuildCmsStarterZipFromSelectionActionTest extends TestCase
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

    public function test_it_exports_a_selection_as_importable_starter_zip(): void
    {
        $example = app(BuildExampleCmsStarterZipAction::class)->handle();
        $this->temporaryFiles[] = $example['path'];

        app(ImportCmsStarterZipAction::class)->handle(
            new UploadedFile($example['path'], $example['filename'], 'application/zip', null, true)
        );

        $layout = CmsLayout::query()->where('import_key', 'starter:example-starter:layout.main')->firstOrFail();
        $template = CmsTemplate::query()->where('import_key', 'starter:example-starter:template.page-detail')->firstOrFail();
        $page = CmsPage::query()->where('settings->starter_import_key', 'starter:example-starter:page.home')->firstOrFail();
        $menu = CmsMenu::query()->where('settings->starter_import_key', 'starter:example-starter:menu.header')->firstOrFail();

        $export = app(BuildCmsStarterZipFromSelectionAction::class)->handle([
            'starter_key' => 'exported-starter',
            'starter_name' => 'Exported Starter',
            'layout_id' => $layout->id,
            'template_id' => $template->id,
            'page_id' => $page->id,
            'menu_id' => $menu->id,
        ]);
        $this->temporaryFiles[] = $export['path'];

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($export['path']));

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $templates = json_decode((string) $zip->getFromName('templates.json'), true, flags: JSON_THROW_ON_ERROR);
        $pages = json_decode((string) $zip->getFromName('pages.json'), true, flags: JSON_THROW_ON_ERROR);
        $menus = json_decode((string) $zip->getFromName('menus.json'), true, flags: JSON_THROW_ON_ERROR);

        $zip->close();

        $this->assertSame('exported-starter', $manifest['key']);
        $this->assertArrayNotHasKey('layout_import_key', $pages[0]);
        $this->assertSame('template.page-detail', $pages[0]['detail_template_import_key']);
        $this->assertSame('intro_text', $templates[0]['data_contract']['template_fields'][0]['key']);
        $this->assertArrayHasKey('template_data', $pages[0]);
        $this->assertArrayNotHasKey('sections', $pages[0]);
        $this->assertSame('page.home', $menus[0]['items'][0]['page_import_key']);

        $result = app(ImportCmsStarterZipAction::class)->handle(
            new UploadedFile($export['path'], $export['filename'], 'application/zip', null, true)
        );

        $this->assertSame(['layouts' => 1, 'templates' => 1, 'pages' => 1, 'menus' => 1, 'site' => 0, 'languages' => 0, 'public_texts' => 0, 'redirects' => 0, 'forms' => 0, 'taxonomies' => 0, 'blogs' => 0, 'media' => 0, 'themes' => 0], $result['imported']);
        $this->assertTrue(CmsPage::query()->where('settings->starter_import_key', 'starter:exported-starter:page.home')->exists());
    }
}
