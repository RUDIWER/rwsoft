<?php

namespace Tests\Feature\PublicSite;

use App\Models\Cms\CmsSetting;

class PublicCmsRobotsTxtTest extends PublicCmsTestCase
{
    public function test_robots_txt_renders_default_rules_and_sitemap(): void
    {
        $this
            ->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *', false)
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Disallow: /login', false)
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }

    public function test_robots_txt_includes_extra_rules_from_settings(): void
    {
        $this->storeRobotsExtraRules("# Tijdelijk\nDisallow: /campagne\nAllow: /campagne/publiek");

        $this
            ->get('/robots.txt')
            ->assertOk()
            ->assertSee('# Extra regels uit CMS', false)
            ->assertSee('Disallow: /campagne', false)
            ->assertSee('Allow: /campagne/publiek', false)
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }

    public function test_global_noindex_disallows_all_and_hides_extra_rules(): void
    {
        $this->storeSetting('seo', 'global_noindex', true);
        $this->storeRobotsExtraRules('Disallow: /campagne');

        $this
            ->get('/robots.txt')
            ->assertOk()
            ->assertSee("User-agent: *\nDisallow: /", false)
            ->assertDontSee('Disallow: /admin')
            ->assertDontSee('Disallow: /campagne')
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }

    private function storeRobotsExtraRules(string $rules): void
    {
        CmsSetting::query()->updateOrCreate(
            ['group' => 'seo', 'key' => 'robots_extra_rules'],
            [
                'label' => 'Robots.txt extra regels',
                'type' => 'textarea',
                'value' => ['value' => $rules],
                'is_public' => true,
                'sort_order' => 80,
            ]
        );
    }
}
