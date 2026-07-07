<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTemplate;
use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use App\Support\Cms\CmsTemplateDataContract;
use App\Support\Cms\Search\CmsSearchService;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsNavigationBuilder;
use App\Support\PublicSite\CmsPublicTextResolver;
use App\Support\PublicSite\CmsSeoData;
use App\Support\PublicSite\CmsTemplateCompositionBuilder;
use App\Support\PublicSite\CmsTemplateResolver;
use App\Support\PublicSite\PublicStorageUrl;
use App\Support\PublicSite\PublicViewResolver;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;

class CmsSearchController extends Controller
{
    public function __construct(
        private readonly CmsSearchService $searchService,
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsTemplateResolver $templateResolver,
        private readonly CmsTemplateCompositionBuilder $templateCompositionBuilder,
        private readonly PublicViewResolver $viewResolver,
        private readonly CmsNavigationBuilder $navigationBuilder,
        private readonly CmsPublicTextResolver $publicTextResolver,
        private readonly PublicStorageUrl $storageUrl,
        private readonly CmsSeoData $seoData,
    ) {}

    public function index(Request $request, string $locale): View
    {
        $locale = $this->resolveLocale($locale);
        App::setLocale($locale);
        $query = trim((string) $request->query('q', ''));
        $results = $query !== '' ? $this->searchService->search($query, $locale) : [];
        $site = $this->sitePayload($request, $locale);
        $title = public_text('search.title', 'Search', $locale);
        $search = [
            'title' => $title,
            'query' => $query,
            'results' => $results,
            'result_count' => count($results),
            'endpoint' => route('cms.public.localized.search.results', ['locale' => $locale]),
        ];
        $template = $this->templateResolver->resolve('search.index', $locale);

        if ($template instanceof CmsTemplate) {
            $contentItem = [
                'id' => 'search-'.$locale,
                'title' => $title,
                'locale' => $locale,
                'template_class' => 'search',
                'template_key' => 'search.index',
                'settings' => [],
            ];
            $context = ['search' => $search];
            $context['__template'] = [
                'template_class' => $template->template_class,
                'template_key' => $template->template_key,
                'locale' => $template->locale,
                'data_contract' => app(CmsTemplateDataContract::class)->normalize($template->data_contract, (string) $template->template_key),
            ];
            $composition = $this->templateCompositionBuilder->handle($template, $contentItem, $context);

            return view($this->viewResolver->template(), [
                'pageItem' => $composition['page'],
                'composition' => $composition,
                'site' => $site,
                'navigation' => $this->navigationBuilder->handle($locale),
                'seo' => $this->seoData->forDocumentation($title, null, true, $site, $request),
                'search' => $search,
                'translations' => [],
            ]);
        }

        return view('public.system.search.index', [
            'pageItem' => [
                'id' => 'search-'.$locale,
                'title' => $title,
                'locale' => $locale,
                'settings' => [],
            ],
            'composition' => [
                'page' => ['id' => 'search-'.$locale, 'title' => $title, 'locale' => $locale, 'settings' => []],
                'layout' => ['settings' => ['scroll_mode' => 'browser']],
                'sections' => ['head' => [], 'header' => [], 'header_scroll' => [], 'header_sticky' => [], 'content' => [], 'footer' => [], 'footer_scroll' => [], 'footer_sticky' => [], 'body_end' => []],
                'styles' => [],
            ],
            'site' => $site,
            'navigation' => $this->navigationBuilder->handle($locale),
            'seo' => $this->seoData->forDocumentation($title, null, true, $site, $request),
            'search' => $search,
            'translations' => [],
        ]);
    }

    public function results(Request $request, string $locale): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);
        $query = trim((string) ($validated['q'] ?? ''));

        return response()->json([
            'results' => $query !== '' ? $this->searchService->search($query, $this->resolveLocale($locale)) : [],
        ]);
    }

    private function resolveLocale(string $locale): string
    {
        return in_array($locale, $this->languageSettings->activeLocales(), true)
            ? $locale
            : $this->languageSettings->defaultLocale();
    }

    /**
     * @return array<string, mixed>
     */
    private function sitePayload(Request $request, string $locale): array
    {
        return [
            'name' => $this->settingValue('general', 'site_name', TenantContext::site()?->name ?? config('app.name'), $locale),
            'tagline' => $this->settingValue('general', 'site_tagline', null, $locale),
            'default_locale' => $this->languageSettings->defaultLocale(),
            'current_locale' => $locale,
            'multilingual_enabled' => $this->languageSettings->multilingualEnabled(),
            'available_languages' => $this->languageSettings->languages(true),
            'available_locales' => $this->languageSettings->activeLocales(),
            'global_noindex' => (bool) $this->settingValue('seo', 'global_noindex', false),
            'seo_default_title' => $this->settingValue('seo', 'default_title', null, $locale),
            'seo_default_description' => $this->settingValue('seo', 'default_description', null, $locale),
            'texts' => $this->publicTextResolver->all($locale),
            'active_theme_css_url' => $this->themeCssUrl($request),
            'favicon' => $this->faviconPayload(),
            'logo_url' => $this->versionedPublicUrl($this->settingValue('branding', 'logo_path'), $this->settingValue('branding', 'logo_version')),
            'logo_show_tagline' => (bool) $this->settingValue('branding', 'logo_show_tagline', false),
        ];
    }

    private function settingValue(string $group, string $key, mixed $default = null, ?string $locale = null): mixed
    {
        if (! Schema::hasTable('cms_settings')) {
            return $default;
        }

        $setting = CmsSetting::query()->where('group', $group)->where('key', $key)->first();

        if (! $setting instanceof CmsSetting) {
            return $default;
        }

        return $this->publicTextResolver->settingValue($setting, $default, $locale);
    }

    private function themeCssUrl(Request $request): ?string
    {
        if (! Schema::hasTable('cms_themes') || ! Schema::hasTable('cms_theme_versions')) {
            return null;
        }

        $theme = CmsTheme::query()->where('status', 'active')->first();
        $version = $theme instanceof CmsTheme ? CmsThemeVersion::query()->where('cms_theme_id', $theme->id)->where('status', 'active')->first() : null;

        return $theme instanceof CmsTheme && $version instanceof CmsThemeVersion && $version->compiled_css_hash
            ? $request->getSchemeAndHttpHost().route('cms.theme.active', ['hash' => $version->compiled_css_hash], false)
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function faviconPayload(): ?array
    {
        $path = $this->settingValue('branding', 'favicon_path');
        $version = $this->settingValue('branding', 'favicon_version');
        $url = $this->versionedPublicUrl($path, $version);

        return $url === null ? null : ['url' => $url, 'version' => $version];
    }

    private function versionedPublicUrl(mixed $path, mixed $version = null): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $url = $this->storageUrl->url($path);

        return is_scalar($version) && trim((string) $version) !== ''
            ? $url.'?v='.rawurlencode((string) $version)
            : $url;
    }
}
