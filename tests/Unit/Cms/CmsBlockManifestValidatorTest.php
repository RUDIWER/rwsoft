<?php

namespace Tests\Unit\Cms;

use App\Support\Cms\Blocks\CmsBlockManifestValidator;
use Tests\TestCase;

class CmsBlockManifestValidatorTest extends TestCase
{
    public function test_valid_safe_blade_manifest_has_no_errors(): void
    {
        $errors = app(CmsBlockManifestValidator::class)->errors($this->manifest());

        $this->assertSame([], $errors);
    }

    public function test_non_safe_blade_renderer_must_be_registered(): void
    {
        $manifest = $this->manifest([
            'rendering_mode' => 'platform_blade',
            'renderer_key' => 'unknown_renderer',
            'template_source' => null,
        ]);

        $this->assertContains('Block [0] renderer_key is not registered.', app(CmsBlockManifestValidator::class)->errors($manifest));
    }

    public function test_safe_blade_template_and_css_are_validated(): void
    {
        $manifest = $this->manifest([
            'template_source' => '{{ block.title() }}',
            'css_source' => '.bad { color: red; }</style>',
        ]);
        $errors = app(CmsBlockManifestValidator::class)->errors($manifest);

        $this->assertContains('Block [0] SafeBlade template is invalid.', $errors);
        $this->assertContains('Block [0] css_source contains forbidden CSS syntax.', $errors);
    }

    /**
     * @param  array<string, mixed>  $blockOverrides
     * @return array<string, mixed>
     */
    private function manifest(array $blockOverrides = []): array
    {
        return [
            'manifest_version' => 1,
            'package_key' => 'rwsoft.test',
            'blocks' => [array_merge([
                'key' => 'package_notice',
                'name' => 'Package notice',
                'category' => 'content',
                'source' => 'package',
                'allowed_zones' => ['content'],
                'rendering_mode' => 'safe_blade',
                'renderer_key' => 'package_notice',
                'template_source' => '<article>{{ block.title }}</article>',
                'css_source' => '.package-notice { color: green; }',
                'schema' => ['fields' => ['title'], 'editor_fields' => [], 'preview' => []],
                'defaults' => ['title' => 'Default title'],
            ], $blockOverrides)],
        ];
    }
}
