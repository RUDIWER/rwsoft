<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Cms\CmsDocCollection;
use App\Models\Cms\CmsDocPage;
use App\Models\Cms\CmsDocVersion;
use App\Models\Cms\CmsModule;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTemplate;
use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use App\Support\Cms\CmsTemplateDataContract;
use App\Support\Cms\Docs\CmsDocsMarkdownRenderer;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;

class CmsDocsController extends Controller
{
    public function __construct(
        private readonly CmsDocsMarkdownRenderer $markdownRenderer,
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsNavigationBuilder $navigationBuilder,
        private readonly CmsTemplateResolver $templateResolver,
        private readonly CmsTemplateCompositionBuilder $templateCompositionBuilder,
        private readonly PublicViewResolver $viewResolver,
        private readonly CmsPublicTextResolver $publicTextResolver,
        private readonly PublicStorageUrl $storageUrl,
        private readonly CmsSeoData $seoData,
    ) {}

    public function index(Request $request, string $locale): View|RedirectResponse
    {
        abort_unless($this->docsModuleInstalled(), 404);

        $version = CmsDocVersion::query()
            ->whereHas('collection', fn (Builder $query) => $query->where('is_active', true))
            ->whereHas('pages', fn (Builder $query) => $query->where('status', 'published')->where('locale', $this->resolveLocale($locale)))
            ->where('cms_doc_versions.is_active', true)
            ->with('collection')
            ->join('cms_doc_collections', 'cms_doc_collections.id', '=', 'cms_doc_versions.cms_doc_collection_id')
            ->orderByDesc('cms_doc_versions.is_default')
            ->orderBy('cms_doc_collections.sort_order')
            ->orderBy('cms_doc_collections.name')
            ->orderBy('cms_doc_versions.sort_order')
            ->select('cms_doc_versions.*')
            ->first();

        abort_unless($version instanceof CmsDocVersion, 404);

        return redirect()->route('cms.public.docs.version', [
            'locale' => $this->resolveLocale($locale),
            'collection' => $version->collection?->slug,
            'version' => $version->slug,
        ]);
    }

    public function collection(Request $request, string $locale, string $collection): View|RedirectResponse
    {
        $resolvedLocale = $this->resolveLocale($locale);
        $docCollection = $this->collectionForSlug($collection);
        abort_unless($docCollection instanceof CmsDocCollection, 404);

        $version = $docCollection->versions()
            ->where('is_active', true)
            ->whereHas('pages', fn (Builder $query) => $query->where('status', 'published')->where('locale', $resolvedLocale))
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();

        abort_unless($version instanceof CmsDocVersion, 404);

        return redirect()->route('cms.public.docs.version', [
            'locale' => $resolvedLocale,
            'collection' => $docCollection->slug,
            'version' => $version->slug,
        ]);
    }

    public function version(Request $request, string $locale, string $collection, string $version): View|RedirectResponse
    {
        $resolvedLocale = $this->resolveLocale($locale);
        $docVersion = $this->versionForSlug($collection, $version);
        abort_unless($docVersion instanceof CmsDocVersion, 404);

        $page = $this->publishedPages($docVersion, $resolvedLocale)->first();

        if ($page instanceof CmsDocPage) {
            return redirect()->route('cms.public.docs.show', [
                'locale' => $resolvedLocale,
                'collection' => $docVersion->collection?->slug,
                'version' => $docVersion->slug,
                'path' => $page->path,
            ]);
        }

        return $this->render($request, $resolvedLocale, $docVersion, null);
    }

    public function show(Request $request, string $locale, string $collection, string $version, string $path): View
    {
        $resolvedLocale = $this->resolveLocale($locale);
        $docVersion = $this->versionForSlug($collection, $version);
        abort_unless($docVersion instanceof CmsDocVersion, 404);

        $page = $this->publishedPages($docVersion, $resolvedLocale)
            ->where('path', trim($path, '/'))
            ->first();

        abort_unless($page instanceof CmsDocPage, 404);

        return $this->render($request, $resolvedLocale, $docVersion, $page);
    }

