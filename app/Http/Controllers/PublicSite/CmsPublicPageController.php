<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsRedirect;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use App\Support\Cms\CmsTemplateDataContract;
use App\Support\PublicSite\CmsBlockPayloadBuilder;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsNavigationBuilder;
use App\Support\PublicSite\CmsPageCompositionBuilder;
use App\Support\PublicSite\CmsPublicTextResolver;
use App\Support\PublicSite\CmsPublicUrlBuilder;
use App\Support\PublicSite\CmsSeoData;
use App\Support\PublicSite\CmsTemplateCompositionBuilder;
use App\Support\PublicSite\CmsTemplateResolver;
use App\Support\PublicSite\PublicMediaUrl;
use App\Support\PublicSite\PublicSiteLocaleDetector;
use App\Support\PublicSite\PublicStorageUrl;
use App\Support\PublicSite\PublicViewResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;

class CmsPublicPageController extends Controller
{
    public function __construct(
        private readonly CmsBlockPayloadBuilder $blockPayloadBuilder,
        private readonly CmsPageCompositionBuilder $pageCompositionBuilder,
        private readonly CmsNavigationBuilder $navigationBuilder,
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsPublicTextResolver $publicTextResolver,
        private readonly CmsSeoData $seoData,
        private readonly CmsTemplateResolver $templateResolver,
        private readonly CmsTemplateCompositionBuilder $templateCompositionBuilder,
        private readonly CmsPublicUrlBuilder $urlBuilder,
        private readonly PublicSiteLocaleDetector $localeDetector,
        private readonly PublicMediaUrl $mediaUrl,
        private readonly PublicStorageUrl $storageUrl,
        private readonly PublicViewResolver $viewResolver,
    ) {}

    public function home(Request $request): View|RedirectResponse
    {
        return $this->renderHome($request, $this->defaultLocale());
    }

    public function localizedHome(Request $request, string $locale): View|RedirectResponse
    {
        return $this->renderHome($request, $this->resolveLocale($locale));
    }

    public function localizedShow(Request $request, string $locale, string $slug): View|RedirectResponse
    {
        return $this->renderPath($request, $slug, $this->resolveLocale($locale));
    }

    public function localizedShowPath(Request $request, string $locale, string $path): View|RedirectResponse
    {
        return $this->renderPath($request, $path, $this->resolveLocale($locale));
    }

    public function localizedPosts(Request $request, string $locale): View|RedirectResponse
    {
        return $this->renderPosts($request, $this->resolveLocale($locale));
    }

    public function localizedPost(Request $request, string $locale, string $slug): View|RedirectResponse
    {
        return $this->renderPostPath($request, $slug, $this->resolveLocale($locale));
    }

    private function renderHome(Request $request, string $locale): View|RedirectResponse
    {
        App::setLocale($locale);

        if ($redirect = $this->activeRedirect('/', $locale)) {
            return $this->redirectToTarget($redirect);
        }

        if ($targetLocale = $this->localeDetector->redirectLocale($request, $locale)) {
            $targetPage = $this->homepage($targetLocale);

            if ($targetPage instanceof CmsPage) {
                return redirect()->to($this->localizedPagePath($targetPage));
            }
        }

        $page = $this->homepage($locale);

        abort_unless($page instanceof CmsPage, 404);

        return $this->renderPage($page, $request, $locale);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        return $this->showPath($request, $slug);
    }

    public function showPath(Request $request, string $path): View|RedirectResponse
    {
        return $this->renderPath($request, $path, $this->defaultLocale());
    }

    private function renderPath(Request $request, string $path, string $locale): View|RedirectResponse
    {
        App::setLocale($locale);

        $sourcePath = '/'.trim($path, '/');

        if ($redirect = $this->activeRedirect($sourcePath, $locale)) {
            return $this->redirectToTarget($redirect);
        }

        $page = $this->pageForPath($path, $locale);

        abort_unless($page instanceof CmsPage, 404);

        if ($targetLocale = $this->localeDetector->redirectLocale($request, $locale)) {
            $targetPage = $this->pageTranslationForLocale($page, $targetLocale);

            if ($targetPage instanceof CmsPage) {
                return redirect()->to($this->localizedPagePath($targetPage));
            }
        }

        return $this->renderPage($page, $request, $locale);
    }

