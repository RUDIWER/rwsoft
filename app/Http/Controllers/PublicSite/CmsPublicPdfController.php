<?php

namespace App\Http\Controllers\PublicSite;

use App\Actions\PublicSite\Pdf\RenderCmsPublicPdfAction;
use App\Http\Controllers\Controller;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTag;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsPublicUrlBuilder;
use App\Support\PublicSite\Pdf\CmsPdfPayloadBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class CmsPublicPdfController extends Controller
{
    public function __construct(
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsPublicUrlBuilder $urlBuilder,
        private readonly CmsPdfPayloadBuilder $payloadBuilder,
        private readonly RenderCmsPublicPdfAction $renderPdf,
    ) {}

    public function home(Request $request): Response
    {
        return $this->renderPage($request, '', $this->defaultLocale());
    }

    public function localizedHome(Request $request, string $locale): Response
    {
        return $this->renderPage($request, '', $this->resolveLocale($locale));
    }

    public function page(Request $request, string $path): Response
    {
        return $this->renderPage($request, $path, $this->defaultLocale());
    }

    public function localizedPage(Request $request, string $locale, string $path): Response
    {
        return $this->renderPage($request, $path, $this->resolveLocale($locale));
    }

    public function post(Request $request, string $slug): Response
    {
        return $this->renderPost($request, $slug, $this->defaultLocale());
    }

    public function localizedPost(Request $request, string $locale, string $slug): Response
    {
        return $this->renderPost($request, $slug, $this->resolveLocale($locale));
    }

    public function categoryArchive(Request $request, string $path): Response
    {
        return $this->renderCategoryArchive($request, $path, $this->defaultLocale());
    }

    public function localizedCategoryArchive(Request $request, string $locale, string $path): Response
    {
        return $this->renderCategoryArchive($request, $path, $this->resolveLocale($locale));
    }

    public function categoryDetail(Request $request, string $path): Response
    {
        return $this->renderCategoryDetail($request, $path, $this->defaultLocale());
    }

    public function localizedCategoryDetail(Request $request, string $locale, string $path): Response
    {
        return $this->renderCategoryDetail($request, $path, $this->resolveLocale($locale));
    }

    public function tagArchive(Request $request, string $slug): Response
    {
        return $this->renderTagArchive($request, $slug, $this->defaultLocale());
    }

    public function localizedTagArchive(Request $request, string $locale, string $slug): Response
    {
        return $this->renderTagArchive($request, $slug, $this->resolveLocale($locale));
    }

    public function tagDetail(Request $request, string $slug): Response
    {
        return $this->renderTagDetail($request, $slug, $this->defaultLocale());
    }

    public function localizedTagDetail(Request $request, string $locale, string $slug): Response
    {
        return $this->renderTagDetail($request, $slug, $this->resolveLocale($locale));
    }

    private function renderPage(Request $request, string $path, string $locale): Response
    {
        App::setLocale($locale);

        $page = $this->pageForPath($path, $locale);

        abort_unless($page instanceof CmsPage, 404);
        abort_unless((bool) ($page->settings['pdf_download_enabled'] ?? false), 404);

        return $this->renderPdf->handle($this->payloadBuilder->page($page, $locale));
    }

    private function renderPost(Request $request, string $slug, string $locale): Response
    {
        App::setLocale($locale);

        $post = $this->publishedPostBaseQuery()
            ->with(['featuredMedia', 'categories', 'tags'])
            ->where('locale', $locale)
            ->where('slug', trim($slug, '/'))
            ->first();

        abort_unless($post instanceof CmsPost, 404);
        abort_unless((bool) ($post->settings['pdf_download_enabled'] ?? false), 404);

        return $this->renderPdf->handle($this->payloadBuilder->post($post, $locale));
    }

    private function renderCategoryArchive(Request $request, string $path, string $locale): Response
    {
        App::setLocale($locale);

        $category = $this->categoryForPath($path, $locale);

        abort_unless($category instanceof CmsCategory, 404);
        abort_unless((bool) ($category->settings['pdf_download_enabled'] ?? false), 404);

        $posts = $this->postsForCategoryIds($this->categoryAndDescendantIds($category, $locale), $locale);

        return $this->renderPdf->handle($this->payloadBuilder->categoryArchive($category, $posts, $locale));
    }

    private function renderCategoryDetail(Request $request, string $path, string $locale): Response
    {
        App::setLocale($locale);

        $category = $this->categoryForPath($path, $locale);

        abort_unless($category instanceof CmsCategory, 404);
        abort_unless((bool) ($category->settings['pdf_download_enabled'] ?? false), 404);
        abort_unless($category->landingPage instanceof CmsPage && $this->pageIsPublic($category->landingPage, $locale), 404);

        return $this->renderPdf->handle($this->payloadBuilder->categoryDetail($category, $locale));
    }

    private function renderTagArchive(Request $request, string $slug, string $locale): Response
    {
        App::setLocale($locale);

        $tag = $this->tagForSlug($slug, $locale);

        abort_unless($tag instanceof CmsTag, 404);
        abort_unless((bool) ($tag->settings['pdf_download_enabled'] ?? false), 404);

        $posts = $this->postsForTag($tag, $locale);

        return $this->renderPdf->handle($this->payloadBuilder->tagArchive($tag, $posts, $locale));
    }

    private function renderTagDetail(Request $request, string $slug, string $locale): Response
    {
        App::setLocale($locale);

        $tag = $this->tagForSlug($slug, $locale);

        abort_unless($tag instanceof CmsTag, 404);
        abort_unless((bool) ($tag->settings['pdf_download_enabled'] ?? false), 404);
        abort_unless($tag->landingPage instanceof CmsPage && $this->pageIsPublic($tag->landingPage, $locale), 404);

        return $this->renderPdf->handle($this->payloadBuilder->tagDetail($tag, $locale));
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
    private function publishedPostBaseQuery(): Builder
    {
        return CmsPost::query()
            ->where('status', 'published')
            ->where('noindex', false)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('canonical_url')
                    ->orWhere('canonical_url', '')
                    ->orWhere('canonical_url', 'like', request()->getSchemeAndHttpHost().'%')
                    ->orWhere('canonical_url', 'like', '/%');
            });
    }

    private function pageIsPublic(CmsPage $page, string $locale): bool
    {
        return $page->locale === $locale
            && $page->status === 'published'
            && ($page->published_at === null || $page->published_at->isPast());
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
                'landingPage:id,parent_id,title,slug,locale,status,is_home,published_at,content_blocks,short_description',
                'parent:id,parent_id,landing_page_id,type,title,slug,locale,description,is_active',
            ])
            ->get(['id', 'parent_id', 'landing_page_id', 'archive_template_id', 'detail_template_id', 'type', 'title', 'slug', 'locale', 'description', 'is_active', 'settings'])
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

    /**
     * @param  array<int, int>  $categoryIds
     * @return Collection<int, CmsPost>
     */
    private function postsForCategoryIds(array $categoryIds, string $locale): Collection
    {
        return $this->publishedPostBaseQuery()
            ->where('locale', $locale)
            ->with(['featuredMedia', 'categories'])
            ->whereHas('categories', fn (Builder $query): Builder => $query->whereIn('cms_categories.id', $categoryIds))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    private function tagForSlug(string $slug, string $locale): ?CmsTag
    {
        return CmsTag::query()
            ->with([
                'landingPage:id,parent_id,title,slug,locale,status,is_home,published_at,content_blocks,short_description',
            ])
            ->where('locale', $locale)
            ->where('is_active', true)
            ->where('slug', $slug)
            ->first(['id', 'landing_page_id', 'archive_template_id', 'detail_template_id', 'title', 'slug', 'locale', 'description', 'is_active', 'settings']);
    }

    /**
     * @return Collection<int, CmsPost>
     */
    private function postsForTag(CmsTag $tag, string $locale): Collection
    {
        return $this->publishedPostBaseQuery()
            ->where('locale', $locale)
            ->with(['featuredMedia', 'categories'])
            ->whereHas('tags', fn (Builder $query): Builder => $query->whereKey($tag->id))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
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