    private function render(Request $request, string $locale, CmsDocVersion $version, ?CmsDocPage $page): View
    {
        App::setLocale($locale);

        $rendered = $page instanceof CmsDocPage
            ? $this->markdownRenderer->render($page->body, $locale)
            : ['html' => '', 'toc' => [], 'plain_text' => ''];
        $collection = $version->collection;
        $title = $page instanceof CmsDocPage ? $page->title : $collection?->name;

        $templateKey = 'docs.detail';
        $template = $this->templateResolver->resolve($templateKey, $locale);

        abort_unless($template instanceof CmsTemplate, 404);

        $sitePayload = $this->sitePayload($request, $locale);
        $seo = $this->seoData->forDocumentation(
            title: (string) ($page?->seo_title ?: $title),
            description: $page?->seo_description,
            noindex: (bool) $page?->noindex,
            site: $sitePayload,
            request: $request,
        );
        $contentItem = [
            'id' => $page?->id ?? 'docs-version-'.$version->id,
            'title' => $title,
            'locale' => $locale,
            'template_class' => 'module',
            'template_key' => $templateKey,
            'settings' => [],
        ];
        $context = [
            'docs' => [
                'collection' => [
                    'id' => $collection?->id,
                    'name' => $collection?->name,
                    'slug' => $collection?->slug,
                    'description' => $collection?->description,
                ],
                'version' => [
                    'id' => $version->id,
                    'label' => $version->label,
                    'slug' => $version->slug,
                ],
                'page' => $page instanceof CmsDocPage ? [
                    'id' => $page->id,
                    'title' => $page->title,
                    'path' => $page->path,
                    'locale' => $page->locale,
                    'plain_text' => $rendered['plain_text'] ?? '',
                ] : null,
            ],
        ];
        $context['__template'] = [
            'template_class' => $template->template_class,
            'template_key' => $template->template_key,
            'locale' => $template->locale,
            'data_contract' => app(CmsTemplateDataContract::class)->normalize($template->data_contract, (string) $template->template_key),
        ];
        $composition = $this->templateCompositionBuilder->handle($template, $contentItem, $context);
        $translations = $page instanceof CmsDocPage ? $this->translationPayload($page) : [];

        return view($this->viewResolver->template(), [
            'pageItem' => $composition['page'],
            'composition' => $composition,
            'site' => $sitePayload,
            'navigation' => $this->navigationBuilder->handle($locale),
            'seo' => $seo,
            'collection' => $collection,
            'version' => $version,
            'versions' => $this->versionPayload($version),
            'docPage' => $page,
            'docNavigation' => $this->navigationPayload($version, $locale),
            'rendered' => $rendered,
            'translations' => $translations,
        ]);
    }

