<?php

namespace Tests\Feature\PublicSite;

use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsTemplate;
use App\Support\PublicSite\CmsPageCompositionBuilder;
use App\Support\PublicSite\CmsTemplateCompositionBuilder;

class PublicCmsCompositionBuilderTest extends PublicCmsTestCase
{
    public function test_composition_uses_default_layout_and_page_sections(): void
    {
        $layout = $this->createLayout(['is_default' => true]);
        $headerSection = $this->createSection($layout, ['zone' => 'header', 'name' => 'Header']);
        $this->createPlacement($headerSection, $this->createBlock([
            'name' => 'Header blok',
            'content' => ['title' => 'Layout header', 'text' => 'Header tekst'],
        ]));

        $page = $this->createPage(['title' => 'Composition page']);
        $contentSection = $this->createSection($page, [
            'zone' => 'content',
            'name' => 'Content',
            'settings' => [
                'layout_type' => 'hero',
                'width_mode' => 'display',
                'spacing' => 'spacious',
                'background_color' => '#2563eb',
            ],
        ]);
        $this->createPlacement($contentSection, $this->createBlock([
            'name' => 'Content blok',
            'content' => ['title' => 'Pagina intro', 'text' => 'Pagina tekst'],
        ]), [
            'desktop_span' => 6,
        ]);

        $composition = app(CmsPageCompositionBuilder::class)->handle($page);

        $this->assertSame($layout->id, $composition['layout']['id']);
        $this->assertSame('Layout header', $composition['sections']['header'][0]['placements'][0]['block']['title']);
        $this->assertSame('Pagina intro', $composition['sections']['content'][0]['placements'][0]['block']['title']);
        $this->assertSame('hero', $composition['sections']['content'][0]['settings']['layout_type']);
        $this->assertSame('display', $composition['sections']['content'][0]['settings']['width_mode']);
        $this->assertSame('spacious', $composition['sections']['content'][0]['settings']['spacing']);
        $this->assertSame('#2563eb', $composition['sections']['content'][0]['settings']['background_color']);
        $this->assertSame(6, $composition['sections']['content'][0]['placements'][0]['desktop_span']);
        $this->assertSame([], $composition['sections']['footer']);
    }

    public function test_page_override_changes_layout_block_only_for_composition(): void
    {
        $layout = $this->createLayout(['is_default' => true]);
        $headerSection = $this->createSection($layout, ['zone' => 'header']);
        $placement = $this->createPlacement($headerSection, $this->createBlock([
            'content' => ['title' => 'Globale titel', 'text' => 'Globale tekst'],
            'settings' => ['tone' => 'global'],
        ]), [
            'settings' => ['width' => 'normal'],
        ]);
        $page = $this->createPage();

        $page->blockOverrides()->create([
            'cms_block_placement_id' => $placement->id,
            'content' => ['title' => 'Lokale titel'],
            'settings' => ['tone' => 'local'],
            'is_active' => true,
        ]);

        $composition = app(CmsPageCompositionBuilder::class)->handle($page);
        $payload = $composition['sections']['header'][0]['placements'][0];

        $this->assertSame('Lokale titel', $payload['block']['title']);
        $this->assertSame('Globale tekst', $payload['block']['text']);
        $this->assertTrue($payload['has_override']);
        $this->assertSame('local', $payload['settings']['tone']);
        $this->assertSame('normal', $payload['settings']['width']);
    }

    public function test_form_block_composition_uses_form_translation_key(): void
    {
        $form = $this->createForm('nl');
        $page = $this->createPage(['locale' => 'nl']);
        $section = $this->createSection($page);
        $this->createPlacement($section, $this->createBlock([
            'type' => 'form',
            'content' => ['form_translation_key' => $form->translation_key],
        ]));

        $composition = app(CmsPageCompositionBuilder::class)->handle($page);

        $block = $composition['sections']['content'][0]['placements'][0]['block'];

        $this->assertSame('form', $block['type']);
        $this->assertSame($form->translation_key, $block['form_translation_key']);
        $this->assertSame('nl', $block['locale']);
    }

