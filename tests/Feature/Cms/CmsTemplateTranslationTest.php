<?php

namespace Tests\Feature\Cms;

use App\Actions\Admin\Cms\CreateCmsTemplateTranslationAction;
use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsTemplate;
use App\Support\Ai\CmsTemplateTranslationAiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsTemplateTranslationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'rwsoft_site_rwsoft',
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_template_translation_copies_structure_and_maps_target_locale_layout(): void
    {
        $layoutTranslationKey = (string) Str::ulid();
        $templateTranslationKey = (string) Str::ulid();
        $service = $this->createMock(CmsTemplateTranslationAiService::class);
        $service->expects($this->never())->method('translate');

        $sourceLayout = CmsLayout::query()->create([
            'name' => 'Basis NL',
            'locale' => 'nl',
            'translation_key' => $layoutTranslationKey,
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);

        $targetLayout = CmsLayout::query()->create([
            'name' => 'Base EN',
            'locale' => 'en',
            'translation_key' => $layoutTranslationKey,
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);

        $sourceTemplate = CmsTemplate::query()->create([
            'name' => 'NL Detail Template',
            'locale' => 'nl',
            'translation_key' => $templateTranslationKey,
            'layout_id' => $sourceLayout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => ['custom' => 'value'],
        ]);

        $section = $sourceTemplate->sections()->create([
            'zone' => 'content',
            'name' => 'Hero sectie',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => [],
        ]);

        $block = CmsBlock::query()->create([
            'type' => 'text',
            'name' => 'Hero block',
            'content' => ['title' => 'Welkom', 'text' => 'Originele tekst'],
            'settings' => [],
            'is_shared' => false,
            'is_dynamic' => false,
            'cache_strategy' => 'inherit',
        ]);

        $section->placements()->create([
            'cms_block_id' => $block->id,
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'height_mode' => 'auto',
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);

        $translation = (new CreateCmsTemplateTranslationAction($service))->handle($sourceTemplate->fresh(['layout', 'sections.placements.block']), 'en');
        $translation->load(['layout', 'sections.placements.block']);

        $this->assertSame('en', $translation->locale);
        $this->assertSame($templateTranslationKey, $translation->translation_key);
        $this->assertSame($sourceTemplate->id, $translation->translated_from_template_id);
        $this->assertSame($targetLayout->id, $translation->layout_id);
        $this->assertFalse($translation->is_default);
        $this->assertFalse($translation->is_active);
        $this->assertSame('Hero sectie', $translation->sections->first()?->name);
        $this->assertSame('Welkom', $translation->sections->first()?->placements->first()?->block?->content['title']);
    }

    public function test_template_translation_applies_ai_translated_labels(): void
    {
        $service = $this->createMock(CmsTemplateTranslationAiService::class);
        $service->expects($this->once())
            ->method('translate')
            ->willReturn([
                'name' => 'EN Detail Template',
                'sections' => [[
                    'index' => 0,
                    'name' => 'Hero section',
                    'placements' => [[
                        'index' => 0,
                        'title' => 'Welcome',
                        'text' => 'Translated text',
                    ]],
                ]],
            ]);

        $sourceLayout = CmsLayout::query()->create([
            'name' => 'Base EN',
            'locale' => 'en',
            'translation_key' => (string) Str::ulid(),
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);

        $sourceTemplate = CmsTemplate::query()->create([
            'name' => 'NL Detail Template',
            'locale' => 'nl',
            'translation_key' => (string) Str::ulid(),
            'layout_id' => $sourceLayout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);

        $section = $sourceTemplate->sections()->create([
            'zone' => 'content',
            'name' => 'Hero sectie',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => [],
        ]);

        $block = CmsBlock::query()->create([
            'type' => 'text',
            'name' => 'Hero block',
            'content' => ['title' => 'Welkom', 'text' => 'Originele tekst'],
            'settings' => [],
            'is_shared' => false,
            'is_dynamic' => false,
            'cache_strategy' => 'inherit',
        ]);

        $section->placements()->create([
            'cms_block_id' => $block->id,
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'height_mode' => 'auto',
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);

        $translation = (new CreateCmsTemplateTranslationAction($service))->handle($sourceTemplate->fresh(['layout', 'sections.placements.block']), 'en', true);
        $translation->load('sections.placements.block');

        $this->assertSame('EN Detail Template', $translation->name);
        $this->assertSame('Hero section', $translation->sections->first()?->name);
        $this->assertSame('Welcome', $translation->sections->first()?->placements->first()?->block?->content['title']);
        $this->assertSame('Translated text', $translation->sections->first()?->placements->first()?->block?->content['text']);
        $this->assertSame('ai', $translation->settings['translation_source'] ?? null);
        $this->assertSame('pending', $translation->settings['translation_review_status'] ?? null);
    }
}
