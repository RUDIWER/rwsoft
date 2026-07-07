<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use App\Support\PublicSite\CmsBlockPayloadBuilder;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsNavigationBuilder;
use App\Support\PublicSite\CmsPublicTextResolver;
use App\Support\PublicSite\CmsPublicUrlBuilder;
use App\Support\PublicSite\CmsSeoData;
use App\Support\PublicSite\CmsTemplateCompositionBuilder;
use App\Support\PublicSite\CmsTemplateResolver;
use App\Support\PublicSite\PublicMediaUrl;
use App\Support\PublicSite\PublicStorageUrl;
use App\Support\PublicSite\PublicViewResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;

class CmsPublicTaxonomyController extends Controller
{
    public function __construct(
        private readonly CmsNavigationBuilder $navigationBuilder,
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsPublicTextResolver $publicTextResolver,
        private readonly CmsSeoData $seoData,
        private readonly CmsBlockPayloadBuilder $blockPayloadBuilder,
        private readonly CmsTemplateResolver $templateResolver,
        private readonly CmsTemplateCompositionBuilder $templateCompositionBuilder,
        private readonly PublicMediaUrl $mediaUrl,
        private readonly PublicViewResolver $viewResolver,
        private readonly CmsPublicUrlBuilder $urlBuilder,
        private readonly PublicStorageUrl $storageUrl,
    ) {}

    public function categoryIndex(Request $request): View
    {
        return $this->renderCategoryIndex($request, $this->defaultLocale());
    }

    public function localizedCategoryIndex(Request $request, string $locale): View
    {
        return $this->renderCategoryIndex($request, $this->resolveLocale($locale));
    }

    public function category(Request $request, string $path): View|RedirectResponse
    {
        return $this->renderCategory($request, $path, $this->defaultLocale());
    }

    public function localizedCategory(Request $request, string $locale, string $path): View|RedirectResponse
    {
        return $this->renderCategory($request, $path, $this->resolveLocale($locale));
    }

    public function localizedTag(Request $request, string $locale, string $slug): View|RedirectResponse
    {
        return $this->renderTag($request, $slug, $this->resolveLocale($locale));
    }

    private function renderCategory(Request $request, string $path, string $locale): View|RedirectResponse
    {
        App::setLocale($locale);

        $normalizedPath = trim($path, '/');

        if (str_ends_with($normalizedPath, '/info')) {
            return $this->renderCategoryDetail($request, substr($normalizedPath, 0, -5), $locale);
        }

        return $this->renderCategoryArchive($request, $normalizedPath, $locale);
    }

    private function renderCategoryArchive(Request $request, string $path, string $locale): View|RedirectResponse
    {
        $category = $this->categoryForPath($path, $locale);

        abort_unless($category instanceof CmsCategory, 404);

        $site = $this->sitePayload($locale);
        $categoryIds = $this->categoryAndDescendantIds($category, $locale);
        $posts = $this->postsForCategoryIds($categoryIds, $locale);
        $children = $this->childCategories($category, $locale);
        $template = $this->templateResolver->resolve('category.archive', $locale, $category);

        if ($template !== null) {
            return $this->renderTemplate(
                request: $request,
                template: $template,
                site: $site,
                contentItem: $this->categoryContentItem($category, 'category.archive'),
                context: [
                    'category' => array_merge($this->categoryPayload($category), [
                        'parent' => $category->parent instanceof CmsCategory ? $this->categoryPayload($category->parent) : null,
                        'children' => $children->map(fn (CmsCategory $child): array => $this->categoryPayload($child))->values()->all(),
                        'blogs' => $posts->map(fn (CmsPost $post): array => $this->postListItemPayload($post))->all(),
                    ]),
                ],
                contentSlots: $this->contentSlotsFor($category->landingPage),
                seo: $this->seoData->forCategory($category, $site, $request),
                locale: $locale,
            );
        }

        if ($category->landingPage instanceof CmsPage && $this->landingPageIsPublic($category->landingPage)) {
            $pages = CmsPage::query()
                ->get(['id', 'parent_id', 'slug', 'locale', 'is_home'])
                ->keyBy('id');

            return redirect()->to($this->urlBuilder->pagePath($category->landingPage, $pages), 301);
        }

        abort_if($posts->isEmpty(), 404);

        return view($this->viewResolver->postIndex(), [
            'posts' => $posts->map(fn (CmsPost $post): array => $this->postListItemPayload($post))->all(),
            'site' => $site,
            'navigation' => $this->navigationBuilder->handle($locale),
            'seo' => $this->seoData->forCategory($category, $site, $request),
            'postIndexTitle' => $category->title,
            'postIndexLead' => $category->description ?: public_text('taxonomy.category_lead', 'Blogs in deze categorie.', $locale),
        ]);
    }

