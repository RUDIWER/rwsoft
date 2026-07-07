<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTag;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CmsSitemapBuilder
{
    public function __construct(
        private readonly CmsPublicUrlBuilder $urlBuilder,
        private readonly CmsLanguageSettings $languageSettings,
    ) {}

    /**
     * @return array<int, array{type: string, loc: string, lastmod: string|null}>
     */
    public function index(): array
    {
        if ($this->globalNoindex()) {
            return [];
        }

        return collect(['pages', 'posts', 'categories', 'tags'])
            ->map(fn (string $type): array => [
                'type' => $type,
                'loc' => $this->urlBuilder->sitemapUrl($type),
                'lastmod' => $this->lastmodFor($this->entries($type)),
            ])
            ->filter(fn (array $sitemap): bool => $sitemap['lastmod'] !== null)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{loc: string, lastmod: string}>
     */
    public function entries(string $type): array
    {
        if ($this->globalNoindex()) {
            return [];
        }

        return match ($type) {
            'pages' => $this->pageEntries(),
            'posts' => $this->postEntries(),
            'categories' => $this->categoryEntries(),
            'tags' => $this->tagEntries(),
            default => [],
        };
    }

    public function sitemapIndexXml(): string
    {
        $items = collect($this->index())
            ->map(fn (array $sitemap): string => implode('', [
                '<sitemap>',
                '<loc>'.$this->escapeXml($sitemap['loc']).'</loc>',
                '<lastmod>'.$this->escapeXml((string) $sitemap['lastmod']).'</lastmod>',
                '</sitemap>',
            ]))
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .$items
            .'</sitemapindex>';
    }

    public function urlSetXml(string $type): string
    {
        $items = collect($this->entries($type))
            ->map(fn (array $entry): string => implode('', [
                '<url>',
                '<loc>'.$this->escapeXml($entry['loc']).'</loc>',
                '<lastmod>'.$this->escapeXml($entry['lastmod']).'</lastmod>',
                '</url>',
            ]))
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .$items
            .'</urlset>';
    }

    /**
     * @return array<int, array{loc: string, lastmod: string}>
     */
    private function pageEntries(): array
    {
        $pages = $this->publicPageQuery()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'slug', 'locale', 'status', 'noindex', 'is_home', 'sort_order', 'published_at', 'canonical_url', 'updated_at'])
            ->keyBy('id');

        return $pages
            ->filter(fn (CmsPage $page): bool => $this->pageAncestorsArePublic($page, $pages))
            ->map(fn (CmsPage $page): array => [
                'loc' => $this->urlBuilder->pageUrl($page, $pages),
                'lastmod' => $this->formatLastmod($page->updated_at),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{loc: string, lastmod: string}>
     */
    private function postEntries(): array
    {
        $posts = $this->publicPostQuery()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['id', 'slug', 'locale', 'status', 'noindex', 'published_at', 'canonical_url', 'updated_at']);

        $entries = [];

        foreach ($posts->groupBy('locale') as $locale => $localePosts) {
            $entries[] = [
                'loc' => url($this->urlBuilder->postIndexPath((string) $locale)),
                'lastmod' => $this->lastmodFor($localePosts->map(fn (CmsPost $post): array => [
                    'lastmod' => $this->formatLastmod($post->updated_at),
                ])->all()) ?? now()->toAtomString(),
            ];
        }

        foreach ($posts as $post) {
            $entries[] = [
                'loc' => $this->urlBuilder->postUrl($post),
                'lastmod' => $this->formatLastmod($post->updated_at),
            ];
        }

        return $entries;
    }

    /**
     * @return array<int, array{loc: string, lastmod: string}>
     */
    private function categoryEntries(): array
    {
        $categories = $this->activeCategoryQuery()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'parent_id', 'type', 'title', 'slug', 'locale', 'is_active', 'sort_order', 'updated_at'])
            ->keyBy('id');
        $publicPosts = $this->publicPostQuery()->with('categories:id')->get(['id', 'updated_at']);
        $postLastmodsByCategory = $this->postLastmodsByCategory($publicPosts);

        return $categories
            ->filter(fn (CmsCategory $category): bool => $this->categoryAncestorsAreActive($category, $categories))
            ->filter(fn (CmsCategory $category): bool => $this->categoryHasPublicPosts($category, $categories, $postLastmodsByCategory))
            ->map(function (CmsCategory $category) use ($categories, $postLastmodsByCategory): array {
                $lastmod = collect($this->categoryAndDescendantIds($category, $categories))
                    ->map(fn (int $categoryId): ?CarbonInterface => $postLastmodsByCategory[$categoryId] ?? null)
                    ->filter()
                    ->push($category->updated_at)
                    ->max();

                return [
                    'loc' => $this->urlBuilder->categoryUrl($category, $categories),
                    'lastmod' => $this->formatLastmod($lastmod),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{loc: string, lastmod: string}>
     */
    private function tagEntries(): array
    {
        $tags = CmsTag::query()
            ->where('is_active', true)
            ->whereIn('locale', $this->publicLocales())
            ->whereHas('posts', fn (Builder $query): Builder => $this->applyPublicPostFilters($query))
            ->withMax(['posts as public_posts_max_updated_at' => fn (Builder $query): Builder => $this->applyPublicPostFilters($query)], 'updated_at')
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'locale', 'is_active', 'updated_at']);

        return $tags
            ->map(function (CmsTag $tag): array {
                $postLastmod = $tag->public_posts_max_updated_at instanceof CarbonInterface
                    ? $tag->public_posts_max_updated_at
                    : null;
                $lastmod = collect([$tag->updated_at, $postLastmod])->filter()->max();

                return [
                    'loc' => $this->urlBuilder->tagUrl($tag),
                    'lastmod' => $this->formatLastmod($lastmod),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{lastmod: string}>  $entries
     */
    private function lastmodFor(array $entries): ?string
    {
        return collect($entries)->pluck('lastmod')->filter()->max();
    }

    /**
     * @return Builder<CmsPage>
     */
    private function publicPageQuery(): Builder
    {
        return CmsPage::query()
            ->where('status', 'published')
            ->when(! $this->languageSettings->multilingualEnabled(), fn (Builder $query): Builder => $query->where('locale', $this->defaultLocale()))
            ->where('noindex', false)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('canonical_url')->orWhere('canonical_url', '')->orWhere('canonical_url', 'like', request()->getSchemeAndHttpHost().'%')->orWhere('canonical_url', 'like', '/%');
            });
    }

    /**
     * @return Builder<CmsPost>
     */
    private function publicPostQuery(): Builder
    {
        return CmsPost::query()->where(fn (Builder $query): Builder => $this->applyPublicPostFilters($query));
    }

    /**
     * @param  Builder<CmsPost>  $query
     * @return Builder<CmsPost>
     */
    private function applyPublicPostFilters(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->when(! $this->languageSettings->multilingualEnabled(), fn (Builder $query): Builder => $query->where('locale', $this->defaultLocale()))
            ->where('noindex', false)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('canonical_url')->orWhere('canonical_url', '')->orWhere('canonical_url', 'like', request()->getSchemeAndHttpHost().'%')->orWhere('canonical_url', 'like', '/%');
            });
    }

    /**
     * @return Builder<CmsCategory>
     */
    private function activeCategoryQuery(): Builder
    {
        return CmsCategory::query()
            ->where('type', 'post')
            ->when(! $this->languageSettings->multilingualEnabled(), fn (Builder $query): Builder => $query->where('locale', $this->defaultLocale()))
            ->where('is_active', true);
    }

    /**
     * @param  Collection<int, CmsPage>  $pages
     */
    private function pageAncestorsArePublic(CmsPage $page, Collection $pages): bool
    {
        $current = $page;
        $seen = [];

        while ($current->parent_id !== null) {
            if (in_array($current->id, $seen, true)) {
                return false;
            }

            $seen[] = $current->id;
            $current = $pages->get((int) $current->parent_id);

            if (! $current instanceof CmsPage) {
                return false;
            }
        }

        return true;
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
     * @param  Collection<int, CmsCategory>  $categories
     * @param  array<int, CarbonInterface>  $postLastmodsByCategory
     */
    private function categoryHasPublicPosts(CmsCategory $category, Collection $categories, array $postLastmodsByCategory): bool
    {
        return collect($this->categoryAndDescendantIds($category, $categories))
            ->contains(fn (int $categoryId): bool => array_key_exists($categoryId, $postLastmodsByCategory));
    }

    /**
     * @param  Collection<int, CmsCategory>  $categories
     * @return array<int, int>
     */
    private function categoryAndDescendantIds(CmsCategory $category, Collection $categories): array
    {
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
     * @param  Collection<int, CmsPost>  $posts
     * @return array<int, CarbonInterface>
     */
    private function postLastmodsByCategory(Collection $posts): array
    {
        $lastmods = [];

        foreach ($posts as $post) {
            foreach ($post->categories as $category) {
                $categoryId = (int) $category->id;

                if (! isset($lastmods[$categoryId]) || $post->updated_at->greaterThan($lastmods[$categoryId])) {
                    $lastmods[$categoryId] = $post->updated_at;
                }
            }
        }

        return $lastmods;
    }

    private function defaultLocale(): string
    {
        return $this->languageSettings->defaultLocale();
    }

    private function globalNoindex(): bool
    {
        return (bool) $this->settingValue('seo', 'global_noindex', false);
    }

    private function settingValue(string $group, string $key, mixed $default = null): mixed
    {
        $setting = CmsSetting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if (! $setting instanceof CmsSetting) {
            return $default;
        }

        return $setting->value['value'] ?? $default;
    }

    private function formatLastmod(mixed $date): string
    {
        return $date instanceof CarbonInterface ? $date->toAtomString() : now()->toAtomString();
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
