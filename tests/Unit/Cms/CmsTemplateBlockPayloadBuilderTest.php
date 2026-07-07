<?php

namespace Tests\Unit\Cms;

use App\Support\Cms\CmsBlockFieldContract;
use App\Support\Cms\CmsBlockRegistry;
use App\Support\Cms\CmsHtmlSanitizer;
use App\Support\Cms\CmsTemplateFieldRegistry;
use App\Support\PublicSite\CmsBlockPayloadBuilder;
use App\Support\PublicSite\CmsBreadcrumbBuilder;
use App\Support\PublicSite\CmsContentListBlockResolver;
use App\Support\PublicSite\CmsNavigationBuilder;
use App\Support\PublicSite\PublicMediaUrl;
use App\Support\PublicSite\PublicSafeUrl;
use Mockery;
use Tests\TestCase;

class CmsTemplateBlockPayloadBuilderTest extends TestCase
{
    public function test_dynamic_field_resolves_whitelisted_context_value(): void
    {
        $payload = $this->builder()->handle([
            [
                'type' => 'dynamic_field',
                'field_key' => 'category.title',
                'title' => 'Titel',
                'heading_level' => 'h1',
            ],
        ], templateContext: [
            '__template' => [
                'template_class' => 'category',
                'template_key' => 'category.archive',
            ],
            'category' => [
                'title' => 'Nieuws',
            ],
        ]);

        $this->assertSame('dynamic_field', $payload[0]['renderer_key']);
        $this->assertSame('category.title', $payload[0]['field_key']);
        $this->assertSame('Titel', $payload[0]['title']);
        $this->assertSame('h1', $payload[0]['heading_level']);
        $this->assertSame('Nieuws', $payload[0]['value']);
        $this->assertSame('scalar', $payload[0]['value_type']);
    }

    public function test_page_dynamic_fields_keep_title_and_use_short_description(): void
    {
        $payload = $this->builder()->handle([
            [
                'type' => 'dynamic_field',
                'field_key' => 'page.title',
            ],
            [
                'type' => 'dynamic_field',
                'field_key' => 'page.short_description',
            ],
            [
                'type' => 'dynamic_field',
                'field_key' => 'page.slug',
            ],
            [
                'type' => 'dynamic_field',
                'field_key' => 'page.locale',
            ],
            [
                'type' => 'dynamic_field',
                'field_key' => 'page.url',
            ],
            [
                'type' => 'dynamic_field',
                'field_key' => 'page.seo_title',
            ],
            [
                'type' => 'dynamic_field',
                'field_key' => 'page.seo_description',
            ],
            [
                'type' => 'dynamic_field',
                'field_key' => 'page.published_at',
            ],
            [
                'type' => 'dynamic_field',
                'field_key' => 'page.updated_at',
            ],
        ], templateContext: [
            '__template' => [
                'template_class' => 'page',
                'template_key' => 'page.detail',
            ],
            'page' => [
                'title' => 'Page title',
                'short_description' => 'Page short description',
                'slug' => 'page-slug',
                'locale' => 'nl',
                'url' => 'https://example.test/page-slug',
                'seo_title' => 'Page SEO title',
                'seo_description' => 'Page SEO description',
                'published_at' => '2026-07-01',
                'updated_at' => '2026-07-02',
            ],
        ]);

        $this->assertSame('page.title', $payload[0]['field_key']);
        $this->assertSame('Page title', $payload[0]['value']);
        $this->assertSame('page.short_description', $payload[1]['field_key']);
        $this->assertSame('Page short description', $payload[1]['value']);
        $this->assertSame('page.slug', $payload[2]['field_key']);
        $this->assertSame('page-slug', $payload[2]['value']);
        $this->assertSame('page.locale', $payload[3]['field_key']);
        $this->assertSame('nl', $payload[3]['value']);
        $this->assertSame('page.url', $payload[4]['field_key']);
        $this->assertSame('https://example.test/page-slug', $payload[4]['value']);
        $this->assertSame('page.seo_title', $payload[5]['field_key']);
        $this->assertSame('Page SEO title', $payload[5]['value']);
        $this->assertSame('page.seo_description', $payload[6]['field_key']);
        $this->assertSame('Page SEO description', $payload[6]['value']);
        $this->assertSame('page.published_at', $payload[7]['field_key']);
        $this->assertSame('2026-07-01', $payload[7]['value']);
        $this->assertSame('page.updated_at', $payload[8]['field_key']);
        $this->assertSame('2026-07-02', $payload[8]['value']);
    }

    public function test_dynamic_field_rejects_non_whitelisted_context_value(): void
    {
        $payload = $this->builder()->handle([
            [
                'type' => 'dynamic_field',
                'field_key' => 'category.secret_value',
                'title' => 'Secret',
            ],
        ], templateContext: [
            '__template' => [
                'template_class' => 'category',
                'template_key' => 'category.archive',
            ],
            'category' => [
                'secret_value' => 'mag niet tonen',
            ],
        ]);

        $this->assertSame('dynamic_field', $payload[0]['renderer_key']);
        $this->assertNull($payload[0]['field_key']);
        $this->assertNull($payload[0]['value']);
        $this->assertSame('empty', $payload[0]['value_type']);
    }