    private function renderCategoryDetail(Request $request, string $path, string $locale): View|RedirectResponse
    {
        $category = $this->categoryForPath($path, $locale);

        abort_unless($category instanceof CmsCategory, 404);

        $site = $this->sitePayload($locale);
        $template = $this->templateResolver->resolve('category.detail', $locale, $category);

        abort_unless($template !== null, 404);

        return $this->renderTemplate(
            request: $request,
            template: $template,
            site: $site,
            contentItem: $this->categoryContentItem($category, 'category.detail'),
            context: [
                'category' => array_merge($this->categoryPayload($category), [
                    'forms' => [],
                ]),
            ],
            contentSlots: $this->contentSlotsFor($category->landingPage),
            seo: $this->seoData->forCategory($category, $site, $request),
            locale: $locale,
        );
    }

    public function tag(Request $request, string $slug): View|RedirectResponse
    {
        return $this->renderTag($request, $slug, $this->defaultLocale());
    }

    public function tagIndex(Request $request): View
    {
        return $this->renderTagIndex($request, $this->defaultLocale());
    }

    public function localizedTagIndex(Request $request, string $locale): View
    {
        return $this->renderTagIndex($request, $this->resolveLocale($locale));
    }

    public function tagDetail(Request $request, string $slug): View|RedirectResponse
    {
        return $this->renderTagDetail($request, $slug, $this->defaultLocale());
    }

    public function localizedTagDetail(Request $request, string $locale, string $slug): View|RedirectResponse
    {
        return $this->renderTagDetail($request, $slug, $this->resolveLocale($locale));
    }

    private function renderTag(Request $request, string $slug, string $locale): View|RedirectResponse
    {
        App::setLocale($locale);

        $tag = $this->tagForSlug($slug, $locale);

        abort_unless($tag instanceof CmsTag, 404);

        $site = $this->sitePayload($locale);
        $posts = $this->postsForTag($tag, $locale);
        $template = $this->templateResolver->resolve('tag.archive', $locale, $tag);

        if ($template !== null) {
            return $this->renderTemplate(
                request: $request,
                template: $template,
                site: $site,
                contentItem: $this->tagContentItem($tag, 'tag.archive'),
                context: [
                    'tag' => array_merge($this->tagPayload($tag), [
                        'blogs' => $posts->map(fn (CmsPost $post): array => $this->postListItemPayload($post, $tag))->all(),
                    ]),
                ],
                contentSlots: $this->contentSlotsFor($tag->landingPage),
                seo: $this->seoData->forTag($tag, $site, $request),
                locale: $locale,
            );
        }

        if ($tag->landingPage instanceof CmsPage && $this->landingPageIsPublic($tag->landingPage)) {
            $pages = CmsPage::query()
                ->get(['id', 'parent_id', 'slug', 'locale', 'is_home'])
                ->keyBy('id');

            return redirect()->to($this->urlBuilder->pagePath($tag->landingPage, $pages), 301);
        }

        abort_if($posts->isEmpty(), 404);

        return view($this->viewResolver->postIndex(), [
            'posts' => $posts->map(fn (CmsPost $post): array => $this->postListItemPayload($post, $tag))->all(),
            'site' => $site,
            'navigation' => $this->navigationBuilder->handle($locale),
            'seo' => $this->seoData->forTag($tag, $site, $request),
            'postIndexTitle' => '#'.$tag->title,
            'postIndexLead' => $tag->description ?: public_text('taxonomy.tag_lead', 'Blogs met deze tag.', $locale),
        ]);
    }