    public function test_page_exclusion_detaches_layout_block_from_composition(): void
    {
        $layout = $this->createLayout(['is_default' => true]);
        $headerSection = $this->createSection($layout, ['zone' => 'header']);
        $placement = $this->createPlacement($headerSection, $this->createBlock([
            'content' => ['title' => 'Te detachen', 'text' => 'Niet tonen'],
        ]));
        $page = $this->createPage();

        $page->blockExclusions()->create([
            'cms_block_placement_id' => $placement->id,
            'reason' => 'Niet tonen op deze pagina',
        ]);

        $composition = app(CmsPageCompositionBuilder::class)->handle($page);

        $this->assertSame([], $composition['sections']['header']);
    }

    public function test_shared_all_pages_placement_is_added_to_page_content(): void
    {
        $targetPage = $this->createPage(['title' => 'Target page']);
        $sourcePage = $this->createPage(['title' => 'Shared source']);
        $sharedSection = $this->createSection($sourcePage, ['zone' => 'content', 'name' => 'Shared section']);
        $placement = $this->createPlacement($sharedSection, $this->createBlock([
            'name' => 'Shared CTA',
            'content' => ['title' => 'Gedeeld blok', 'text' => 'Komt overal terug'],
            'is_shared' => true,
        ]));
        $placement->scopes()->create([
            'scope_type' => 'all_pages',
            'scope_value' => null,
            'locale' => 'nl',
            'is_active' => true,
        ]);

        $composition = app(CmsPageCompositionBuilder::class)->handle($targetPage);

        $this->assertSame('shared', $composition['sections']['content'][0]['source']);
        $this->assertSame('Gedeeld blok', $composition['sections']['content'][0]['placements'][0]['block']['title']);
    }

