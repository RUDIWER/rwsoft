<?php

namespace Tests\Unit;

use App\Support\PublicSite\CmsBlockPayloadBuilder;
use Tests\TestCase;

class PublicBlockDefinitionRuntimeRenderingTest extends TestCase
{
    public function test_content_block_wrapper_renders_definition_runtime_metadata(): void
    {
        $this->configureTextBlockRuntimeMetadata();

        $view = $this->view('public.system.partials.blocks', [
            'blocks' => [[
                'type' => 'text',
                'title' => 'Runtime metadata',
                'text' => 'Content block.',
            ]],
            'contentItem' => [],
            'site' => ['current_locale' => 'nl'],
        ]);

        $view->assertSee('class="rw-public-content-block safe-runtime-class"', false);
        $view->assertSee('data-cms-behavior="accordion"', false);
        $view->assertSee('--rw-runtime-color: red', false);
        $view->assertDontSee('invalid;class', false);
        $view->assertDontSee('--rw-invalid', false);
    }

    public function test_placement_wrapper_renders_definition_runtime_metadata(): void
    {
        $this->configureTextBlockRuntimeMetadata();

        $view = $this->view('public.system.partials.placement', [
            'placement' => [
                'id' => 123,
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => true,
                'mobile_span' => 12,
                'tablet_span' => 12,
                'desktop_span' => 12,
                'settings' => [],
                'block' => [
                    'type' => 'text',
                    'title' => 'Runtime metadata',
                    'text' => 'Placement block.',
                ],
            ],
            'contentItem' => [],
        ]);

        $view->assertSee('data-cms-placement-id="123"', false);
        $view->assertSee('safe-runtime-class', false);
        $view->assertSee('data-cms-behavior="accordion"', false);
        $view->assertSee('--rw-runtime-color: red', false);
        $view->assertDontSee('invalid;class', false);
        $view->assertDontSee('--rw-invalid', false);
    }

    public function test_dynamic_field_scalar_renders_without_card_wrapper(): void
    {
        $view = $this->view('public.system.blocks.dynamic-field', [
            'block' => [
                'renderer_key' => 'dynamic_field',
                'field_key' => 'template.hero.title',
                'title' => 'Optional label',
                'value' => 'Hero title',
                'value_type' => 'scalar',
                'heading_level' => 'h1',
            ],
            'contentItem' => ['title' => 'Page title'],
            'site' => ['current_locale' => 'en'],
        ]);

        $view->assertSee('Hero title');
        $view->assertSee('<h2 class="rw-public-block__title">Optional label</h2>', false);
        $view->assertSee('<h1 class="rw-public-title">Hero title</h1>', false);
        $view->assertSee('rw-public-block--dynamic-field', false);
        $view->assertDontSee('rw-public-block rw-public-block--dynamic-field', false);
        $view->assertDontSee('rw-public-card', false);
    }

    public function test_section_and_placement_wrappers_render_stable_html_anchors(): void
    {
        $view = $this->view('public.system.partials.sections', [
            'sections' => [[
                'id' => 456,
                'zone' => 'content',
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => true,
                'settings' => [
                    'html_anchor' => 'intro-section',
                    'layout_type' => 'grid',
                ],
                'placements' => [[
                    'id' => 123,
                    'visible_mobile' => true,
                    'visible_tablet' => true,
                    'visible_desktop' => true,
                    'mobile_span' => 12,
                    'tablet_span' => 12,
                    'desktop_span' => 12,
                    'settings' => ['html_anchor' => 'intro-text-block'],
                    'block' => [
                        'renderer_key' => 'text',
                        'type' => 'text',
                        'title' => 'Anchored block',
                        'text' => 'Anchored content.',
                    ],
                ]],
            ]],
            'contentItem' => [],
            'site' => ['current_locale' => 'nl'],
        ]);

        $view->assertSee('id="intro-section"', false);
        $view->assertSee('data-cms-anchor="intro-section"', false);
        $view->assertSee('data-cms-section-id="456"', false);
        $view->assertSee('id="intro-text-block"', false);
        $view->assertSee('data-cms-anchor="intro-text-block"', false);
        $view->assertSee('data-cms-placement-id="123"', false);
        $view->assertSee('data-cms-block-type="text"', false);
        $view->assertSee('data-cms-renderer="text"', false);
    }

    public function test_content_block_wrapper_renders_definition_safe_blade_template_with_escaped_block_data(): void
    {
        $this->configureTextBlockSafeBladeTemplate();

        $view = $this->view('public.system.partials.blocks', [
            'blocks' => [[
                'type' => 'text',
                'title' => 'SafeBlade title',
                'text' => '<script>alert(1)</script>',
            ]],
            'contentItem' => [],
            'site' => ['current_locale' => 'nl'],
        ]);

        $view->assertSee('<article class="safe-blade-text">', false);
        $view->assertSee('<h2>SafeBlade title</h2>', false);
        $view->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $view->assertSee('{{ block.unknown }}', false);
        $view->assertDontSee('<script>alert(1)</script>', false);
        $view->assertDontSee('rw-public-block--text', false);
    }

