<?php

namespace Tests\Unit\Cms;

use App\Actions\Admin\Cms\Themes\CompileThemeCssAction;
use App\Actions\Platform\ConfigureTenantDatabaseAction;
use App\Models\Cms\CmsTheme;
use App\Models\Platform\Site;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompileThemeCssActionTest extends TestCase
{
    private ?string $themeStoragePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'central',
            'database.connections.central' => array_merge(config('database.connections.mysql'), [
                'database' => 'rwsoft',
            ]),
            'database.connections.tenant' => array_merge(config('database.connections.mysql'), [
                'database' => 'rwsoft',
            ]),
            'cms_themes.storage_disk' => 'local',
        ]);

        DB::purge('central');
        DB::purge('tenant');
        DB::reconnect('central');

        $site = Site::on('central')->firstOrFail();
        app(ConfigureTenantDatabaseAction::class)->handle($site);

        DB::connection('tenant')->beginTransaction();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        if ($this->themeStoragePath) {
            Storage::disk('local')->deleteDirectory($this->themeStoragePath);
        }

        parent::tearDown();
    }

    public function test_generated_css_is_written_after_developer_css_in_minified_output(): void
    {
        $theme = CmsTheme::query()->create([
            'key' => 'compile-order-test',
            'name' => 'Compile Order Test',
            'version' => '1.0.0',
            'status' => 'draft',
        ]);
        $this->themeStoragePath = 'sites/'.TenantContext::siteId().'/themes/'.$theme->key;

        $result = app(CompileThemeCssAction::class)->handle(
            $theme,
            '.rw-public-title { color: #000000; }',
            ['h1_color' => '#ff0000'],
        );

        $css = Storage::disk('local')->get($result['version']->minified_css_path);

        $developerPosition = strpos($css, 'color:#000000');
        $generatedPosition = strpos($css, 'color:#ff0000');

        $this->assertIsInt($developerPosition);
        $this->assertIsInt($generatedPosition);
        $this->assertGreaterThan($developerPosition, $generatedPosition);
    }
}
