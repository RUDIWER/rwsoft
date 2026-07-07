<?php

namespace Tests\Unit;

use App\Support\Cms\CmsBlockRegistry;
use App\Support\PublicSite\PublicViewResolver;
use Tests\TestCase;

class CmsBlockRegistryTest extends TestCase
{
    public function test_registry_exposes_expected_block_types_and_views(): void
    {
        $registry = app(CmsBlockRegistry::class);

        $this->assertContains('text', $registry->typeKeys());
        $this->assertContains('feature_card', $registry->typeKeys());
        $this->assertContains('testimonial', $registry->typeKeys());
        $this->assertContains('stats', $registry->typeKeys());
        $this->assertContains('accordion', $registry->typeKeys());
        $this->assertContains('tabs', $registry->typeKeys());
        $this->assertContains('carousel', $registry->typeKeys());
        $this->assertContains('faq', $registry->typeKeys());
        $this->assertContains('steps', $registry->typeKeys());
        $this->assertContains('icon_list', $registry->typeKeys());
        $this->assertContains('video', $registry->typeKeys());
        $this->assertContains('logo_strip', $registry->typeKeys());
        $this->assertContains('custom_head_code', $registry->typeKeys());
        $this->assertContains('site_head_meta', $registry->typeKeys());
        $this->assertContains('site_head_favicons', $registry->typeKeys());
        $this->assertContains('site_head_system_assets', $registry->typeKeys());
        $this->assertContains('site_head_theme', $registry->typeKeys());
        $this->assertContains('text', $registry->contentTypeKeys());
        $this->assertContains('feature_card', $registry->contentTypeKeys());
        $this->assertContains('testimonial', $registry->contentTypeKeys());
        $this->assertContains('stats', $registry->contentTypeKeys());
        $this->assertContains('accordion', $registry->contentTypeKeys());
        $this->assertContains('tabs', $registry->contentTypeKeys());
        $this->assertContains('carousel', $registry->contentTypeKeys());
        $this->assertContains('faq', $registry->contentTypeKeys());
        $this->assertContains('steps', $registry->contentTypeKeys());
        $this->assertContains('icon_list', $registry->contentTypeKeys());
        $this->assertContains('video', $registry->contentTypeKeys());
        $this->assertContains('logo_strip', $registry->contentTypeKeys());
        $this->assertNotContains('custom_head_code', $registry->contentTypeKeys());
        $this->assertSame('public.system.blocks.text', $registry->viewFor('text'));
        $this->assertSame('public.system.blocks.feature-card', $registry->viewFor('feature_card'));
        $this->assertSame('public.system.blocks.testimonial', $registry->viewFor('testimonial'));
        $this->assertSame('public.system.blocks.stats', $registry->viewFor('stats'));
        $this->assertSame('public.system.blocks.text', $registry->viewFor('accordion'));
        $this->assertSame('public.system.blocks.text', $registry->viewFor('tabs'));
        $this->assertSame('public.system.blocks.text', $registry->viewFor('carousel'));
        $this->assertSame('public.system.blocks.text', $registry->viewFor('faq'));
        $this->assertSame('public.system.blocks.text', $registry->viewFor('steps'));
        $this->assertSame('public.system.blocks.text', $registry->viewFor('icon_list'));
        $this->assertSame('public.system.blocks.video', $registry->viewFor('video'));
        $this->assertSame('public.system.blocks.logo-strip', $registry->viewFor('logo_strip'));
        $this->assertSame('public.system.blocks.content-list', $registry->viewFor('list_grid'));
        $this->assertSame('public.system.blocks.head-meta', $registry->viewFor('site_head_meta'));
        $this->assertSame('public.system.blocks.head-favicons', $registry->viewFor('site_head_favicons'));
        $this->assertSame('public.system.blocks.head-system-assets', $registry->viewFor('site_head_system_assets'));
        $this->assertSame('public.system.blocks.head-theme', $registry->viewFor('site_head_theme'));
        $this->assertSame('public.system.blocks.text', $registry->viewFor('unknown_type'));
    }

