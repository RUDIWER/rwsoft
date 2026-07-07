<?php

namespace Tests\Unit\Cms;

use App\Support\Cms\Blocks\CmsBlockManifestValidator;
use App\Support\Cms\CmsBlockRegistry;
use Tests\TestCase;

class CmsBlockZoneValidationTest extends TestCase
{
    public function test_manifest_accepts_configured_content_and_layout_zones(): void
    {
        $registry = app(CmsBlockRegistry::class);
        $zones = array_values(array_unique(array_merge($registry->contentZones(), $registry->layoutZones())));
        $manifest = $this->manifest(['allowed_zones' => $zones]);

        $this->assertSame([], app(CmsBlockManifestValidator::class)->errors($manifest));
    }

    public function test_manifest_rejects_unknown_zones(): void
    {
        $errors = app(CmsBlockManifestValidator::class)->errors($this->manifest([
            'allowed_zones' => ['content', 'sidebar'],
        ]));

        $this->assertContains('Block [0] zone [sidebar] is unsupported.', $errors);
    }

    public function test_block_rules_keep_language_switcher_content_fields(): void
    {
        $rules = app(CmsBlockRegistry::class)->blockRules('block');

        $this->assertArrayHasKey('block.label_display', $rules);
        $this->assertArrayHasKey('block.show_current', $rules);
        $this->assertArrayHasKey('block.hide_missing_translations', $rules);
        $this->assertArrayHasKey('block.flag_position', $rules);
        $this->assertArrayHasKey('block.flag_shape', $rules);
        $this->assertArrayHasKey('block.flag_size', $rules);
    }

    /**
     * @param  array<string, mixed>  $blockOverrides
     * @return array<string, mixed>
     */
    private function manifest(array $blockOverrides = []): array
    {
        return [
            'manifest_version' => 1,
            'package_key' => 'rwsoft.zones',
            'blocks' => [array_merge([
                'key' => 'zone_notice',
                'name' => 'Zone notice',
                'category' => 'content',
                'source' => 'package',
                'allowed_zones' => ['content'],
                'rendering_mode' => 'safe_blade',
                'renderer_key' => 'zone_notice',
                'template_source' => '<article>{{ block.title }}</article>',
            ], $blockOverrides)],
        ];
    }
}
