<?php

namespace Tests\Unit\Cms;

use App\Actions\Admin\Cms\Starters\BuildExampleCmsStarterZipAction;
use Tests\TestCase;
use ZipArchive;

class BuildExampleCmsStarterZipActionTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_it_builds_a_valid_example_starter_zip(): void
    {
        $export = app(BuildExampleCmsStarterZipAction::class)->handle();
        $this->temporaryFiles[] = $export['path'];

        $this->assertSame('rwsoft-example-starter.zip', $export['filename']);
        $this->assertFileExists($export['path']);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($export['path']));

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $layouts = json_decode((string) $zip->getFromName('layouts.json'), true, flags: JSON_THROW_ON_ERROR);
        $templates = json_decode((string) $zip->getFromName('templates.json'), true, flags: JSON_THROW_ON_ERROR);
        $pages = json_decode((string) $zip->getFromName('pages.json'), true, flags: JSON_THROW_ON_ERROR);
        $menus = json_decode((string) $zip->getFromName('menus.json'), true, flags: JSON_THROW_ON_ERROR);

        $zip->close();

        $this->assertSame('rwsoft-cms-starter', $manifest['type']);
        $this->assertSame(['layouts', 'templates', 'pages', 'menus'], $manifest['modules']);
        $this->assertSame('layout.main', $layouts[0]['import_key']);
        $this->assertSame('layout.main', $templates[0]['layout_import_key']);
        $this->assertSame('template.page-detail', $pages[0]['detail_template_import_key']);
        $this->assertSame('page.home', $menus[0]['items'][0]['page_import_key']);
        $this->assertSame('site_header', $layouts[0]['sections']['header'][0]['placements'][0]['block']['type']);
        $this->assertSame('template.intro_text', $templates[0]['sections']['content'][1]['placements'][0]['block']['field_key']);
        $this->assertSame('intro_text', $templates[0]['data_contract']['template_fields'][0]['key']);
        $this->assertArrayHasKey('template_data', $pages[0]);
        $this->assertArrayNotHasKey('sections', $pages[0]);
    }
}