    public function test_registry_filters_block_types_by_zone(): void
    {
        $registry = app(CmsBlockRegistry::class);

        $this->assertContains('text', $registry->allowedForZone('content'));
        $this->assertContains('site_header', $registry->allowedForZone('header'));
        $this->assertContains('site_head_meta', $registry->allowedForZone('head'));
        $this->assertContains('site_head_favicons', $registry->allowedForZone('head'));
        $this->assertContains('site_head_system_assets', $registry->allowedForZone('head'));
        $this->assertContains('site_head_theme', $registry->allowedForZone('head'));
        $this->assertContains('custom_head_code', $registry->allowedForZone('head'));
        $this->assertContains('custom_body_end_code', $registry->allowedForZone('body_end'));
        $this->assertNotContains('custom_head_code', $registry->allowedForZone('content'));
        $this->assertFalse($registry->isAllowedForZone('site_footer', 'header'));
        $this->assertTrue($registry->isAllowedForZone('site_footer', 'footer'));
    }

    public function test_registry_exposes_fields_and_defaults(): void
    {
        $registry = app(CmsBlockRegistry::class);

        $this->assertSame(['title', 'text'], $registry->fieldsFor('text'));
        $this->assertSame(['title', 'text'], $registry->fieldsFor('feature_card'));
        $this->assertSame(['text', 'source'], $registry->fieldsFor('testimonial'));
        $this->assertSame(['value', 'suffix', 'label'], $registry->fieldsFor('stats'));
        $this->assertSame(['items'], $registry->fieldsFor('accordion'));
        $this->assertSame(['items'], $registry->fieldsFor('tabs'));
        $this->assertSame(['items', 'previous_label', 'next_label'], $registry->fieldsFor('carousel'));
        $this->assertSame(['items'], $registry->fieldsFor('faq'));
        $this->assertSame(['items'], $registry->fieldsFor('steps'));
        $this->assertSame(['items'], $registry->fieldsFor('icon_list'));
        $this->assertSame(['title', 'video_url'], $registry->fieldsFor('video'));
        $this->assertSame(['title', 'media_asset_ids'], $registry->fieldsFor('logo_strip'));
        $this->assertSame(['title' => null, 'text' => null], $registry->defaultsFor('text'));
        $this->assertSame(['title' => null, 'text' => null], $registry->defaultsFor('feature_card'));
        $this->assertSame(['text' => null, 'source' => null], $registry->defaultsFor('testimonial'));
        $this->assertSame(['value' => null, 'suffix' => null, 'label' => null], $registry->defaultsFor('stats'));
        $this->assertSame(['items' => []], $registry->defaultsFor('accordion'));
        $this->assertSame(['items' => []], $registry->defaultsFor('tabs'));
        $this->assertSame(['items' => [], 'previous_label' => null, 'next_label' => null], $registry->defaultsFor('carousel'));
        $this->assertSame(['items' => []], $registry->defaultsFor('faq'));
        $this->assertSame(['items' => []], $registry->defaultsFor('steps'));
        $this->assertSame(['items' => []], $registry->defaultsFor('icon_list'));
        $this->assertSame(['title' => null, 'video_url' => null], $registry->defaultsFor('video'));
        $this->assertSame(['title' => null, 'media_asset_ids' => []], $registry->defaultsFor('logo_strip'));
        $this->assertSame(24, $registry->defaultsFor('list_rows')['limit']);
        $this->assertTrue($registry->defaultsFor('breadcrumb')['show_current']);
    }

    public function test_registry_normalizes_repeater_items_from_editor_definition(): void
    {
        $registry = app(CmsBlockRegistry::class);

        $this->assertSame(['title', 'text'], $registry->repeaterFieldNamesFor('accordion', 'items'));
        $this->assertSame(['question', 'answer'], $registry->repeaterFieldNamesFor('faq', 'items'));
        $this->assertSame([], $registry->repeaterFieldNamesFor('text', 'items'));

        $this->assertSame([
            ['title' => 'Vraag', 'text' => 'Antwoord'],
            ['title' => null, 'text' => 'Alleen tekst'],
        ], $registry->normalizeRepeaterItems('accordion', 'items', [
            ['title' => 'Vraag', 'text' => 'Antwoord', 'unknown' => 'wordt niet opgeslagen'],
            ['title' => '', 'text' => ''],
            ['text' => 'Alleen tekst'],
            'invalid',
        ]));

        $this->assertSame([
            ['question' => 'Vraag', 'answer' => 'Antwoord'],
        ], $registry->normalizeRepeaterItems('faq', 'items', [
            ['question' => 'Vraag', 'answer' => 'Antwoord', 'title' => 'wordt niet opgeslagen'],
        ]));
    }

