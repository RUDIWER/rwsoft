<?php

namespace Tests\Unit\Cms;

use App\Support\Cms\CmsHtmlSanitizer;
use Tests\TestCase;

class CmsHtmlSanitizerTest extends TestCase
{
    public function test_removes_executable_html_and_event_handlers(): void
    {
        $clean = app(CmsHtmlSanitizer::class)->clean(
            '<p onclick="alert(1)">Hello <strong>world</strong></p><script>alert(1)</script><iframe src="https://example.com"></iframe>',
        );

        $this->assertStringContainsString('<p>Hello <strong>world</strong></p>', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringNotContainsString('alert(1)', $clean);
    }

    public function test_removes_unsafe_link_urls_and_keeps_safe_links(): void
    {
        $clean = app(CmsHtmlSanitizer::class)->clean(
            '<a href="javascript:alert(1)">Bad</a><a href="/contact" target="_blank">Contact</a><a href="https://example.com">External</a>',
        );

        $this->assertStringContainsString('<a>Bad</a>', $clean);
        $this->assertStringContainsString('<a href="/contact" target="_blank" rel="noopener noreferrer">Contact</a>', $clean);
        $this->assertStringContainsString('<a href="https://example.com">External</a>', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_preserves_utf8_text(): void
    {
        $clean = app(CmsHtmlSanitizer::class)->clean('<p>Één café in België</p>');

        $this->assertSame('<p>Één café in België</p>', $clean);
    }

    public function test_keeps_safe_images_and_removes_unsafe_image_attributes(): void
    {
        $clean = app(CmsHtmlSanitizer::class)->clean(
            '<figure><img src="/storage/cms/example.jpg" alt="Example" width="640" height="360" loading="lazy" onclick="alert(1)"><figcaption>Caption</figcaption></figure><img src="javascript:alert(1)" onerror="alert(2)">',
        );

        $this->assertStringContainsString('<figure><img src="/storage/cms/example.jpg" alt="Example" width="640" height="360" loading="lazy"><figcaption>Caption</figcaption></figure>', $clean);
        $this->assertStringContainsString('<img>', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }
}
