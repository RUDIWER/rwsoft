<?php

namespace Tests\Unit\Cms;

use App\Support\Cms\CmsBlockFieldContract;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use Tests\TestCase;

class CmsBlockFieldContractTest extends TestCase
{
    public function test_normalizes_block_fields_from_schema_and_defaults(): void
    {
        $fields = app(CmsBlockFieldContract::class)->fieldsForBlock('feature_card', [
            'fields' => ['Title', 'body_text', 'image'],
            'editor_fields' => [
                ['name' => 'Title', 'type' => 'text', 'required' => true, 'label_key' => 'components.block_editor.title'],
                ['name' => 'body_text', 'type' => 'textarea'],
                ['name' => 'image', 'type' => 'media_select'],
            ],
        ], [
            'Title' => 'Default title',
            'body_text' => 'Default text',
        ]);

        $this->assertSame('title', $fields[0]['key']);
        $this->assertSame('text', $fields[0]['type']);
        $this->assertTrue($fields[0]['required']);
        $this->assertSame('Default title', $fields[0]['default']);
        $this->assertSame('textarea', $fields[1]['type']);
        $this->assertSame('media_select', $fields[2]['type']);
    }

    public function test_builds_rules_for_select_media_and_repeater_fields(): void
    {
        $fields = app(CmsBlockFieldContract::class)->fieldsForBlock('feature_card', [
            'fields' => ['variant', 'image', 'logos', 'items'],
            'editor_fields' => [
                ['name' => 'variant', 'type' => 'select', 'options' => [
                    ['value' => 'primary', 'label' => 'Primary'],
                    ['value' => 'secondary', 'label' => 'Secondary'],
                ]],
                ['name' => 'image', 'type' => 'media_select', 'required' => true],
                ['name' => 'logos', 'type' => 'media_list'],
                ['name' => 'items', 'type' => 'repeater', 'fields' => [
                    ['name' => 'title', 'type' => 'text', 'required' => true],
                ]],
            ],
        ], []);

        $rules = app(CmsBlockFieldContract::class)->validationRules($fields, 'template_data.blocks.hero');

        $this->assertContains('required', $rules['template_data.blocks.hero.image']);
        $this->assertTrue(collect($rules['template_data.blocks.hero.image'])->contains(fn (mixed $rule): bool => $rule instanceof Exists));
        $this->assertTrue(collect($rules['template_data.blocks.hero.variant'])->contains(fn (mixed $rule): bool => $rule instanceof In));
        $this->assertSame(['nullable', 'array'], $rules['template_data.blocks.hero.logos']);
        $this->assertTrue(collect($rules['template_data.blocks.hero.logos.*'])->contains(fn (mixed $rule): bool => $rule instanceof Exists));
        $this->assertContains('required', $rules['template_data.blocks.hero.items.*.title']);
    }

    public function test_cleans_block_data_to_known_fields_and_supported_types(): void
    {
        $fields = app(CmsBlockFieldContract::class)->fieldsForBlock('feature_card', [
            'fields' => ['title', 'enabled', 'variant', 'image', 'logos', 'items'],
            'editor_fields' => [
                ['name' => 'title', 'type' => 'text'],
                ['name' => 'enabled', 'type' => 'checkbox'],
                ['name' => 'variant', 'type' => 'select', 'options' => ['primary', 'secondary']],
                ['name' => 'image', 'type' => 'media_select'],
                ['name' => 'logos', 'type' => 'media_list'],
                ['name' => 'items', 'type' => 'repeater', 'fields' => [
                    ['name' => 'title', 'type' => 'text'],
                    ['name' => 'text', 'type' => 'textarea'],
                ]],
            ],
        ], [
            'enabled' => false,
        ]);

        $clean = app(CmsBlockFieldContract::class)->cleanData([
            'title' => ' Hero ',
            'enabled' => '1',
            'variant' => 'forbidden',
            'image' => '12',
            'logos' => ['12', '0', 13, 12],
            'items' => [
                ['title' => 'First', 'text' => 'Body', 'unknown' => 'drop'],
                ['title' => '', 'text' => ''],
            ],
            'unknown' => 'drop',
        ], $fields);

        $this->assertSame([
            'title' => 'Hero',
            'enabled' => true,
            'image' => 12,
            'logos' => [12, 13],
            'items' => [
                ['title' => 'First', 'text' => 'Body'],
            ],
        ], $clean);
    }

    public function test_cleans_rich_text_and_markdown_fields(): void
    {
        $fields = app(CmsBlockFieldContract::class)->fieldsForBlock('rich_text', [
            'fields' => ['html', 'markdown'],
            'editor_fields' => [
                ['name' => 'html', 'type' => 'rich_text'],
                ['name' => 'markdown', 'type' => 'markdown'],
            ],
        ], []);

        $clean = app(CmsBlockFieldContract::class)->cleanData([
            'html' => '<p onclick="alert(1)">Safe</p><script>alert(1)</script>',
            'markdown' => " # Title\n\n<script>alert(1)</script> ",
        ], $fields);

        $this->assertSame('<p>Safe</p>', $clean['html']);
        $this->assertSame("# Title\n\n<script>alert(1)</script>", $clean['markdown']);
    }

    public function test_preserves_editor_field_translations(): void
    {
        $fields = app(CmsBlockFieldContract::class)->fieldsForBlock('feature_card', [
            'fields' => ['title', 'items'],
            'editor_fields' => [
                ['name' => 'title', 'type' => 'text', 'translations' => [
                    'en' => ['label' => 'Title', 'help' => 'Main title'],
                ]],
                ['name' => 'items', 'type' => 'repeater', 'fields' => [
                    ['name' => 'text', 'type' => 'textarea', 'translations' => [
                        'en' => ['label' => 'Text', 'placeholder' => 'Enter text'],
                    ]],
                ]],
            ],
        ], []);

        $this->assertSame('Title', $fields[0]['translations']['en']['label']);
        $this->assertSame('Text', $fields[1]['fields'][0]['translations']['en']['label']);
        $this->assertSame('Enter text', $fields[1]['fields'][0]['translations']['en']['placeholder']);
    }
}