    public function test_registry_exposes_editor_definitions(): void
    {
        $registry = app(CmsBlockRegistry::class);

        $definitions = collect($registry->editorDefinitions())->keyBy('type');

        $this->assertTrue($definitions->has('text'));
        $this->assertSame('components.block_editor.text', $definitions['text']['label_key']);
        $this->assertSame('content', $definitions['text']['category']);
        $this->assertSame(['content', 'header', 'footer'], $definitions['text']['zones']);
        $this->assertSame(['title', 'text'], $definitions['text']['fields']);
        $this->assertSame('title', $definitions['text']['editor_fields'][0]['name']);
        $this->assertSame('textarea', $definitions['text']['editor_fields'][1]['type']);
        $this->assertSame(['title_field' => 'title'], $definitions['text']['preview']);

        $this->assertSame('components.block_editor.feature_card', $definitions['feature_card']['label_key']);
        $this->assertSame('content', $definitions['feature_card']['category']);
        $this->assertSame(['content', 'header', 'footer'], $definitions['feature_card']['zones']);
        $this->assertSame(['title', 'text'], $definitions['feature_card']['fields']);

        $this->assertSame('components.block_editor.testimonial', $definitions['testimonial']['label_key']);
        $this->assertSame('content', $definitions['testimonial']['category']);
        $this->assertSame(['content', 'header', 'footer'], $definitions['testimonial']['zones']);
        $this->assertSame(['text', 'source'], $definitions['testimonial']['fields']);
        $this->assertSame('source', $definitions['testimonial']['editor_fields'][1]['name']);

        $this->assertSame('components.block_editor.stats', $definitions['stats']['label_key']);
        $this->assertSame('content', $definitions['stats']['category']);
        $this->assertSame(['content', 'header', 'footer'], $definitions['stats']['zones']);
        $this->assertSame(['value', 'suffix', 'label'], $definitions['stats']['fields']);
        $this->assertSame('value', $definitions['stats']['editor_fields'][0]['name']);
        $this->assertSame('text', $definitions['stats']['editor_fields'][0]['type']);

        $this->assertSame('components.block_editor.accordion', $definitions['accordion']['label_key']);
        $this->assertSame('content', $definitions['accordion']['category']);
        $this->assertSame(['content', 'header', 'footer'], $definitions['accordion']['zones']);
        $this->assertSame(['items'], $definitions['accordion']['fields']);
        $this->assertSame('repeater', $definitions['accordion']['editor_fields'][0]['type']);
        $this->assertSame('title', $definitions['accordion']['editor_fields'][0]['fields'][0]['name']);

        $this->assertSame('components.block_editor.tabs', $definitions['tabs']['label_key']);
        $this->assertSame(['items'], $definitions['tabs']['fields']);
        $this->assertSame('repeater', $definitions['tabs']['editor_fields'][0]['type']);
        $this->assertSame('title', $definitions['tabs']['editor_fields'][0]['fields'][0]['name']);

        $this->assertSame('components.block_editor.carousel', $definitions['carousel']['label_key']);
        $this->assertSame(['items', 'previous_label', 'next_label'], $definitions['carousel']['fields']);
        $this->assertSame('repeater', $definitions['carousel']['editor_fields'][0]['type']);
        $this->assertSame('previous_label', $definitions['carousel']['editor_fields'][1]['name']);

        $this->assertSame('components.block_editor.faq', $definitions['faq']['label_key']);
        $this->assertSame(['items'], $definitions['faq']['fields']);
        $this->assertSame('repeater', $definitions['faq']['editor_fields'][0]['type']);
        $this->assertSame('question', $definitions['faq']['editor_fields'][0]['fields'][0]['name']);

        $this->assertSame('components.block_editor.steps', $definitions['steps']['label_key']);
        $this->assertSame(['items'], $definitions['steps']['fields']);

        $this->assertSame('components.block_editor.icon_list', $definitions['icon_list']['label_key']);
        $this->assertSame(['items'], $definitions['icon_list']['fields']);

        $this->assertSame('components.block_editor.video', $definitions['video']['label_key']);
        $this->assertSame('content', $definitions['video']['category']);
        $this->assertSame(['content', 'header', 'footer'], $definitions['video']['zones']);
        $this->assertSame(['title', 'video_url'], $definitions['video']['fields']);
        $this->assertSame('video_url', $definitions['video']['editor_fields'][1]['name']);

        $this->assertSame('components.block_editor.logo_strip', $definitions['logo_strip']['label_key']);
        $this->assertSame('content', $definitions['logo_strip']['category']);
        $this->assertSame(['content', 'header', 'footer'], $definitions['logo_strip']['zones']);
        $this->assertSame(['title', 'media_asset_ids'], $definitions['logo_strip']['fields']);
        $this->assertSame('media_list', $definitions['logo_strip']['editor_fields'][1]['type']);

        $this->assertSame('code', $definitions['custom_head_code']['category']);
        $this->assertSame(['head'], $definitions['custom_head_code']['zones']);
        $this->assertSame(['code'], $definitions['custom_head_code']['fields']);
        $this->assertSame('code', $definitions['custom_head_code']['editor_fields'][0]['type']);
        $this->assertSame('system', $definitions['site_head_meta']['category']);
        $this->assertSame(['head'], $definitions['site_head_meta']['zones']);
        $this->assertSame([], $definitions['site_head_meta']['fields']);
    }

