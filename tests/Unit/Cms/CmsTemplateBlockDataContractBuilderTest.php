<?php

namespace Tests\Unit\Cms;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsPlaceableBlockRevision;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsTemplate;
use App\Support\Cms\CmsTemplateBlockDataContractBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

class CmsTemplateBlockDataContractBuilderTest extends TestCase
{
    public function test_builds_contract_for_multiple_instances_of_same_block_type(): void
    {
        $template = $this->templateWithPlacements([
            $this->placement('hero_main', 10),
            $this->placement('hero_secondary', 20),
        ]);

        $contract = app(CmsTemplateBlockDataContractBuilder::class)->handle($template);

        $this->assertCount(2, $contract['blocks']);
        $this->assertSame('hero_main', $contract['blocks'][0]['content_key']);
        $this->assertSame('hero_secondary', $contract['blocks'][1]['content_key']);
        $this->assertSame('feature_card', $contract['blocks'][0]['renderer_key']);
        $this->assertSame(['title', 'text'], collect($contract['blocks'][0]['fields'])->pluck('key')->all());
    }

    public function test_builds_validation_rules_for_each_content_key(): void
    {
        $template = $this->templateWithPlacements([
            $this->placement('hero_main', 10),
            $this->placement('hero_secondary', 20),
        ]);

        $rules = app(CmsTemplateBlockDataContractBuilder::class)->validationRules($template);

        $this->assertArrayHasKey('template_data.blocks.hero_main.title', $rules);
        $this->assertArrayHasKey('template_data.blocks.hero_main.text', $rules);
        $this->assertArrayHasKey('template_data.blocks.hero_secondary.title', $rules);
        $this->assertArrayNotHasKey('template_data.blocks.orphan.title', $rules);
    }

    public function test_cleans_template_data_to_current_block_content_keys(): void
    {
        $template = $this->templateWithPlacements([
            $this->placement('hero_main', 10),
            $this->placement('hero_secondary', 20),
        ]);

        $clean = app(CmsTemplateBlockDataContractBuilder::class)->cleanTemplateData($template, [
            'blocks' => [
                'hero_main' => [
                    'title' => 'Main hero',
                    'text' => 'Intro',
                    'unknown' => 'drop',
                ],
                'hero_secondary' => [
                    'title' => 'Secondary hero',
                ],
                'orphan' => [
                    'title' => 'Drop me',
                ],
            ],
        ]);

        $this->assertSame([
            'blocks' => [
                'hero_main' => [
                    'title' => 'Main hero',
                    'text' => 'Intro',
                ],
                'hero_secondary' => [
                    'title' => 'Secondary hero',
                ],
            ],
        ], $clean);
    }

    public function test_suffixes_duplicate_content_keys_in_same_section(): void
    {
        $template = $this->templateWithPlacements([
            $this->placement('hero_main', 10),
            $this->placement('hero_main', 20),
        ]);

        $contract = app(CmsTemplateBlockDataContractBuilder::class)->handle($template);

        $this->assertCount(2, $contract['blocks']);
        $this->assertSame('hero_main', $contract['blocks'][0]['content_key']);
        $this->assertSame('hero_main_2', $contract['blocks'][1]['content_key']);
    }

    public function test_suffixes_duplicate_content_keys_across_sections(): void
    {
        $template = $this->templateWithSections([
            [$this->placement('hero_main', 10)],
            [$this->placement('hero_main', 20)],
        ]);

        $contract = app(CmsTemplateBlockDataContractBuilder::class)->handle($template);

        $this->assertCount(2, $contract['blocks']);
        $this->assertSame('hero_main', $contract['blocks'][0]['content_key']);
        $this->assertSame('hero_main_2', $contract['blocks'][1]['content_key']);
    }

    /**
     * @param  array<int, CmsBlockPlacement>  $placements
     */
    private function templateWithPlacements(array $placements): CmsTemplate
    {
        return $this->templateWithSections([$placements]);
    }

    /**
     * @param  array<int, array<int, CmsBlockPlacement>>  $sections
     */
    private function templateWithSections(array $sections): CmsTemplate
    {
        $templateSections = collect($sections)
            ->map(function (array $placements, int $index): CmsSection {
                $section = new CmsSection([
                    'zone' => 'content',
                    'name' => 'Content '.($index + 1),
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ]);
                $section->setRelation('placements', new EloquentCollection($placements));

                return $section;
            });

        $template = new CmsTemplate([
            'template_key' => 'page.detail',
            'locale' => 'en',
            'is_active' => true,
        ]);
        $template->setRelation('sections', new EloquentCollection($templateSections));

        return $template;
    }

    private function placement(string $contentKey, int $sortOrder): CmsBlockPlacement
    {
        $revision = new CmsPlaceableBlockRevision([
            'revision_number' => 1,
            'status' => 'published',
            'renderer_key' => 'feature_card',
            'schema' => [
                'fields' => ['title', 'text'],
                'editor_fields' => [
                    ['name' => 'title', 'type' => 'text'],
                    ['name' => 'text', 'type' => 'textarea'],
                ],
            ],
            'defaults' => [
                'title' => null,
                'text' => null,
            ],
            'published_at' => now(),
        ]);
        $revision->forceFill(['id' => 100 + $sortOrder]);

        $block = new CmsBlock([
            'cms_placeable_block_id' => 5,
            'placeable_block_revision_id' => $revision->id,
            'settings' => [],
        ]);
        $block->forceFill(['id' => 200 + $sortOrder]);
        $block->setRelation('placeableBlockRevision', $revision);

        $placement = new CmsBlockPlacement([
            'cms_block_id' => $block->id,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'settings' => [
                'content_key' => $contentKey,
                'editor_label' => 'Hero '.$sortOrder,
            ],
        ]);
        $placement->forceFill(['id' => 300 + $sortOrder]);
        $placement->setRelation('block', $block);

        return $placement;
    }
}
