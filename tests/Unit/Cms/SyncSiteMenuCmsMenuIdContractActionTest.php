<?php

namespace Tests\Unit\Cms;

use App\Actions\Admin\Cms\SyncSiteMenuCmsMenuIdContractAction;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SyncSiteMenuCmsMenuIdContractActionTest extends TestCase
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

    public function test_it_syncs_site_menu_schema_and_existing_block_content(): void
    {
        $blockId = (int) DB::connection('tenant')
            ->table('cms_placeable_blocks')
            ->where('key', 'site_menu')
            ->value('id');

        $this->assertGreaterThan(0, $blockId);

        $siteMenuBlockId = $this->prepareStaleSiteMenuBlock($blockId);

        app(SyncSiteMenuCmsMenuIdContractAction::class)->handle('tenant');

        $placeableBlock = DB::connection('tenant')
            ->table('cms_placeable_blocks')
            ->where('id', $blockId)
            ->first();
        $revision = DB::connection('tenant')
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $blockId)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->orderByDesc('revision_number')
            ->first();
        $siteMenuBlock = DB::connection('tenant')
            ->table('cms_blocks')
            ->where('id', $siteMenuBlockId)
            ->first();

        $this->assertSame(['cms_menu_id'], json_decode($placeableBlock->schema, true, flags: JSON_THROW_ON_ERROR)['fields']);
        $this->assertSame(['content', 'header', 'footer'], json_decode($placeableBlock->allowed_zones, true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(['cms_menu_id'], json_decode($revision->schema, true, flags: JSON_THROW_ON_ERROR)['fields']);
        $this->assertSame(['content', 'header', 'footer'], json_decode($revision->allowed_zones, true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(['cms_menu_id' => $this->headerMenuId()], json_decode($siteMenuBlock->content, true, flags: JSON_THROW_ON_ERROR));
    }

    private function prepareStaleSiteMenuBlock(int $blockId): int
    {
        DB::connection('tenant')
            ->table('cms_placeable_blocks')
            ->where('id', $blockId)
            ->update([
                'schema' => json_encode(['fields' => ['menu_key', 'mobile_label']], JSON_THROW_ON_ERROR),
                'defaults' => json_encode(['menu_key' => 'header', 'mobile_label' => 'Menu'], JSON_THROW_ON_ERROR),
            ]);
        DB::connection('tenant')
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $blockId)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->update([
                'schema' => json_encode(['fields' => ['menu_key', 'mobile_label']], JSON_THROW_ON_ERROR),
                'defaults' => json_encode(['menu_key' => 'header', 'mobile_label' => 'Menu'], JSON_THROW_ON_ERROR),
            ]);

        $siteMenuBlockId = (int) DB::connection('tenant')
            ->table('cms_blocks')
            ->where('type', 'site_menu')
            ->whereIn('id', function ($query): void {
                $query->select('cms_block_id')
                    ->from('cms_block_placements')
                    ->join('cms_sections', 'cms_sections.id', '=', 'cms_block_placements.cms_section_id')
                    ->where('cms_sections.zone', 'header')
                    ->where('cms_sections.is_active', true)
                    ->where('cms_block_placements.is_active', true);
            })
            ->orderBy('id')
            ->value('id');

        $this->assertGreaterThan(0, $siteMenuBlockId);

        DB::connection('tenant')
            ->table('cms_blocks')
            ->where('id', $siteMenuBlockId)
            ->update([
                'content' => json_encode(['menu_key' => 'header', 'mobile_label' => 'Menu'], JSON_THROW_ON_ERROR),
            ]);

        return $siteMenuBlockId;
    }

    private function headerMenuId(): int
    {
        return (int) DB::connection('tenant')
            ->table('cms_menus')
            ->where('is_active', true)
            ->whereJsonContains('placements', 'header')
            ->orderBy('id')
            ->value('id');
    }
}