    public function test_registry_exposes_phase_one_contracts(): void
    {
        $registry = app(CmsBlockRegistry::class);

        $this->assertSame(['content'], $registry->contentZones());
        $this->assertSame(['head', 'header', 'footer', 'body_end'], $registry->layoutZones());
        $this->assertContains('textarea', $registry->editorFieldTypes());
        $this->assertContains('media_list', $registry->editorFieldTypes());

        $safeBlade = $registry->safeBladeContract();

        $this->assertTrue($safeBlade['enabled']);
        $this->assertTrue($safeBlade['definition_level_only']);
        $this->assertSame('safe_blade_template', $safeBlade['template_definition_field']);
        $this->assertSame('public.system.blocks.safe-blade', $safeBlade['runtime_view']);
        $this->assertTrue($safeBlade['escape_output']);
        $this->assertContains('blade_engine', $safeBlade['forbidden']);
        $this->assertContains('raw_output', $safeBlade['forbidden']);

        $this->assertTrue($registry->cssContract()['definition_level_first']);
        $this->assertFalse($registry->behaviorContract()['allow_free_javascript']);
        $this->assertSame('draft_first', $registry->packageContract()['import_mode']);
    }

    public function test_registry_builds_content_block_rules_without_code_field(): void
    {
        $registry = app(CmsBlockRegistry::class);
        $rules = $registry->contentBlockRules('content_blocks');

        $this->assertArrayHasKey('content_blocks', $rules);
        $this->assertArrayHasKey('content_blocks.*.type', $rules);
        $this->assertArrayHasKey('content_blocks.*.text', $rules);
        $this->assertArrayHasKey('content_blocks.*.second_title', $rules);
        $this->assertArrayHasKey('content_blocks.*.second_text', $rules);
        $this->assertArrayHasKey('content_blocks.*.previous_label', $rules);
        $this->assertArrayHasKey('content_blocks.*.next_label', $rules);
        $this->assertArrayHasKey('content_blocks.*.items', $rules);
        $this->assertArrayHasKey('content_blocks.*.items.*', $rules);
        $this->assertArrayHasKey('content_blocks.*.items.*.title', $rules);
        $this->assertArrayHasKey('content_blocks.*.items.*.text', $rules);
        $this->assertArrayHasKey('content_blocks.*.media_asset_ids.*', $rules);
        $this->assertArrayNotHasKey('content_blocks.*.code', $rules);
    }