    public function posts(Request $request): View|RedirectResponse
    {
        return $this->renderPosts($request, $this->defaultLocale());
    }

    private function renderPosts(Request $request, string $locale): View|RedirectResponse
    {
        App::setLocale($locale);

        if ($targetLocale = $this->localeDetector->redirectLocale($request, $locale)) {
            return redirect()->to($this->languageSettings->pathPrefix($targetLocale).'/'.trim($request->path(), '/'));
        }

        $site = $this->sitePayload($request, $locale);
        $postRows = $this->publishedPostQuery()
            ->with(['featuredMedia', 'categories'])
            ->where('locale', $locale)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(24)
            ->get();
        $posts = $postRows
            ->map(fn (CmsPost $post): array => $this->postListItemPayload($post))
            ->all();
        $template = $this->templateResolver->resolve('blog.index', $locale);

        if ($template instanceof CmsTemplate) {
            return $this->renderTemplate(
                request: $request,
                template: $template,
                site: $site,
                contentItem: [
                    'id' => 'blog-index-'.$locale,
                    'title' => public_text('post_index.title', 'Blogs', $locale),
                    'locale' => $locale,
                    'template_class' => 'blog',
                    'template_key' => 'blog.index',
                ],
                context: [
                    'blog_index' => [
                        'title' => public_text('post_index.title', 'Blogs', $locale),
                        'lead' => public_text('post_index.lead', 'Laatste gepubliceerde blogs en updates.', $locale),
                    ],
                    'blogs' => $posts,
                    'categories' => $this->activeCategoryPayloads($locale),
                    'tags' => $this->activeTagPayloads($locale),
                ],
                contentSlots: [],
                seo: $this->seoData->forPostIndex($site, $request),
                locale: $locale,
            );
        }

        $page = $this->pageForPath('blogs', $locale) ?? $this->pageForPath('posts', $locale);

        if ($page instanceof CmsPage) {
            return $this->renderPage($page, $request, $locale);
        }

        return view($this->viewResolver->postIndex(), [
            'posts' => $posts,
            'site' => $site,
            'navigation' => $this->navigationBuilder->handle($locale),
            'seo' => $this->seoData->forPostIndex($site, $request),
            'postIndexTitle' => public_text('post_index.title', 'Blogs', $locale),
            'postIndexLead' => public_text('post_index.lead', 'Laatste gepubliceerde blogs en updates.', $locale),
        ]);
    }

    public function post(Request $request, string $slug): View|RedirectResponse
    {
        return $this->renderPostPath($request, $slug, $this->defaultLocale());
    }

    private function renderPostPath(Request $request, string $slug, string $locale): View|RedirectResponse
    {
        App::setLocale($locale);

        $sourcePath = '/blogs/'.trim($slug, '/');

        if ($redirect = $this->activeRedirect($sourcePath, $locale)) {
            return $this->redirectToTarget($redirect);
        }

        $post = $this->publishedPostQuery()
            ->with(['author', 'featuredMedia', 'categories', 'tags'])
            ->where('locale', $locale)
            ->where('slug', trim($slug, '/'))
            ->first();

        abort_unless($post instanceof CmsPost, 404);

        if ($targetLocale = $this->localeDetector->redirectLocale($request, $locale)) {
            $targetPost = $this->postTranslationForLocale($post, $targetLocale);

            if ($targetPost instanceof CmsPost) {
                return redirect()->to($this->languageSettings->pathPrefix($targetPost->locale).'/blogs/'.$targetPost->slug);
            }
        }

        return $this->renderPost($post, $request, $locale);
    }

    private function renderPage(CmsPage $page, Request $request, string $locale): View
    {
        $site = $this->sitePayload($request, $locale);
        $template = $this->templateResolver->resolve('page.detail', $locale, $page);

        abort_unless($template instanceof CmsTemplate, 404);

        $composition = $this->pageCompositionBuilder->handle($page);
        $pageItem = array_merge($composition['page'], [
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'template_data' => $page->template_data ?? [],
        ]);
        $contentSections = $composition['sections']['content'] ?? [];

        return $this->renderTemplate(
            request: $request,
            template: $template,
            site: $site,
            contentItem: $pageItem,
            context: [
                'page' => array_merge($composition['page'], [
                    'content' => $contentSections,
                ]),
                'template' => [],
            ],
            contentSlots: [
                'content' => [
                    'sections' => $contentSections,
                ],
            ],
            seo: $this->seoData->forPage($page, $site, $request),
            locale: $locale,
            translations: $this->pageTranslations($page),
            page: $page,
        );
    }