    public function test_placement_wrapper_renders_definition_safe_blade_template_with_escaped_block_data(): void
    {
        $this->configureTextBlockSafeBladeTemplate();

        $view = $this->view('public.system.partials.placement', [
            'placement' => [
                'id' => 123,
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => true,
                'mobile_span' => 12,
                'tablet_span' => 12,
                'desktop_span' => 12,
                'settings' => [],
                'block' => [
                    'type' => 'text',
                    'title' => 'SafeBlade placement',
                    'text' => '<img src=x onerror=alert(1)>',
                ],
            ],
            'contentItem' => [],
        ]);

        $view->assertSee('<h2>SafeBlade placement</h2>', false);
        $view->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
        $view->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_simple_public_blocks_render_through_definition_safe_blade_templates(): void
    {
        $view = $this->view('public.system.partials.blocks', [
            'blocks' => [
                [
                    'type' => 'text',
                    'title' => 'Tekst titel',
                    'text' => '<script>alert(1)</script>',
                ],
                [
                    'type' => 'feature_card',
                    'title' => 'Feature titel',
                    'text' => '<img src=x onerror=alert(1)>',
                ],
                [
                    'type' => 'quote',
                    'text' => 'Quote tekst',
                    'source' => '<b>Bron</b>',
                ],
                [
                    'type' => 'stats',
                    'value' => '42',
                    'suffix' => '<small>%</small>',
                    'label' => 'Aantal',
                ],
            ],
            'contentItem' => [],
            'site' => ['current_locale' => 'nl'],
        ]);

        $view->assertSee('rw-public-block--text', false);
        $view->assertSee('rw-public-block--feature-card', false);
        $view->assertSee('rw-public-block--quote', false);
        $view->assertSee('rw-public-block--stats', false);
        $view->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $view->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
        $view->assertSee('&lt;b&gt;Bron&lt;/b&gt;', false);
        $view->assertSee('&lt;small&gt;%&lt;/small&gt;', false);
        $view->assertDontSee('<script>alert(1)</script>', false);
        $view->assertDontSee('<img src=x onerror=alert(1)>', false);
        $view->assertDontSee('<b>Bron</b>', false);
        $view->assertDontSee('<small>%</small>', false);
    }

    public function test_behavior_blocks_render_registry_driven_markup_and_behavior_attributes(): void
    {
        $view = $this->view('public.system.partials.blocks', [
            'blocks' => [
                [
                    'type' => 'accordion',
                    'runtime_id' => 'cms-accordion-test',
                    'items' => [
                        [
                            'runtime_id' => 'cms-accordion-item-test',
                            'title' => 'Vraag',
                            'text' => '<script>alert(1)</script>',
                        ],
                    ],
                ],
                [
                    'type' => 'tabs',
                    'runtime_id' => 'cms-tabs-test',
                    'items' => [
                        [
                            'runtime_id' => 'cms-tabs-item-one',
                            'is_first' => true,
                            'title' => 'Tab 1',
                            'text' => 'Eerste inhoud',
                        ],
                        [
                            'runtime_id' => 'cms-tabs-item-two',
                            'is_first' => false,
                            'title' => 'Tab 2',
                            'text' => '<img src=x onerror=alert(1)>',
                        ],
                    ],
                ],
                [
                    'type' => 'carousel',
                    'runtime_id' => 'cms-carousel-test',
                    'items' => [
                        [
                            'runtime_id' => 'cms-carousel-item-one',
                            'is_first' => true,
                            'title' => 'Slide 1',
                            'text' => 'Eerste slide',
                        ],
                        [
                            'runtime_id' => 'cms-carousel-item-two',
                            'is_first' => false,
                            'title' => 'Slide 2',
                            'text' => '<b>Tweede slide</b>',
                        ],
                    ],
                    'previous_label' => 'Vorige',
                    'next_label' => 'Volgende',
                ],
                [
                    'type' => 'faq',
                    'runtime_id' => 'cms-faq-test',
                    'items' => [
                        [
                            'runtime_id' => 'cms-faq-item-one',
                            'is_first' => true,
                            'question' => 'Vraag?',
                            'answer' => '<script>alert(1)</script>',
                        ],
                    ],
                ],
                [
                    'type' => 'steps',
                    'runtime_id' => 'cms-steps-test',
                    'items' => [
                        [
                            'runtime_id' => 'cms-steps-item-one',
                            'is_first' => true,
                            'title' => 'Stap 1',
                            'text' => '<img src=x onerror=alert(1)>',
                        ],
                    ],
                ],
                [
                    'type' => 'icon_list',
                    'runtime_id' => 'cms-icon-list-test',
                    'items' => [
                        [
                            'runtime_id' => 'cms-icon-list-item-one',
                            'is_first' => true,
                            'title' => 'Punt',
                            'text' => '<b>Tekst</b>',
                        ],
                    ],
                ],
            ],
            'contentItem' => [],
            'site' => ['current_locale' => 'nl'],
        ]);

        $view->assertSee('data-cms-behavior="accordion"', false);
        $view->assertSee('data-cms-accordion-trigger', false);
        $view->assertSee('aria-controls="accordion-panel-cms-accordion-item-test"', false);
        $view->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

        $view->assertSee('data-cms-behavior="tabs"', false);
        $view->assertSee('role="tablist"', false);
        $view->assertSee('tabs-panel-cms-tabs-item-two', false);
        $view->assertSee('aria-selected="true"', false);
        $view->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);

        $view->assertSee('data-cms-behavior="carousel"', false);
        $view->assertSee('data-cms-carousel-slide', false);
        $view->assertSee('data-cms-carousel-prev', false);
        $view->assertSee('data-cms-carousel-next', false);
        $view->assertSee('cms-carousel-item-two', false);
        $view->assertSee('&lt;b&gt;Tweede slide&lt;/b&gt;', false);

        $view->assertSee('data-cms-behavior="accordion"', false);
        $view->assertSee('faq-panel-cms-faq-item-one', false);
        $view->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

        $view->assertSee('rw-public-block--steps', false);
        $view->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);