    public function test_registry_only_allows_code_field_for_code_block_rules(): void
    {
        $registry = app(CmsBlockRegistry::class);

        $contentRules = $registry->blockRules(
            'sections.content.*.placements.*.block',
            $registry->contentTypeKeys()
        );
        $layoutRules = $registry->blockRules(
            'sections.*.*.placements.*.block',
            $registry->typeKeys()
        );

        $this->assertArrayNotHasKey('sections.content.*.placements.*.block.code', $contentRules);
        $this->assertArrayHasKey('sections.*.*.placements.*.block.code', $layoutRules);
    }

    public function test_registry_validates_current_block_definitions(): void
    {
        $registry = app(CmsBlockRegistry::class);

        $this->assertSame([], $registry->validateDefinitions());
    }

    public function test_registry_injects_css_source_from_block_style_config(): void
    {
        $types = config('cms_blocks.types');
        $types['style_probe'] = $types['text'];

        unset($types['style_probe']['css_source']);

        config([
            'cms_blocks.types' => $types,
            'cms_block_styles.style_probe' => '.rw-public-style-probe { color: red; }',
        ]);

        $registry = app(CmsBlockRegistry::class);

        $this->assertSame('.rw-public-style-probe { color: red; }', $registry->definition('style_probe')['css_source']);
        $this->assertSame('.rw-public-style-probe { color: red; }', $registry->cssSourceFor('style_probe'));
    }

    public function test_registry_reports_invalid_block_definitions(): void
    {
        $registry = app(CmsBlockRegistry::class);

        $errors = $registry->validateDefinitions([
            'Invalid-Type' => [
                'label_key' => '',
                'category' => 'content',
                'zones' => ['unknown'],
                'view' => '',
                'fields' => ['title'],
                'defaults' => [],
                'preview' => ['title_field' => 'missing'],
                'editor' => [
                    'fields' => [
                        ['name' => 'missing', 'type' => 'unknown'],
                    ],
                ],
                'behavior_key' => 'free_script',
                'behavior_options' => 'invalid',
                'css_variables' => 'invalid',
                'safe_blade_template' => '{!! block.title !!}',
                'items' => 'invalid',
            ],
            'broken_repeater' => [
                'label_key' => 'components.block_editor.text',
                'category' => 'content',
                'zones' => ['content'],
                'view' => 'public.system.blocks.text',
                'fields' => ['items'],
                'defaults' => ['items' => []],
                'preview' => ['title_field' => null],
                'editor' => [
                    'fields' => [
                        ['name' => 'items', 'type' => 'repeater', 'fields' => [
                            ['name' => 'Unsafe-Field', 'type' => 'code'],
                        ]],
                    ],
                ],
            ],
            'unsafe_code' => [
                'label_key' => 'components.block_editor.text',
                'category' => 'content',
                'zones' => ['content'],
                'view' => 'public.system.blocks.text',
                'fields' => ['code'],
                'defaults' => ['code' => null],
                'editor' => [
                    'fields' => [
                        ['name' => 'code', 'type' => 'code'],
                    ],
                ],
                'safe_blade_template' => '{{ block.code }}',
            ],
            'code_with_safe_blade' => [
                'label_key' => 'components.block_editor.text',
                'category' => 'code',
                'zones' => ['head'],
                'view' => 'public.system.blocks.custom-head-code',
                'fields' => [],
                'defaults' => [],
                'preview' => ['title_field' => null],
                'safe_blade_template' => '{{ block.title }}',
            ],
        ]);

        $this->assertContains('Block type [Invalid-Type] must use snake_case alphanumeric keys.', $errors);
        $this->assertContains('Block type [Invalid-Type] must define a label_key.', $errors);
        $this->assertContains('Block type [Invalid-Type] uses unknown zone [unknown].', $errors);
        $this->assertContains('Block type [Invalid-Type] field [title] is missing a default value.', $errors);
        $this->assertContains('Block type [Invalid-Type] editor field [0] references an unknown field.', $errors);
        $this->assertContains('Block type [Invalid-Type] editor field [missing] has an unsupported type.', $errors);
        $this->assertContains('Block type [Invalid-Type] preview title_field must reference a configured field.', $errors);
        $this->assertContains('Block type [Invalid-Type] uses an unsupported behavior_key.', $errors);
        $this->assertContains('Block type [Invalid-Type] behavior_options must be an array.', $errors);
        $this->assertContains('Block type [Invalid-Type] css_variables must be an array.', $errors);
        $this->assertContains('Block type [Invalid-Type] SafeBlade template is invalid: SafeBlade raw output is not allowed.', $errors);
        $this->assertContains('Block type [broken_repeater] repeater field [items] nested field [0] references an unsupported field.', $errors);
        $this->assertContains('Block type [broken_repeater] repeater field [items] nested field [0] has an unsupported type.', $errors);
        $this->assertContains('Block type [unsafe_code] can only use code editor fields in the code category.', $errors);
        $this->assertContains('Block type [code_with_safe_blade] code blocks must define requires_permission.', $errors);
        $this->assertContains('Block type [code_with_safe_blade] code blocks cannot define a SafeBlade template.', $errors);
    }

