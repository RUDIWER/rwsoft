<?php

namespace Tests\Unit\Cms;

use App\Actions\Admin\Cms\Seo\ValidateCmsSeoRulesAction;
use App\Support\Cms\Seo\CmsSeoSettings;
use Tests\TestCase;

class ValidateCmsSeoRulesActionTest extends TestCase
{
    public function test_it_blocks_invalid_required_publish_seo_fields(): void
    {
        app()->setLocale('nl');

        $result = $this->validator([
            'seo_require_json_ld' => true,
        ])->handle([
            'title' => 'Korte titel voor pagina',
            'slug' => 'Ongeldige Slug',
            'seo_title' => '',
            'seo_description' => '',
            'structured_data_extra' => '',
        ], 'page', true);

        $messages = implode(' ', $result['errors']);

        $this->assertStringContainsString('Gebruik alleen kleine letters', $messages);
        $this->assertStringContainsString('SEO titel is verplicht', $messages);
        $this->assertStringContainsString('SEO omschrijving is verplicht', $messages);
        $this->assertStringContainsString('JSON-LD is verplicht', $messages);
    }

    public function test_it_reports_heading_hierarchy_and_thin_content_warnings(): void
    {
        app()->setLocale('nl');

        $result = $this->validator([
            'seo_content_min_words' => 40,
        ])->handle([
            'title' => 'Complete hoofdtitel voor deze pagina',
            'slug' => 'complete-hoofdtitel',
            'seo_title' => 'Complete SEO titel voor deze pagina',
            'seo_description' => str_repeat('omschrijving ', 12),
            'content_blocks' => [
                [
                    'type' => 'text',
                    'title' => 'Te diepe subtitel',
                    'heading_level' => 'h3',
                    'text' => 'Korte inhoud.',
                ],
            ],
        ], 'page', true);

        $warnings = implode(' ', $result['warnings']);

        $this->assertStringContainsString('heading-hierarchie', $warnings);
        $this->assertStringContainsString('tekstinhoud is mogelijk te dun', $warnings);
    }

    public function test_it_accepts_complete_seo_content_without_warnings(): void
    {
        app()->setLocale('nl');

        $content = implode(' ', array_fill(0, 90, 'inhoud'));

        $result = $this->validator()->handle([
            'title' => 'Complete hoofdtitel voor deze pagina',
            'slug' => 'complete-hoofdtitel',
            'seo_title' => 'Complete SEO titel voor deze pagina',
            'seo_description' => str_repeat('omschrijving ', 12),
            'structured_data_extra' => '{"@type":"WebPage"}',
            'content_blocks' => [
                [
                    'type' => 'text',
                    'title' => 'Logische subtitel',
                    'heading_level' => 'h2',
                    'text' => $content,
                ],
            ],
        ], 'page', true);

        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
    }

    /**
     * @param  array<string, int|bool|string>  $overrides
     */
    private function validator(array $overrides = []): ValidateCmsSeoRulesAction
    {
        $settings = new class($overrides) extends CmsSeoSettings
        {
            /**
             * @param  array<string, int|bool|string>  $overrides
             */
            public function __construct(private readonly array $overrides) {}

            /**
             * @return array<string, int|bool|string>
             */
            public function values(): array
            {
                return array_merge($this->defaults(), $this->overrides);
            }
        };

        return new ValidateCmsSeoRulesAction($settings);
    }
}
