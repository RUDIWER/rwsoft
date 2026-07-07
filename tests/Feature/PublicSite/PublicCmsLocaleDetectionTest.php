<?php

namespace Tests\Feature\PublicSite;

use App\Models\Cms\CmsLanguage;
use App\Models\Cms\CmsPage;
use App\Support\PublicSite\PublicSiteLocaleDetector;

class PublicCmsLocaleDetectionTest extends PublicCmsTestCase
{
    public function test_homepage_redirects_to_browser_language_when_enabled(): void
    {
        $this->configureLanguages(['nl', 'fr']);
        $this->configureAutoLocaleSettings();
        $this->createLocalizedHomepage('nl');
        $this->createLocalizedHomepage('fr');

        $this
            ->withHeaders(['Accept-Language' => 'fr-BE,fr;q=0.9,nl;q=0.8'])
            ->get(route('cms.public.home'))
            ->assertRedirect('/fr');
    }

    public function test_remembered_cookie_language_wins_over_browser_language(): void
    {
        $this->configureLanguages(['nl', 'en', 'fr']);
        $this->configureAutoLocaleSettings();
        $this->createLocalizedHomepage('nl');
        $this->createLocalizedHomepage('en');
        $this->createLocalizedHomepage('fr');

        $this
            ->withCookie(PublicSiteLocaleDetector::COOKIE_NAME, 'en')
            ->withHeaders(['Accept-Language' => 'fr-BE,fr;q=0.9'])
            ->get(route('cms.public.home'))
            ->assertRedirect('/en');
    }

    public function test_country_header_is_used_as_fallback_after_browser_language(): void
    {
        $this->configureLanguages(['nl', 'fr']);
        $this->configureAutoLocaleSettings(countryMap: "FR=fr\nBE=nl", rememberChoice: false);
        $this->createLocalizedHomepage('nl');
        $this->createLocalizedHomepage('fr');

        $this
            ->withHeaders(['CF-IPCountry' => 'FR'])
            ->get(route('cms.public.home'))
            ->assertRedirect('/fr');
    }

    public function test_explicit_localized_url_sets_remembered_language_cookie(): void
    {
        $this->configureLanguages(['nl', 'fr']);
        $this->configureAutoLocaleSettings();
        $this->createLocalizedHomepage('nl');
        $this->createLocalizedHomepage('fr');

        $this
            ->get(route('cms.public.localized.home', ['locale' => 'fr']))
            ->assertOk()
            ->assertCookie(PublicSiteLocaleDetector::COOKIE_NAME, 'fr');
    }

    /**
     * @param  array<int, string>  $locales
     */
    private function configureLanguages(array $locales): void
    {
        CmsLanguage::query()->update(['is_active' => false]);

        foreach ($locales as $index => $locale) {
            CmsLanguage::query()->updateOrCreate(
                ['locale' => $locale],
                [
                    'name' => strtoupper($locale),
                    'native_name' => strtoupper($locale),
                    'direction' => 'ltr',
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                ],
            );
        }

        $this->storeSetting('general', 'default_locale', 'nl');
        $this->storeSetting('general', 'multilingual_enabled', true);
    }

    private function configureAutoLocaleSettings(string $countryMap = '', bool $rememberChoice = true): void
    {
        $this->storeSetting('localization', 'auto_locale_detection_enabled', true);
        $this->storeSetting('localization', 'auto_locale_detection_strategy', PublicSiteLocaleDetector::STRATEGY_BROWSER_THEN_IP);
        $this->storeSetting('localization', 'auto_locale_redirect_enabled', true);
        $this->storeSetting('localization', 'auto_locale_remember_choice', $rememberChoice);
        $this->storeSetting('localization', 'auto_locale_cookie_days', 180);
        $this->storeSetting('localization', 'auto_locale_country_map', $countryMap);
    }

    private function createLocalizedHomepage(string $locale): CmsPage
    {
        return $this->createPage([
            'title' => 'Home '.$locale,
            'slug' => 'home-'.$locale,
            'locale' => $locale,
            'is_home' => true,
            'translation_key' => 'home-locale-detection',
        ]);
    }
}
