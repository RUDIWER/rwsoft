<?php

namespace Tests\Unit\Cms;

use App\Actions\Admin\Cms\Themes\GenerateThemeCssFromSettingsAction;
use Tests\TestCase;

class GenerateThemeCssFromSettingsActionTest extends TestCase
{
    public function test_it_generates_css_variables_and_selector_rules_from_whitelisted_settings(): void
    {
        $css = app(GenerateThemeCssFromSettingsAction::class)->handle([
            'primary_color' => '#ff0000',
            'h1_font_size' => '4rem',
            'h1_color' => '#111111',
            'unknown_setting' => 'display:none',
        ]);

        $this->assertStringContainsString('--rw-public-color-primary: #ff0000;', $css);
        $this->assertStringContainsString('.rw-public-title {', $css);
        $this->assertStringContainsString('font-size: 4rem;', $css);
        $this->assertStringContainsString('color: #111111;', $css);
        $this->assertStringNotContainsString('unknown_setting', $css);
        $this->assertStringNotContainsString('display:none', $css);
    }

    public function test_it_ignores_invalid_color_and_css_breakout_values(): void
    {
        $css = app(GenerateThemeCssFromSettingsAction::class)->handle([
            'primary_color' => 'red; color: blue',
            'body_font_size' => '1rem; background: red',
        ]);

        $this->assertSame("/* Admin basisinstellingen zijn nog niet geconfigureerd. */\n", $css);
    }
}
