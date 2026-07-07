<?php

namespace Tests\Unit\Cms;

use App\Models\Cms\CmsTemplate;
use App\Support\Cms\CmsTemplateDataContract;
use Illuminate\Validation\Rules\Exists;
use Tests\TestCase;

class CmsTemplateDataContractTest extends TestCase
{
    public function test_normalizes_contract_and_builds_template_field_options(): void
    {
        $template = new CmsTemplate([
            'template_key' => 'page.detail',
            'locale' => 'nl',
            'data_contract' => [
                'system_fields' => [
                    ['key' => 'page.title', 'enabled' => true],
                    ['key' => 'page.short_description', 'enabled' => true],
                    ['key' => 'page.slug', 'enabled' => true],
                    ['key' => 'page.locale', 'enabled' => true],
                    ['key' => 'page.url', 'enabled' => true],
                    ['key' => 'page.seo_title', 'enabled' => true],
                    ['key' => 'page.seo_description', 'enabled' => true],
                    ['key' => 'page.published_at', 'enabled' => true],
                    ['key' => 'page.updated_at', 'enabled' => true],
                    ['key' => 'page.excerpt', 'enabled' => true],
                    ['key' => 'page.content', 'enabled' => true],
                    ['key' => 'forbidden.field', 'enabled' => true],
                ],
                'template_fields' => [
                    [
                        'key' => ' Hero Title ',
                        'type' => 'text',
                        'translations' => [
                            'en' => ['label' => 'Hero title'],
                            'nl' => ['label' => 'Heldentitel'],
                        ],
                    ],
                    ['key' => 'hero_title', 'type' => 'textarea'],
                ],
            ],
        ]);

        $contract = app(CmsTemplateDataContract::class)->normalize($template->data_contract, 'page.detail');
        $options = app(CmsTemplateDataContract::class)->fieldOptions($template, 'nl');

        $this->assertSame([
            ['key' => 'page.title', 'enabled' => true],
            ['key' => 'page.short_description', 'enabled' => true],
            ['key' => 'page.slug', 'enabled' => true],
            ['key' => 'page.locale', 'enabled' => true],
            ['key' => 'page.url', 'enabled' => true],
            ['key' => 'page.seo_title', 'enabled' => true],
            ['key' => 'page.seo_description', 'enabled' => true],
            ['key' => 'page.published_at', 'enabled' => true],
            ['key' => 'page.updated_at', 'enabled' => true],
        ], $contract['system_fields']);
        $this->assertSame('Hero_Title', $contract['template_fields'][0]['key']);
        $this->assertSame('template.Hero_Title', $options[9]['value']);
        $this->assertStringContainsString('Heldentitel', $options[9]['label']);
    }

    public function test_cleans_template_data_to_current_contract_fields(): void
    {
        $template = new CmsTemplate([
            'template_key' => 'page.detail',
            'data_contract' => [
                'template_fields' => [
                    ['key' => 'title', 'type' => 'text'],
                    ['key' => 'enabled', 'type' => 'boolean'],
                    ['key' => 'image', 'type' => 'media'],
                ],
            ],
        ]);

        $clean = app(CmsTemplateDataContract::class)->cleanTemplateData([
            'title' => 'Landing page',
            'enabled' => '1',
            'image' => '42',
            'removed' => 'Old value',
        ], $template);

        $this->assertSame([
            'title' => 'Landing page',
            'enabled' => true,
            'image' => 42,
        ], $clean);
    }

    public function test_media_template_fields_require_existing_active_media(): void
    {
        $template = new CmsTemplate([
            'template_key' => 'page.detail',
            'data_contract' => [
                'template_fields' => [
                    ['key' => 'hero.image', 'type' => 'media', 'required' => true],
                ],
            ],
        ]);

        $rules = app(CmsTemplateDataContract::class)->validationRules($template);

        $this->assertContains('required', $rules['template_data.hero.image']);
        $this->assertContains('integer', $rules['template_data.hero.image']);
        $this->assertTrue(
            collect($rules['template_data.hero.image'])->contains(fn (mixed $rule): bool => $rule instanceof Exists)
        );
    }
}
