<?php

namespace Tests\Unit\Cms;

use App\Support\PublicSite\CmsCanonicalUrlPolicy;
use App\Support\PublicSite\CmsLanguageSettings;
use Illuminate\Http\Request;
use Tests\TestCase;

class CmsCanonicalUrlPolicyTest extends TestCase
{
    public function test_it_accepts_empty_relative_and_same_site_canonicals(): void
    {
        $policy = $this->policy();
        $request = Request::create('https://example.test/admin/cms/pages/1/edit');

        $this->assertTrue($policy->isValid(null, 'en', $request));
        $this->assertTrue($policy->isValid('', 'en', $request));
        $this->assertTrue($policy->isValid('/en/about-us', 'en', $request));
        $this->assertTrue($policy->isValid('https://example.test/en/about-us', 'en', $request));
        $this->assertTrue($policy->isValid('https://example.test:8443/en/about-us', 'en', Request::create('https://example.test:8443/admin')));
    }

    public function test_it_rejects_external_and_unsafe_canonicals(): void
    {
        $policy = $this->policy();
        $request = Request::create('https://example.test/admin/cms/pages/1/edit');

        $this->assertFalse($policy->isValid('https://other.test/en/about-us', 'en', $request));
        $this->assertFalse($policy->isValid('//other.test/en/about-us', 'en', $request));
        $this->assertFalse($policy->isValid('javascript:alert(1)', 'en', $request));
        $this->assertFalse($policy->isValid('mailto:test@example.test', 'en', $request));
    }

    public function test_it_rejects_wrong_locale_prefixes(): void
    {
        $policy = $this->policy();
        $request = Request::create('https://example.test/admin/cms/pages/1/edit');

        $this->assertFalse($policy->isValid('/nl/over-ons', 'en', $request));
        $this->assertFalse($policy->isValid('https://example.test/fr/a-propos', 'en', $request));
        $this->assertFalse($policy->isValid('/en/about-us', 'nl', $request));
        $this->assertTrue($policy->isValid('/over-ons', 'nl', $request));
        $this->assertTrue($policy->isValid('/nl/over-ons', 'nl', $request));
    }

    public function test_it_normalizes_relative_canonicals_to_absolute_urls(): void
    {
        $policy = $this->policy();
        $request = Request::create('https://example.test/admin/cms/pages/1/edit');

        $this->assertSame(
            'https://example.test/en/about-us',
            $policy->toAbsoluteUrl('/en/about-us', 'https://example.test/en/fallback', 'en', $request),
        );

        $this->assertSame(
            'https://example.test/en/fallback',
            $policy->toAbsoluteUrl('https://other.test/en/about-us', 'https://example.test/en/fallback', 'en', $request),
        );
    }

    public function test_it_blocks_locale_prefixes_when_multilingual_is_disabled(): void
    {
        $policy = $this->policy(multilingualEnabled: false);
        $request = Request::create('https://example.test/admin/cms/pages/1/edit');

        $this->assertTrue($policy->isValid('/about-us', 'en', $request));
        $this->assertFalse($policy->isValid('/en/about-us', 'en', $request));
    }

    private function policy(bool $multilingualEnabled = true): CmsCanonicalUrlPolicy
    {
        $languageSettings = new class($multilingualEnabled) extends CmsLanguageSettings
        {
            public function __construct(private readonly bool $enabled) {}

            /**
             * @return array<int, string>
             */
            public function activeLocales(): array
            {
                return ['nl', 'en', 'fr'];
            }

            public function defaultLocale(): string
            {
                return 'nl';
            }

            public function multilingualEnabled(): bool
            {
                return $this->enabled;
            }
        };

        return new CmsCanonicalUrlPolicy($languageSettings);
    }
}
