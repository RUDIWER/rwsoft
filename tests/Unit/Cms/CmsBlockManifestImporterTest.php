<?php

namespace Tests\Unit\Cms;

use App\Models\Cms\CmsPlaceableBlock;
use App\Support\Cms\Blocks\CmsBlockManifestImporter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CmsBlockManifestImporterTest extends TestCase
{
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

        TenantContext::clear();

        parent::tearDown();
    }

    public function test_it_imports_and_publishes_manifest_blocks(): void
    {
        $imported = app(CmsBlockManifestImporter::class)->import($this->manifest(), userId: null, publish: true);

        $this->assertCount(1, $imported);

        $block = CmsPlaceableBlock::query()->where('key', 'package_notice')->firstOrFail();

        $this->assertSame('package', $block->source);
        $this->assertSame('rwsoft.test', $block->package_key);
        $this->assertSame('published', $block->status);
        $this->assertSame(['content'], $block->allowed_zones);
        $this->assertEquals(['category' => 'content', 'fields' => ['title'], 'editor_fields' => [], 'preview' => []], $block->schema);
        $this->assertSame(1, $block->revisions()->where('status', 'published')->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        return [
            'manifest_version' => 1,
            'package_key' => 'rwsoft.test',
            'blocks' => [[
                'key' => 'package_notice',
                'name' => 'Package notice',
                'category' => 'content',
                'source' => 'package',
                'allowed_zones' => ['content'],
                'rendering_mode' => 'safe_blade',
                'renderer_key' => 'package_notice',
                'template_source' => '<article>{{ block.title }}</article>',
                'css_source' => '.package-notice { color: green; }',
                'schema' => ['fields' => ['title'], 'editor_fields' => [], 'preview' => []],
                'defaults' => ['title' => 'Default title'],
            ]],
        ];
    }
}