    private function renderPost(CmsPost $post, Request $request, string $locale): View
    {
        $site = $this->sitePayload($request, $locale);
        $featuredMedia = $this->mediaUrl->payload($post->featuredMedia, $locale);
        $postItem = [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'locale' => $post->locale,
            'excerpt' => $post->excerpt,
            'published_at' => $post->published_at?->toDateString(),
            'featured_media' => $featuredMedia,
            'author' => [
                'name' => $post->author?->name,
            ],
            'categories' => $this->taxonomyPayload($post->categories),
            'tags' => $this->tagPayload($post->tags),
            'template_class' => 'blog',
            'template_key' => 'blog.detail',
        ];
        $blocks = $this->blockPayloadBuilder->handle($post->content_blocks ?? [], post: $post);
        $template = $this->templateResolver->resolve('blog.detail', $locale, $post);

        if ($template instanceof CmsTemplate) {
            return $this->renderTemplate(
                request: $request,
                template: $template,
                site: $site,
                contentItem: $postItem,
                context: [
                    'blog' => array_merge($postItem, [
                        'content' => $blocks,
                    ]),
                ],
                contentSlots: [
                    'content' => $blocks,
                ],
                seo: $this->seoData->forPost($post, $site, $request, $featuredMedia),
                locale: $locale,
                translations: $this->postTranslations($post),
                post: $post,
            );
        }

        return view($this->viewResolver->post(), [
            'postItem' => $postItem,
            'blocks' => $blocks,
            'site' => $site,
            'navigation' => $this->navigationBuilder->handle($locale),
            'translations' => $this->postTranslations($post),
            'seo' => $this->seoData->forPost($post, $site, $request, $featuredMedia),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function postListItemPayload(CmsPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'url' => $this->urlBuilder->postPath($post),
            'excerpt' => $post->excerpt,
            'published_at' => $post->published_at?->toDateString(),
            'featured_media' => $this->mediaUrl->payload($post->featuredMedia, $post->locale),
            'categories' => $this->taxonomyPayload($post->categories),
        ];
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function taxonomyPayload(iterable $items): array
    {
        return collect($items)
            ->where('is_active', true)
            ->values()
            ->map(fn ($item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
            ])
            ->all();
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function tagPayload(iterable $items): array
    {
        return collect($items)
            ->where('is_active', true)
            ->values()
            ->map(fn ($item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'url' => $this->urlBuilder->tagPath($item),
            ])
            ->all();
    }

    private function pageForPath(string $path, string $locale): ?CmsPage
    {
        $segments = collect(explode('/', trim($path, '/')))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->values();

        if ($segments->isEmpty()) {
            return $this->homepage($locale);
        }

        $page = $this->publishedPageQuery()
            ->where('locale', $locale)
            ->where('slug', $segments->last())
            ->first();

        if (! $page instanceof CmsPage) {
            return null;
        }

        return $this->pagePathSegments($page, $locale) === $segments->all() ? $page : null;
    }

    /**
     * @return array<int, string>
     */
    private function pagePathSegments(CmsPage $page, string $locale): array
    {
        $segments = [];
        $current = $page;

        while ($current instanceof CmsPage) {
            if (! $this->pageIsPublic($current, $locale)) {
                return [];
            }

            if (! $current->is_home) {
                array_unshift($segments, $current->slug);
            }

            if ($current->parent_id === null) {
                break;
            }

            $current = CmsPage::query()
                ->select(['id', 'parent_id', 'slug', 'locale', 'status', 'is_home', 'published_at'])
                ->whereKey($current->parent_id)
                ->first();
        }

        return $segments;
    }

    private function pageIsPublic(CmsPage $page, string $locale): bool
    {
        return $page->locale === $locale
            && $page->status === 'published'
            && ($page->published_at === null || $page->published_at->isPast());
    }

    private function homepage(string $locale): ?CmsPage
    {
        $homepageId = $this->settingValue('general', 'homepage_id');

        if ($homepageId) {
            $page = $this->publishedPageQuery()
                ->whereKey($homepageId)
                ->where('locale', $locale)
                ->first();

            if ($page instanceof CmsPage) {
                return $page;
            }
        }

        return $this->publishedPageQuery()
            ->where('locale', $locale)
            ->where('is_home', true)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * @return Builder<CmsPage>
     */
    private function publishedPageQuery(): Builder
    {
        return CmsPage::query()
            ->where('status', 'published')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    /**
     * @return Builder<CmsPost>
     */
    private function publishedPostQuery(): Builder
    {
        return CmsPost::query()
            ->where('status', 'published')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    private function activeRedirect(string $sourcePath, string $locale): ?CmsRedirect
    {
        return CmsRedirect::query()
            ->where('source_path', $sourcePath)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($locale): void {
                $query
                    ->whereNull('locale')
                    ->orWhere('locale', $locale);
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderByRaw('locale is null')
            ->first();
    }

    private function redirectToTarget(CmsRedirect $redirect): RedirectResponse
    {
        $redirect->forceFill([
            'hit_count' => ((int) $redirect->hit_count) + 1,
            'last_hit_at' => now(),
        ])->save();

        return redirect()->to($redirect->target_url, (int) $redirect->status_code);
    }

    /**
     * @return array<string, mixed>
     */
    private function sitePayload(?Request $request = null, ?string $locale = null): array
    {
        $locale ??= $this->defaultLocale();

        return [
            'name' => $this->settingValue('general', 'site_name', config('app.name', 'RwSoft'), $locale),
            'tagline' => $this->settingValue('general', 'site_tagline', null, $locale),
            'default_locale' => $this->defaultLocale(),
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

    private function themeCssUrl(?Request $request): ?string
    {
        if (! Schema::hasTable('cms_themes')) {
            return null;
        }

        if ($request instanceof Request && $request->filled(['theme_preview', 'theme_version'])) {
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

    private function defaultLocale(): string
    {
        return $this->languageSettings->defaultLocale();
    }

    private function resolveLocale(string $locale): string
    {
        abort_unless($this->languageSettings->multilingualEnabled(), 404);

        abort_unless(in_array($locale, $this->languageSettings->activeLocales(), true), 404);

        return $locale;
    }

    /**
     * @return array<int, array{locale: string, label: string, url: string, active: bool}>
     */
    private function pageTranslations(CmsPage $page): array
    {
        if (blank($page->translation_key)) {
            return [];
        }

        if (! $this->languageSettings->multilingualEnabled()) {
            return [];
        }

        $pages = CmsPage::query()
            ->where('translation_key', $page->translation_key)
            ->whereIn('locale', $this->languageSettings->activeLocales())
            ->where('status', 'published')
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->get(['id', 'parent_id', 'title', 'slug', 'locale', 'status', 'is_home', 'published_at'])
            ->keyBy('id');

        return $pages
            ->filter(fn (CmsPage $translation): bool => $translation->is_home || $this->pagePathSegments($translation, (string) $translation->locale) !== [])
            ->map(fn (CmsPage $translation): array => [
                'locale' => (string) $translation->locale,
                'label' => $this->localeLabel((string) $translation->locale),
                'url' => $this->localizedPagePath($translation),
                'active' => $translation->is($page),
            ])
            ->sortBy('locale')
            ->values()
            ->all();
    }

    private function pageTranslationForLocale(CmsPage $page, string $locale): ?CmsPage
    {
        if (blank($page->translation_key)) {
            return null;
        }

        return CmsPage::query()
            ->where('translation_key', $page->translation_key)
            ->where('locale', $locale)
            ->where('status', 'published')
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->first(['id', 'parent_id', 'title', 'slug', 'locale', 'status', 'is_home', 'published_at']);
    }

    /**
     * @param  array<string, mixed>  $site
     * @param  array<string, mixed>  $contentItem
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $contentSlots
     * @param  array<string, mixed>  $seo
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function renderTemplate(
        Request $request,
        CmsTemplate $template,
        array $site,
        array $contentItem,
        array $context,
        array $contentSlots,
        array $seo,
        string $locale,
        array $translations = [],
        ?CmsPage $page = null,
        ?CmsPost $post = null,
    ): View {
        $context['__template'] = [
            'template_class' => $template->template_class,
            'template_key' => $template->template_key,
            'locale' => $template->locale,
            'data_contract' => app(CmsTemplateDataContract::class)->normalize($template->data_contract, (string) $template->template_key),
        ];
        $composition = $this->templateCompositionBuilder->handle($template, $contentItem, $context, $contentSlots, $page, $post);

        return view($this->viewResolver->template(), [
            'pageItem' => $composition['page'],
            'composition' => $composition,
            'site' => $site,
            'navigation' => $this->navigationBuilder->handle($locale),
            'translations' => $translations,
            'seo' => $seo,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeCategoryPayloads(string $locale): array
    {
        $categories = CmsCategory::query()
            ->where('type', 'post')
            ->where('locale', $locale)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'parent_id', 'type', 'title', 'slug', 'locale', 'description', 'is_active'])
            ->keyBy('id');

        return $categories
            ->map(fn (CmsCategory $category): array => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'title' => $category->title,
                'slug' => $category->slug,
                'url' => $this->urlBuilder->categoryPath($category, $categories),
                'description' => $category->description,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeTagPayloads(string $locale): array
    {
        return CmsTag::query()
            ->where('locale', $locale)
            ->where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'locale', 'description', 'is_active'])
            ->map(fn (CmsTag $tag): array => [
                'id' => $tag->id,
                'title' => $tag->title,
                'slug' => $tag->slug,
                'url' => $this->urlBuilder->tagPath($tag),
                'description' => $tag->description,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{locale: string, label: string, url: string, active: bool}>
     */
    private function postTranslations(CmsPost $post): array
    {
        if (blank($post->translation_key)) {
            return [];
        }

        if (! $this->languageSettings->multilingualEnabled()) {
            return [];
        }

        return CmsPost::query()
            ->where('translation_key', $post->translation_key)
            ->whereIn('locale', $this->languageSettings->activeLocales())
            ->where('status', 'published')
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->get(['id', 'title', 'slug', 'locale', 'status', 'published_at'])
            ->map(fn (CmsPost $translation): array => [
                'locale' => (string) $translation->locale,
                'label' => $this->localeLabel((string) $translation->locale),
                'url' => $this->languageSettings->pathPrefix($translation->locale).'/blogs/'.$translation->slug,
                'active' => $translation->is($post),
            ])
            ->sortBy('locale')
            ->values()
            ->all();
    }

    private function postTranslationForLocale(CmsPost $post, string $locale): ?CmsPost
    {
        if (blank($post->translation_key)) {
            return null;
        }

        return CmsPost::query()
            ->where('translation_key', $post->translation_key)
            ->where('locale', $locale)
            ->where('status', 'published')
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->first(['id', 'title', 'slug', 'locale', 'status', 'published_at']);
    }

    private function localizedPagePath(CmsPage $page): string
    {
        if ($page->is_home) {
            return $this->languageSettings->pathPrefix($page->locale) ?: '/';
        }

        $segments = [];
        $current = $page;

        while ($current instanceof CmsPage) {
            if (! $current->is_home) {
                array_unshift($segments, $current->slug);
            }

            if ($current->parent_id === null) {
                break;
            }

            $current = CmsPage::query()
                ->select(['id', 'parent_id', 'slug', 'locale', 'status', 'is_home', 'published_at'])
                ->whereKey($current->parent_id)
                ->first();
        }

        return $this->languageSettings->pathPrefix($page->locale).'/'.implode('/', $segments);
    }

    private function localeLabel(string $locale): string
    {
        $language = collect($this->languageSettings->languages(true))->firstWhere('locale', $locale);

        return is_array($language) ? (string) $language['native_name'] : strtoupper($locale);
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
                ?? $setting->translations->firstWhere('locale', $this->defaultLocale());
            $value = $translation?->value['value'] ?? null;

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $setting->value['value'] ?? $default;
    }
}