    public function test_page_composition_includes_child_slot_placements(): void
    {
        $textDefinition = CmsPlaceableBlock::query()
            ->where('key', 'text')
            ->firstOrFail();
        $slotSchema = [[
            'key' => 'actions',
            'label' => 'Actions',
            'allowed_block_keys' => ['button'],
            'layout' => 'inline',
            'responsive' => 'wrap_mobile',
        ]];

        $textDefinition->forceFill([
            'schema' => array_replace_recursive($textDefinition->schema ?? [], [
                'slots' => $slotSchema,
            ]),
        ])->save();
        $textDefinition->revisions()->where('status', 'published')->update([
            'schema' => $textDefinition->schema,
        ]);

        $page = $this->createPage(['title' => 'Slot composition page']);
        $section = $this->createSection($page);
        $parentPlacement = $this->createPlacement($section, $this->createBlock([
            'type' => 'text',
            'content' => ['title' => 'Parent title', 'text' => 'Parent text'],
        ]));
        $parentPlacement->childPlacements()->create([
            'cms_block_id' => $this->createBlock([
                'type' => 'button',
                'content' => ['label' => 'Read more', 'url' => '/read-more'],
            ])->id,
            'slot_key' => 'actions',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'height_mode' => 'auto',
            'height_value' => null,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);

        $composition = app(CmsPageCompositionBuilder::class)->handle($page);
        $slotPlacement = $composition['sections']['content'][0]['placements'][0]['slots']['actions']['placements'][0];

        $this->assertSame('Read more', $slotPlacement['block']['label']);
        $this->assertSame('/read-more', $slotPlacement['block']['url']);
    }

    public function test_template_composition_suffixes_duplicate_page_editable_content_keys(): void
    {
        $layout = $this->createLayout(['locale' => 'nl']);
        $template = CmsTemplate::query()->create([
            'name' => 'Duplicate content key template',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $firstSection = $this->createSection($template, [
            'name' => 'First rich text section',
            'zone' => 'content',
            'sort_order' => 0,
        ]);
        $secondSection = $this->createSection($template, [
            'name' => 'Second rich text section',
            'zone' => 'content',
            'sort_order' => 10,
        ]);

        $this->createPlacement($firstSection, $this->createBlock([
            'type' => 'rich_text',
            'content' => ['html' => '<p>Default first</p>'],
        ]), [
            'settings' => [
                'content_key' => 'rich_text',
                'page_editable' => true,
                'page_editable_fields' => ['html'],
            ],
        ]);
        $this->createPlacement($secondSection, $this->createBlock([
            'type' => 'rich_text',
            'content' => ['html' => '<p>Default second</p>'],
        ]), [
            'settings' => [
                'content_key' => 'rich_text',
                'page_editable' => true,
                'page_editable_fields' => ['html'],
            ],
        ]);
        $page = $this->createPage([
            'locale' => 'nl',
            'detail_template_id' => $template->id,
            'template_data' => [
                'blocks' => [
                    'rich_text' => ['html' => '<p>First page rich text</p>'],
                    'rich_text_2' => ['html' => '<p>Second page rich text</p>'],
                ],
            ],
        ]);

        $composition = app(CmsTemplateCompositionBuilder::class)->handle(
            $template,
            [
                'locale' => 'nl',
                'template_data' => $page->template_data,
            ],
            ['page' => [], 'template' => []],
            [],
            $page,
        );
        $placements = collect($composition['sections']['content'])
            ->flatMap(fn (array $section): array => $section['placements'] ?? [])
            ->values();

        $this->assertSame('<p>First page rich text</p>', $placements[0]['block']['html']);
        $this->assertSame('<p>Second page rich text</p>', $placements[1]['block']['html']);
    }

    public function test_footer_sections_are_split_by_scroll_behavior(): void
    {
        $layout = $this->createLayout(['is_default' => true]);
        $scrollFooterSection = $this->createSection($layout, [
            'zone' => 'footer',
            'name' => 'Scroll footer section',
            'settings' => ['scroll_behavior' => 'normal'],
        ]);
        $this->createPlacement($scrollFooterSection, $this->createBlock([
            'content' => ['title' => 'Scroll footer', 'text' => 'Scrollt mee'],
        ]));
        $stickyFooterSection = $this->createSection($layout, [
            'zone' => 'footer',
            'name' => 'Sticky footer section',
            'settings' => ['scroll_behavior' => 'sticky'],
        ]);
        $this->createPlacement($stickyFooterSection, $this->createBlock([
            'content' => ['title' => 'Sticky footer', 'text' => 'Blijft staan'],
        ]));

        $composition = app(CmsPageCompositionBuilder::class)->handle($this->createPage());

        $this->assertSame('Scroll footer', $composition['sections']['footer_scroll'][0]['placements'][0]['block']['title']);
        $this->assertSame('Sticky footer', $composition['sections']['footer_sticky'][0]['placements'][0]['block']['title']);
    }

    public function test_header_sections_are_split_by_scroll_behavior(): void
    {
        $layout = $this->createLayout(['is_default' => true]);
        $scrollHeaderSection = $this->createSection($layout, [
            'zone' => 'header',
            'name' => 'Scroll header section',
            'settings' => ['scroll_behavior' => 'normal'],
        ]);
        $this->createPlacement($scrollHeaderSection, $this->createBlock([
            'content' => ['title' => 'Scroll header', 'text' => 'Scrollt mee'],
        ]));
        $stickyHeaderSection = $this->createSection($layout, [
            'zone' => 'header',
            'name' => 'Auto-hide header section',
            'settings' => ['scroll_behavior' => 'auto_hide'],
        ]);
        $this->createPlacement($stickyHeaderSection, $this->createBlock([
            'content' => ['title' => 'Sticky header', 'text' => 'Blijft staan'],
        ]));

        $composition = app(CmsPageCompositionBuilder::class)->handle($this->createPage());

        $this->assertSame('Scroll header', $composition['sections']['header_scroll'][0]['placements'][0]['block']['title']);
        $this->assertSame('Sticky header', $composition['sections']['header_sticky'][0]['placements'][0]['block']['title']);
    }
}