    private function docsModuleInstalled(): bool
    {
        return CmsModule::query()
            ->where('key', 'docs')
            ->where('status', 'active')
            ->exists();
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
            'logo_url' => $this->versionedPublicUrl(
                $this->settingValue('branding', 'logo_path'),
                $this->settingValue('branding', 'logo_version'),
            ),
            'logo_show_tagline' => (bool) $this->settingValue('branding', 'logo_show_tagline', false),
        ];
    }

    /**
     * @return array{favicon_32_url: ?string, favicon_192_url: ?string, apple_touch_icon_url: ?string}
     */
    private function faviconPayload(): array
    {
        $version = $this->settingValue('branding', 'favicon_version');

        return [
            'favicon_32_url' => $this->versionedPublicUrl($this->settingValue('branding', 'favicon_32_path'), $version),
            'favicon_192_url' => $this->versionedPublicUrl($this->settingValue('branding', 'favicon_192_path'), $version),
            'apple_touch_icon_url' => $this->versionedPublicUrl($this->settingValue('branding', 'apple_touch_icon_path'), $version),
        ];
    }

    private function versionedPublicUrl(mixed $path, mixed $version): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        return $this->storageUrl->versionedUrl($path, $version);
    }

    private function themeCssUrl(Request $request): ?string
    {
        if (! Schema::hasTable('cms_themes')) {
            return null;
        }

        if ($request->filled(['theme_preview', 'theme_version'])) {
            $theme = CmsTheme::query()->find((int) $request->query('theme_preview'));

            if ($theme instanceof CmsTheme) {
                return route('cms.theme.preview', [
                    'theme' => $theme->id,
                    'hash' => (string) $request->query('theme_version'),
                ]);
            }
        }

        $theme = CmsTheme::query()
            ->with('activeVersion')
            ->where('is_active', true)
            ->first();

        if (! $theme instanceof CmsTheme || ! $theme->activeVersion instanceof CmsThemeVersion) {
            return null;
        }

        return route('cms.theme.active', ['hash' => $theme->activeVersion->version_hash]);
    }

    private function settingValue(string $group, string $key, mixed $default = null, ?string $locale = null): mixed
    {
        $setting = CmsSetting::query()
            ->with('translations')
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if (! $setting instanceof CmsSetting) {
            return $default;
        }

        if ($locale !== null) {
            $translation = $setting->translations->firstWhere('locale', $locale)
                ?? $setting->translations->firstWhere('locale', $this->languageSettings->defaultLocale());
            $value = $translation?->value['value'] ?? null;

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $setting->value['value'] ?? $default;
    }

    private function collectionForSlug(string $slug): ?CmsDocCollection
    {
        if (! $this->docsModuleInstalled()) {
            return null;
        }

        return CmsDocCollection::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    private function versionForSlug(string $collectionSlug, string $versionSlug): ?CmsDocVersion
    {
        $collection = $this->collectionForSlug($collectionSlug);

        if (! $collection instanceof CmsDocCollection) {
            return null;
        }

        return $collection->versions()
            ->with('collection')
            ->where('slug', $versionSlug)
            ->where('is_active', true)
            ->first();
    }

    private function publishedPages(CmsDocVersion $version, string $locale): Builder
    {
        return CmsDocPage::query()
            ->where('cms_doc_version_id', $version->id)
            ->where('locale', $locale)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->orderBy('path');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function navigationPayload(CmsDocVersion $version, string $locale): array
    {
        $pages = $this->publishedPages($version, $locale)->get(['id', 'parent_id', 'title', 'path', 'sort_order']);

        return $this->tree($pages->whereNull('parent_id')->values(), $pages, $version, $locale);
    }

    /**
     * @param  Collection<int, CmsDocPage>  $roots
     * @param  Collection<int, CmsDocPage>  $pages
     * @return array<int, array<string, mixed>>
     */
    private function tree($roots, $pages, CmsDocVersion $version, string $locale): array
    {
        return $roots
            ->map(fn (CmsDocPage $page): array => [
                'id' => (int) $page->id,
                'title' => (string) $page->title,
                'path' => (string) $page->path,
                'url' => route('cms.public.docs.show', ['locale' => $locale, 'collection' => $version->collection?->slug, 'version' => $version->slug, 'path' => $page->path]),
                'children' => $this->tree($pages->where('parent_id', $page->id)->values(), $pages, $version, $locale),
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, slug: string, url: string, active: bool}>
     */
    private function versionPayload(CmsDocVersion $current): array
    {
        return CmsDocVersion::query()
            ->where('cms_doc_collection_id', $current->cms_doc_collection_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get(['label', 'slug'])
            ->map(fn (CmsDocVersion $version): array => [
                'label' => (string) $version->label,
                'slug' => (string) $version->slug,
                'url' => route('cms.public.docs.version', ['locale' => app()->getLocale(), 'collection' => $current->collection?->slug, 'version' => $version->slug]),
                'active' => $version->slug === $current->slug,
            ])
            ->all();
    }

    /**
     * @return array<int, array{locale: string, url: string}>
     */
    private function translationPayload(CmsDocPage $page): array
    {
        if (blank($page->translation_key)) {
            return [];
        }

        $page->loadMissing('version.collection');

        return CmsDocPage::query()
            ->where('translation_key', $page->translation_key)
            ->where('status', 'published')
            ->get(['locale', 'path'])
            ->map(fn (CmsDocPage $translation): array => [
                'locale' => (string) $translation->locale,
                'url' => route('cms.public.docs.show', [
                    'locale' => $translation->locale,
                    'collection' => $page->version->collection?->slug,
                    'version' => $page->version->slug,
                    'path' => $translation->path,
                ]),
            ])
            ->all();
    }

    private function resolveLocale(string $locale): string
    {
        $locales = $this->languageSettings->activeLocales();

        return in_array($locale, $locales, true) ? $locale : $this->languageSettings->defaultLocale();
    }
}