    public function test_registry_resolves_safe_blade_runtime_view_for_definition_templates(): void
    {
        $types = config('cms_blocks.types');
        $types['text']['safe_blade_template'] = '<article>{{ block.title }}</article>';
        config(['cms_blocks.types' => $types]);

        $registry = app(CmsBlockRegistry::class);

        $this->assertTrue($registry->hasSafeBladeTemplate('text'));
        $this->assertSame('<article>{{ block.title }}</article>', $registry->safeBladeTemplateFor('text'));
        $this->assertSame('public.system.blocks.safe-blade', $registry->publicRuntimeViewFor('text'));
        $this->assertSame('public.system.blocks.safe-blade', app(PublicViewResolver::class)->block('text'));
        $this->assertSame('public.system.blocks.video', app(PublicViewResolver::class)->block('video'));
    }

    public function test_registry_resolves_safe_blade_runtime_view_for_simple_public_blocks(): void
    {
        $registry = app(CmsBlockRegistry::class);

        foreach (['text', 'feature_card', 'quote', 'stats', 'accordion', 'tabs', 'carousel'] as $type) {
            $this->assertTrue($registry->hasSafeBladeTemplate($type));
            $this->assertSame('public.system.blocks.safe-blade', $registry->publicRuntimeViewFor($type));
        }

        $this->assertFalse($registry->hasSafeBladeTemplate('video'));
        $this->assertSame('public.system.blocks.video', $registry->publicRuntimeViewFor('video'));
    }

    public function test_registry_sanitizes_runtime_definition_metadata(): void
    {
        $types = config('cms_blocks.types');
        $types['text']['custom_class'] = 'safe-class hover:blue invalid;class';
        $types['text']['css_variables'] = [
            '--rw-test-color' => 'red',
            'color' => 'blue',
            '--rw-bad' => 'red; color: blue',
        ];
        $types['text']['behavior_key'] = 'accordion';
        $types['text']['behavior_options'] = [
            'speed' => 300,
            'open' => true,
            'bad-key' => 'ignored',
            'nested' => ['ignored'],
        ];
        config(['cms_blocks.types' => $types]);

        $metadata = app(CmsBlockRegistry::class)->runtimeMetadataFor('text');

        $this->assertSame('safe-class hover:blue', $metadata['custom_class']);
        $this->assertSame(['--rw-test-color' => 'red'], $metadata['css_variables']);
        $this->assertSame('accordion', $metadata['behavior_key']);
        $this->assertSame(['speed' => 300, 'open' => true], $metadata['behavior_options']);
    }

    public function test_registry_exposes_behavior_metadata_for_behavior_blocks(): void
    {
        $registry = app(CmsBlockRegistry::class);

        $this->assertSame('accordion', $registry->runtimeMetadataFor('accordion')['behavior_key']);
        $this->assertSame('tabs', $registry->runtimeMetadataFor('tabs')['behavior_key']);
        $this->assertSame('carousel', $registry->runtimeMetadataFor('carousel')['behavior_key']);
    }
}
