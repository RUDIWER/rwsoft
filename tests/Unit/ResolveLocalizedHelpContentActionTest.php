<?php

namespace Tests\Unit;

use App\Actions\Admin\Base\ResolveLocalizedHelpContentAction;
use Tests\TestCase;

class ResolveLocalizedHelpContentActionTest extends TestCase
{
    public function test_it_returns_locale_specific_help_content(): void
    {
        $html = ResolveLocalizedHelpContentAction::handle(
            'admin/query/template-cheatsheet',
            'nl',
        );

        $this->assertStringContainsString('Gebruik deze placeholders', $html);
    }

    public function test_it_falls_back_to_configured_fallback_locale(): void
    {
        config()->set('app.fallback_locale', 'fr');

        $html = ResolveLocalizedHelpContentAction::handle(
            'admin/query/template-cheatsheet',
            'es',
        );

        $this->assertStringContainsString('Utilisez ces placeholders', $html);
    }

    public function test_it_falls_back_to_english_when_needed(): void
    {
        config()->set('app.fallback_locale', 'es');

        $html = ResolveLocalizedHelpContentAction::handle(
            'admin/query/template-cheatsheet',
            'xx',
        );

        $this->assertStringContainsString('Use these placeholders', $html);
    }

    public function test_it_returns_empty_string_for_invalid_or_missing_keys(): void
    {
        $invalid = ResolveLocalizedHelpContentAction::handle('../secret', 'nl');
        $missing = ResolveLocalizedHelpContentAction::handle('admin/query/not-existing', 'nl');

        $this->assertSame('', $invalid);
        $this->assertSame('', $missing);
    }
}