        $view->assertSee('rw-public-block--icon-list', false);
        $view->assertSee('&lt;b&gt;Tekst&lt;/b&gt;', false);
        $view->assertDontSee('<script>alert(1)</script>', false);
        $view->assertDontSee('<img src=x onerror=alert(1)>', false);
        $view->assertDontSee('<b>Tweede slide</b>', false);
        $view->assertDontSee('<b>Tekst</b>', false);
    }

    public function test_generic_public_payloads_get_safe_runtime_ids_for_behavior_templates(): void
    {
        $payloads = app(CmsBlockPayloadBuilder::class)->handle([
            ['type' => 'accordion', 'items' => [
                ['title' => 'Vraag', 'text' => 'Antwoord'],
                ['title' => '', 'text' => ''],
            ]],
            ['type' => 'tabs', 'items' => [
                ['title' => 'Tab', 'text' => 'Inhoud'],
            ]],
            ['type' => 'carousel', 'items' => [
                ['title' => 'Slide', 'text' => 'Inhoud'],
            ]],
            ['type' => 'faq', 'items' => [
                ['question' => 'Vraag', 'answer' => 'Antwoord', 'title' => 'wordt niet meegenomen'],
            ]],
        ]);

        $this->assertSame('cms-accordion-1', $payloads[0]['runtime_id']);
        $this->assertSame('cms-accordion-items-item-2', $payloads[0]['items'][0]['runtime_id']);
        $this->assertTrue($payloads[0]['items'][0]['is_first']);
        $this->assertCount(1, $payloads[0]['items']);
        $this->assertSame('cms-tabs-3', $payloads[1]['runtime_id']);
        $this->assertSame('cms-tabs-items-item-4', $payloads[1]['items'][0]['runtime_id']);
        $this->assertTrue($payloads[1]['items'][0]['is_first']);
        $this->assertSame('cms-carousel-5', $payloads[2]['runtime_id']);
        $this->assertSame('cms-carousel-items-item-6', $payloads[2]['items'][0]['runtime_id']);
        $this->assertTrue($payloads[2]['items'][0]['is_first']);
        $this->assertSame('cms-faq-7', $payloads[3]['runtime_id']);
        $this->assertSame('cms-faq-items-item-8', $payloads[3]['items'][0]['runtime_id']);
        $this->assertSame('Vraag', $payloads[3]['items'][0]['question']);
        $this->assertSame('Antwoord', $payloads[3]['items'][0]['answer']);
        $this->assertArrayNotHasKey('title', $payloads[3]['items'][0]);
    }

    private function configureTextBlockRuntimeMetadata(): void
    {
        $types = config('cms_blocks.types');
        $types['text']['custom_class'] = 'safe-runtime-class invalid;class';
        $types['text']['css_variables'] = [
            '--rw-runtime-color' => 'red',
            '--rw-invalid' => 'red; color: blue',
        ];
        $types['text']['behavior_key'] = 'accordion';
        $types['text']['behavior_options'] = ['speed' => 300];

        config(['cms_blocks.types' => $types]);
    }

    private function configureTextBlockSafeBladeTemplate(): void
    {
        $types = config('cms_blocks.types');
        $types['text']['safe_blade_template'] = '<article class="safe-blade-text"><h2>{{ block.title }}</h2><p>{{ block.text }}</p><p>{{ block.unknown }}</p></article>';

        config(['cms_blocks.types' => $types]);
    }
}
