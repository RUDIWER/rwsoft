<?php

namespace Tests\Unit\Cms;

use App\Support\PublicSite\CmsJsonLdTemplateValidator;
use Tests\TestCase;

class CmsJsonLdTemplateValidatorTest extends TestCase
{
    public function test_it_accepts_valid_json_ld_with_allowed_placeholders(): void
    {
        $template = json_encode([
            '@type' => 'FAQPage',
            'name' => '{{ page.title }}',
            'description' => '{{ page.short_description }}',
        ], JSON_THROW_ON_ERROR);

        $errors = app(CmsJsonLdTemplateValidator::class)->errors($template, 'cms.page.json_ld');

        $this->assertSame([], $errors);
    }

    public function test_it_rejects_invalid_json_and_unknown_placeholders(): void
    {
        $errors = app(CmsJsonLdTemplateValidator::class)->errors('{"name":"{{ page.unknown }}"', 'cms.page.json_ld');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('niet-toegelaten placeholders', implode(' ', $errors));
        $this->assertStringContainsString('geldige JSON', implode(' ', $errors));
    }

    public function test_it_rejects_script_tags(): void
    {
        $errors = app(CmsJsonLdTemplateValidator::class)->errors('<script>alert(1)</script>', 'cms.page.json_ld');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('geen HTML', implode(' ', $errors));
    }
}
