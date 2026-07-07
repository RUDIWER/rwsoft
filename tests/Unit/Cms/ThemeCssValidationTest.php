<?php

namespace Tests\Unit\Cms;

use App\Actions\Admin\Cms\Themes\ValidateThemeCssAction;
use Tests\TestCase;

class ThemeCssValidationTest extends TestCase
{
    public function test_it_blocks_imports_and_unsafe_url_schemes(): void
    {
        $validator = new ValidateThemeCssAction;

        $result = $validator->handle(<<<'CSS'
@import url("https://fonts.bunny.net/css?family=Inter");
.hero { background-image: url("javascript:alert(1)"); }
.card { background-image: url("http://example.com/card.webp"); }
CSS);

        $this->assertFalse($result['valid']);
        $rules = collect($result['errors'])->pluck('rule')->all();

        $this->assertContains('css_import_not_allowed', $rules);
        $this->assertContains('blocked_url_scheme', $rules);
        $this->assertContains('insecure_external_asset', $rules);
        $this->assertSame(1, $result['errors'][0]['line']);
    }

    public function test_it_allows_whitelisted_https_assets_and_returns_warning(): void
    {
        config([
            'cms_themes.css.allowed_external_hosts' => ['cdn.example.com'],
            'cms_themes.css.allowed_asset_extensions' => ['woff2', 'webp'],
        ]);

        $validator = new ValidateThemeCssAction;

        $result = $validator->handle(<<<'CSS'
@font-face { font-family: Brand; src: url("https://cdn.example.com/fonts/brand.woff2") format("woff2"); }
.hero { background-image: url("https://cdn.example.com/images/hero.webp"); }
CSS);

        $this->assertTrue($result['valid']);
        $this->assertCount(2, $result['warnings']);
        $this->assertSame([
            'https://cdn.example.com/fonts/brand.woff2',
            'https://cdn.example.com/images/hero.webp',
        ], $result['external_assets']);
    }

    public function test_it_blocks_non_whitelisted_https_hosts(): void
    {
        config([
            'cms_themes.css.allowed_external_hosts' => ['cdn.example.com'],
        ]);

        $validator = new ValidateThemeCssAction;

        $result = $validator->handle('.hero { background-image: url("https://evil.example.net/hero.webp"); }');

        $this->assertFalse($result['valid']);
        $this->assertSame('external_host_not_allowed', $result['errors'][0]['rule']);
    }
}