    private function renderCategoryIndex(Request $request, string $locale): View
    {
        App::setLocale($locale);

        $site = $this->sitePayload($locale);
        $categories = CmsCategory::query()
            ->where('type', 'post')
            ->where('locale', $locale)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'parent_id', 'type', 'title', 'slug', 'locale', 'description', 'is_active']);
        $categoryPayloads = $categories->map(fn (CmsCategory $category): array => $this->categoryPayload($category, $categories))->values()->all();
        $template = $this->templateResolver->resolve('category.index', $locale);

        if ($template !== null) {
            return $this->renderTemplate(
                request: $request,
                template: $template,
                site: $site,
                contentItem: [
                    'id' => 'category-index-'.$locale,
                    'title' => public_text('taxonomy.category_index_title', 'Categorieen', $locale),
                    'locale' => $locale,
                    'template_class' => 'category',
                    'template_key' => 'category.index',
                ],
                context: [
                    'category_index' => [
                        'title' => public_text('taxonomy.category_index_title', 'Categorieen', $locale),
                    ],
                    'categories' => $categoryPayloads,
                    'root_categories' => collect($categoryPayloads)->whereNull('parent_id')->values()->all(),
                    'category_count' => count($categoryPayloads),
                ],
                contentSlots: [],
                seo: $this->seoData->forPostIndex($site, $request),
                locale: $locale,
            );
        }

        return view($this->viewResolver->postIndex(), [
            'posts' => $categoryPayloads,
            'site' => $site,
            'navigation' => $this->navigationBuilder->handle($locale),
            'seo' => $this->seoData->forPostIndex($site, $request),
            'postIndexTitle' => public_text('taxonomy.category_index_title', 'Categorieen', $locale),
            'postIndexLead' => public_text('taxonomy.category_index_lead', 'Bekijk alle categorieen.', $locale),
        ]);
    }

    private function renderTagIndex(Request $request, string $locale): View
    {
        App::setLocale($locale);

        $site = $this->sitePayload($locale);
        $tagPayloads = CmsTag::query()
            ->where('locale', $locale)
            ->where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'locale', 'description', 'is_active'])
            ->map(fn (CmsTag $tag): array => $this->tagPayload($tag))
            ->values()
            ->all();
        $template = $this->templateResolver->resolve('tag.index', $locale);

        if ($template !== null) {
            return $this->renderTemplate(
                request: $request,
                template: $template,
                site: $site,
                contentItem: [
                    'id' => 'tag-index-'.$locale,
                    'title' => public_text('taxonomy.tag_index_title', 'Tags', $locale),
                    'locale' => $locale,
                    'template_class' => 'tag',
                    'template_key' => 'tag.index',
                ],
                context: [
                    'tag_index' => [
                        'title' => public_text('taxonomy.tag_index_title', 'Tags', $locale),
                    ],
                    'tags' => $tagPayloads,
                    'tag_count' => count($tagPayloads),
                ],
                contentSlots: [],
                seo: $this->seoData->forPostIndex($site, $request),
                locale: $locale,
            );
        }

        return view($this->viewResolver->postIndex(), [
            'posts' => $tagPayloads,
            'site' => $site,
            'navigation' => $this->navigationBuilder->handle($locale),
            'seo' => $this->seoData->forPostIndex($site, $request),
            'postIndexTitle' => public_text('taxonomy.tag_index_title', 'Tags', $locale),
            'postIndexLead' => public_text('taxonomy.tag_index_lead', 'Bekijk alle tags.', $locale),
        ]);
    }

    private function renderTagDetail(Request $request, string $slug, string $locale): View|RedirectResponse
    {
        App::setLocale($locale);

        $tag = $this->tagForSlug($slug, $locale);

        abort_unless($tag instanceof CmsTag, 404);

        $site = $this->sitePayload($locale);
        $template = $this->templateResolver->resolve('tag.detail', $locale, $tag);

        abort_unless($template !== null, 404);

        return $this->renderTemplate(
            request: $request,
            template: $template,
            site: $site,
            contentItem: $this->tagContentItem($tag, 'tag.detail'),
            context: [
                'tag' => array_merge($this->tagPayload($tag), [
                    'forms' => [],
                ]),
            ],
            contentSlots: $this->contentSlotsFor($tag->landingPage),
            seo: $this->seoData->forTag($tag, $site, $request),
            locale: $locale,
        );
    }

    private function categoryForPath(string $path, string $locale): ?CmsCategory
    {
        $segments = collect(explode('/', trim($path, '/')))
            ->filter(fn (string $segment): bool => $segment !== '')
            ->values();

        if ($segments->isEmpty()) {
            return null;
        }

        $categories = CmsCategory::query()
            ->where('type', 'post')
            ->where('locale', $locale)
            ->where('is_active', true)
            ->with([
                'archiveTemplate',
                'detailTemplate',
                'landingPage:id,parent_id,title,slug,locale,status,is_home,published_at,content_blocks',
                'parent:id,parent_id,landing_page_id,type,title,slug,locale,description,is_active',
            ])
            ->get(['id', 'parent_id', 'landing_page_id', 'archive_template_id', 'detail_template_id', 'type', 'title', 'slug', 'locale', 'description', 'is_active'])
            ->keyBy('id');
        $category = $categories->firstWhere('slug', $segments->last());

        if (! $category instanceof CmsCategory) {
            return null;
        }

        return $this->categoryAncestorsAreActive($category, $categories)
            && $this->urlBuilder->categoryRelativePath($category, $categories) === $segments->implode('/')
            ? $category
            : null;
    }

    /**
     * @param  Collection<int, CmsCategory>  $categories
     */
    private function categoryAncestorsAreActive(CmsCategory $category, Collection $categories): bool
    {
        $current = $category;
        $seen = [];

        while ($current->parent_id !== null) {
            if (in_array($current->id, $seen, true)) {
                return false;
            }

            $seen[] = $current->id;
            $current = $categories->get((int) $current->parent_id);

            if (! $current instanceof CmsCategory) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, int>
     */
    private function categoryAndDescendantIds(CmsCategory $category, string $locale): array
    {
        $categories = CmsCategory::query()
            ->where('type', 'post')
            ->where('locale', $locale)
            ->where('is_active', true)
            ->get(['id', 'parent_id'])
            ->keyBy('id');
        $ids = [(int) $category->id];
        $frontier = [(int) $category->id];

        while ($frontier !== []) {
            $children = $categories
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $frontier = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique(array_merge($ids, $frontier)));
        }

        return $ids;
    }

    private function landingPageIsPublic(CmsPage $page): bool
    {
        return $page->status === 'published'
            && ($page->published_at === null || $page->published_at->isPast());
    }

    /**
     * @return Builder<CmsPost>
     */
    private function publishedPostQuery(string $locale): Builder
    {
        return CmsPost::query()
            ->where('locale', $locale)
            ->where('status', 'published')
            ->where('noindex', false)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('canonical_url')->orWhere('canonical_url', '')->orWhere('canonical_url', 'like', request()->getSchemeAndHttpHost().'%')->orWhere('canonical_url', 'like', '/%');
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function postListItemPayload(CmsPost $post, ?CmsTag $activeTag = null): array
    {
        $taxonomyItems = $activeTag instanceof CmsTag
            ? [[
                'id' => $activeTag->id,
                'title' => $activeTag->title,
                'slug' => $activeTag->slug,
            ]]
            : $this->taxonomyPayload($post->categories);

        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'url' => $this->urlBuilder->postPath($post),
            'excerpt' => $post->excerpt,
            'published_at' => $post->published_at?->toDateString(),
            'featured_media' => $this->mediaUrl->payload($post->featuredMedia, $post->locale),
            'categories' => $activeTag instanceof CmsTag ? [] : $taxonomyItems,
            'taxonomy_items' => $taxonomyItems,
            'taxonomy_prefix' => $activeTag instanceof CmsTag ? '#' : '',
        ];
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function taxonomyPayload(Collection $items): array
    {
        return $items
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
     * @param  array<string, mixed>  $site
     * @param  array<string, mixed>  $contentItem
     * @param  array<string, mixed>  $context
     * @param  array<string, array<int, array<string, mixed>>>  $contentSlots
     * @param  array<string, mixed>  $seo
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
    ): View {
        $context['__template'] = [
            'template_class' => $template->template_class,
            'template_key' => $template->template_key,
            'locale' => $template->locale,
        ];
        $composition = $this->templateCompositionBuilder->handle($template, $contentItem, $context, $contentSlots);

        return view($this->viewResolver->template(), [
            'pageItem' => $composition['page'],
            'composition' => $composition,
            'site' => $site,
            'navigation' => $this->navigationBuilder->handle($locale),
            'translations' => [],
            'seo' => $seo,
        ]);
    }

    /**
     * @return Collection<int, CmsPost>
     */
    private function postsForCategoryIds(array $categoryIds, string $locale): Collection
    {
        return $this->publishedPostQuery($locale)
            ->with(['featuredMedia', 'categories'])
            ->whereHas('categories', fn (Builder $query): Builder => $query->whereIn('cms_categories.id', $categoryIds))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(24)
            ->get();
    }

    /**
     * @return Collection<int, CmsPost>
     */
    private function postsForTag(CmsTag $tag, string $locale): Collection
    {
        return $this->publishedPostQuery($locale)
            ->with(['featuredMedia'])
            ->whereHas('tags', fn (Builder $query): Builder => $query->whereKey($tag->id))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, CmsCategory>
     */
    private function childCategories(CmsCategory $category, string $locale): Collection
    {
        return CmsCategory::query()
            ->where('parent_id', $category->id)
            ->where('type', $category->type)
            ->where('locale', $locale)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'parent_id', 'landing_page_id', 'type', 'title', 'slug', 'locale', 'description', 'is_active']);
    }

    private function tagForSlug(string $slug, string $locale): ?CmsTag
    {
        return CmsTag::query()
            ->with([
                'archiveTemplate',
                'detailTemplate',
                'landingPage:id,parent_id,title,slug,locale,status,is_home,published_at,content_blocks',
            ])
            ->where('locale', $locale)
            ->where('is_active', true)
            ->where('slug', $slug)
            ->first();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function contentSlotsFor(?CmsPage $page): array
    {
        if (! $page instanceof CmsPage) {
            return ['content' => []];
        }

        return [
            'content' => $this->blockPayloadBuilder->handle($page->content_blocks ?? [], page: $page),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryContentItem(CmsCategory $category, string $templateKey): array
    {
        return [
            'id' => $category->id,
            'title' => $category->title,
            'slug' => $category->slug,
            'locale' => $category->locale,
            'excerpt' => $category->description,
            'template_class' => 'category',
            'template_key' => $templateKey,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tagContentItem(CmsTag $tag, string $templateKey): array
    {
        return [
            'id' => $tag->id,
            'title' => $tag->title,
            'slug' => $tag->slug,
            'locale' => $tag->locale,
            'excerpt' => $tag->description,
            'template_class' => 'tag',
            'template_key' => $templateKey,
        ];
    }

    /**
     * @param  Collection<int, CmsCategory>|null  $categories
     * @return array<string, mixed>
     */
    private function categoryPayload(CmsCategory $category, ?Collection $categories = null): array
    {
        $categories ??= CmsCategory::query()
            ->where('type', $category->type)
            ->where('locale', $category->locale)
            ->get(['id', 'parent_id', 'slug', 'locale'])
            ->keyBy('id');

        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'title' => $category->title,
            'slug' => $category->slug,
            'url' => $this->urlBuilder->categoryPath($category, $categories),
            'detail_url' => $this->urlBuilder->categoryDetailPath($category, $categories),
            'description' => $category->description,
            'excerpt' => $category->description,
            'published_at' => null,
            'featured_media' => null,
            'categories' => [],
            'taxonomy_items' => [],
            'taxonomy_prefix' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tagPayload(CmsTag $tag): array
    {
        return [
            'id' => $tag->id,
            'title' => $tag->title,
            'slug' => $tag->slug,
            'url' => $this->urlBuilder->tagPath($tag),
            'detail_url' => $this->urlBuilder->tagDetailPath($tag),
            'description' => $tag->description,
            'excerpt' => $tag->description,
            'published_at' => null,
            'featured_media' => null,
            'categories' => [],
            'taxonomy_items' => [[
                'id' => $tag->id,
                'title' => $tag->title,
                'slug' => $tag->slug,
            ]],
            'taxonomy_prefix' => '#',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sitePayload(string $locale): array
    {
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
            'active_theme_css_url' => $this->themeCssUrl(),
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

    private function themeCssUrl(): ?string
    {
        if (! Schema::hasTable('cms_themes')) {
            return null;
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