    public function test_dynamic_field_resolves_template_contract_value(): void
    {
        $payload = $this->builder()->handle([
            [
                'type' => 'dynamic_field',
                'field_key' => 'template.hero.title',
                'title' => 'Hero',
            ],
        ], templateContext: [
            '__template' => [
                'template_class' => 'page',
                'template_key' => 'page.detail',
                'data_contract' => [
                    'system_fields' => [
                        ['key' => 'page.title', 'enabled' => false],
                    ],
                    'template_fields' => [
                        ['key' => 'hero.title', 'type' => 'text'],
                    ],
                ],
            ],
            'template' => [
                'hero' => [
                    'title' => 'Welkom',
                ],
            ],
            'page' => [
                'title' => 'Verborgen',
            ],
        ]);

        $this->assertSame('template.hero.title', $payload[0]['field_key']);
        $this->assertSame('Welkom', $payload[0]['value']);
        $this->assertSame('scalar', $payload[0]['value_type']);
    }

    public function test_dynamic_field_rejects_template_value_missing_from_contract(): void
    {
        $payload = $this->builder()->handle([
            [
                'type' => 'dynamic_field',
                'field_key' => 'template.secret',
                'title' => 'Secret',
            ],
        ], templateContext: [
            '__template' => [
                'template_class' => 'page',
                'template_key' => 'page.detail',
                'data_contract' => [
                    'system_fields' => [],
                    'template_fields' => [
                        ['key' => 'hero.title', 'type' => 'text'],
                    ],
                ],
            ],
            'template' => [
                'secret' => 'Niet tonen',
            ],
        ]);

        $this->assertNull($payload[0]['field_key']);
        $this->assertNull($payload[0]['value']);
        $this->assertSame('empty', $payload[0]['value_type']);
    }

    public function test_template_block_data_overlays_matching_content_key_fields(): void
    {
        $payload = $this->builder()->handle([
            [
                'type' => 'feature_card',
                'content_key' => 'hero_main',
                'title' => 'Definition title',
                'text' => 'Definition text',
                'placeable_block' => [
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
                ],
            ],
        ], templateBlockData: [
            'hero_main' => [
                'title' => 'Page title',
                'text' => 'Page text',
                'renderer_key' => 'forbidden',
            ],
            'orphan' => [
                'title' => 'Ignored',
            ],
        ]);

        $this->assertSame('feature_card', $payload[0]['renderer_key']);
        $this->assertSame('Page title', $payload[0]['title']);
        $this->assertSame('Page text', $payload[0]['text']);
    }

    public function test_dynamic_field_rejects_disabled_system_field_from_contract(): void
    {
        $payload = $this->builder()->handle([
            [
                'type' => 'dynamic_field',
                'field_key' => 'page.title',
            ],
        ], templateContext: [
            '__template' => [
                'template_class' => 'page',
                'template_key' => 'page.detail',
                'data_contract' => [
                    'system_fields' => [
                        ['key' => 'page.title', 'enabled' => false],
                    ],
                    'template_fields' => [],
                ],
            ],
            'page' => [
                'title' => 'Niet tonen',
            ],
        ]);

        $this->assertNull($payload[0]['field_key']);
        $this->assertNull($payload[0]['value']);
        $this->assertSame('empty', $payload[0]['value_type']);
    }

    public function test_content_slot_returns_only_requested_slot_blocks(): void
    {
        $payload = $this->builder()->handle([
            [
                'type' => 'content_slot',
                'slot_key' => 'content',
                'title' => 'Inhoud',
            ],
        ], contentSlots: [
            'content' => [
                ['type' => 'text', 'title' => 'Body', 'text' => 'Tekst'],
            ],
            'after_list' => [
                ['type' => 'text', 'title' => 'Footer', 'text' => 'Andere tekst'],
            ],
        ]);

        $this->assertSame('content_slot', $payload[0]['renderer_key']);
        $this->assertSame('content', $payload[0]['slot_key']);
        $this->assertSame('Inhoud', $payload[0]['title']);
        $this->assertSame([
            ['type' => 'text', 'title' => 'Body', 'text' => 'Tekst'],
        ], $payload[0]['blocks']);
        $this->assertSame([], $payload[0]['sections']);
    }

    private function builder(): CmsBlockPayloadBuilder
    {
        return new CmsBlockPayloadBuilder(
            app(PublicMediaUrl::class),
            new PublicSafeUrl,
            Mockery::mock(CmsContentListBlockResolver::class),
            Mockery::mock(CmsBreadcrumbBuilder::class),
            new CmsBlockFieldContract(new CmsBlockRegistry, new CmsHtmlSanitizer),
            new CmsBlockRegistry,
            new CmsTemplateFieldRegistry,
            Mockery::mock(CmsNavigationBuilder::class),
        );
    }
}
