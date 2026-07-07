<?php

namespace Tests\Unit\Cms;

use App\Support\Cms\CmsResponsiveLayoutNormalizer;
use PHPUnit\Framework\TestCase;

class CmsResponsiveLayoutNormalizerTest extends TestCase
{
    public function test_it_normalizes_hex_colors(): void
    {
        $normalizer = new CmsResponsiveLayoutNormalizer;

        $this->assertSame('#ffffff', $normalizer->normalizeHexColor('#fff'));
        $this->assertSame('#1a2b3c', $normalizer->normalizeHexColor(' #1A2B3C '));
    }

    public function test_it_rejects_invalid_hex_colors(): void
    {
        $normalizer = new CmsResponsiveLayoutNormalizer;

        $this->assertNull($normalizer->normalizeHexColor(null));
        $this->assertNull($normalizer->normalizeHexColor('fff'));
        $this->assertNull($normalizer->normalizeHexColor('#ffff'));
        $this->assertNull($normalizer->normalizeHexColor('#fff; color:red'));
    }

    public function test_it_normalizes_style_background_color(): void
    {
        $normalizer = new CmsResponsiveLayoutNormalizer;

        $style = $normalizer->normalizeStyle([
            'devices' => [
                'desktop' => [
                    'z_index' => '20',
                    'appearance' => [
                        'background_color' => '#ABC',
                        'foreground_color_token' => 'primary',
                        'logo_size' => 'large',
                    ],
                ],
            ],
        ]);

        $this->assertSame('#aabbcc', $style['devices']['desktop']['appearance']['background_color']);
        $this->assertSame('#aabbcc', $style['devices']['tablet']['appearance']['background_color']);
        $this->assertSame('#aabbcc', $style['devices']['mobile']['appearance']['background_color']);
        $this->assertSame('primary', $style['devices']['desktop']['appearance']['foreground_color_token']);
        $this->assertSame('primary', $style['devices']['tablet']['appearance']['foreground_color_token']);
        $this->assertSame('primary', $style['devices']['mobile']['appearance']['foreground_color_token']);
        $this->assertSame('20', $style['devices']['desktop']['z_index']);
        $this->assertSame('20', $style['devices']['tablet']['z_index']);
        $this->assertSame('20', $style['devices']['mobile']['z_index']);
        $this->assertSame('large', $style['devices']['desktop']['appearance']['logo_size']);
        $this->assertSame('large', $style['devices']['tablet']['appearance']['logo_size']);
        $this->assertSame('large', $style['devices']['mobile']['appearance']['logo_size']);
    }

    public function test_it_normalizes_language_device_labels_and_icons(): void
    {
        $normalizer = new CmsResponsiveLayoutNormalizer;

        $style = $normalizer->normalizeLanguageStyle([
            'devices' => [
                'desktop' => [
                    'label' => 'Languages',
                    'icon' => 'mdi-earth',
                ],
                'tablet' => [
                    'label' => str_repeat('A', 140),
                    'icon' => 'mdi-custom-safe',
                ],
                'mobile' => [
                    'icon' => 'unsafe icon',
                ],
            ],
        ]);

        $this->assertSame('Languages', $style['devices']['desktop']['label']);
        $this->assertSame('mdi-earth', $style['devices']['desktop']['icon']);
        $this->assertSame(str_repeat('A', 120), $style['devices']['tablet']['label']);
        $this->assertSame('mdi-custom-safe', $style['devices']['tablet']['icon']);
        $this->assertSame('', $style['devices']['mobile']['label']);
        $this->assertSame('none', $style['devices']['mobile']['icon']);
    }

    public function test_it_normalizes_form_style(): void
    {
        $normalizer = new CmsResponsiveLayoutNormalizer;

        $style = $normalizer->normalizeStyle([
            'form' => [
                'field_spacing' => 'spacious',
                'label_weight' => 'bold',
                'input_radius' => 'pill',
                'input_border' => 'primary',
                'input_background_color' => 'rgba(255, 255, 255, .85)',
                'input_text_color' => 'url(javascript:alert(1))',
                'submit_alignment' => 'stretch',
                'submit_variant' => 'outline',
            ],
        ]);

        $this->assertSame('spacious', $style['form']['field_spacing']);
        $this->assertSame('bold', $style['form']['label_weight']);
        $this->assertSame('pill', $style['form']['input_radius']);
        $this->assertSame('primary', $style['form']['input_border']);
        $this->assertSame('rgba(255, 255, 255, .85)', $style['form']['input_background_color']);
        $this->assertNull($style['form']['input_text_color']);
        $this->assertSame('stretch', $style['form']['submit_alignment']);
        $this->assertSame('outline', $style['form']['submit_variant']);
    }
}
