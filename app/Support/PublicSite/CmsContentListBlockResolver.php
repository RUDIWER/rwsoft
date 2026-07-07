<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CmsContentListBlockResolver
{
    public function __construct(
        private readonly CmsPublicUrlBuilder $urlBuilder,
        private readonly PublicMediaUrl $mediaUrl,
    ) {}

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    public function handle(array $block, ?CmsPage $page = null): array
    {
        $sourceType = (string) ($block['source_type'] ?? 'category');
        $isTagSource = $sourceType === 'tag';
        $category = $isTagSource ? null : $this->categoryContext($block, $page);
        $tag = $isTagSource ? $this->tagContext($block, $page) : null;
        $children = $category instanceof CmsCategory ? $this->childCategories($category) : collect();
        $showOnlySubcategories = (bool) ($block['show_only_subcategories'] ?? false);
        $showCategories = ! $isTagSource && $category instanceof CmsCategory && $showOnlySubcategories && $children->isNotEmpty();
        $layout = match ($block['renderer_key'] ?? null) {
            'list_rows' => 'rows',
            default => 'grid',
        };

        return [
            'renderer_key' => (string) ($block['renderer_key'] ?? 'list_grid'),
            'title' => $block['title'] ?? null,
            'layout' => $layout,
            'show_search' => (bool) ($block['show_search'] ?? false),
            'search_query' => $this->searchQuery($block),
            'show_excerpt' => (bool) ($block['show_excerpt'] ?? true),
            'show_image' => (bool) ($block['show_image'] ?? true),
            'show_date' => (bool) ($block['show_date'] ?? true),
            'show_categories' => (bool) ($block['show_categories'] ?? true),
            'empty_text' => $block['empty_text'] ?? null,
            'items_type' => $showCategories ? 'categories' : 'posts',
            'items' => $showCategories
                ? $children->map(fn (CmsCategory $child): array => $this->categoryPayload($child))->values()->all()
                : $this->posts($block, $page, $category, $tag, $isTagSource),
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function categoryContext(array $block, ?CmsPage $page): ?CmsCategory
    {
        $source = (string) ($block['category_source'] ?? 'all');

        if ($source === 'fixed' && filled($block['category_id'] ?? null)) {
            return CmsCategory::query()
                ->whereKey((int) $block['category_id'])
                ->where('is_active', true)
                ->first();
        }

        if ($source === 'current' && $page instanceof CmsPage) {
            return CmsCategory::query()
                ->where('landing_page_id', $page->id)
                ->where('is_active', true)
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function tagContext(array $block, ?CmsPage $page): ?CmsTag
    {
        $source = (string) ($block['tag_source'] ?? 'current');

        if ($source === 'fixed' && filled($block['tag_id'] ?? null)) {
            return CmsTag::query()
                ->whereKey((int) $block['tag_id'])
                ->where('is_active', true)
                ->first();
        }

        if ($source === 'current' && $page instanceof CmsPage) {
            return CmsTag::query()
                ->where('landing_page_id', $page->id)
                ->where('is_active', true)
                ->first();
        }

        return null;
    }

    /**
     * @return Collection<int, CmsCategory>
     */
    private function childCategories(CmsCategory $category): Collection
    {
        return CmsCategory::query()
            ->with('landingPage:id,parent_id,title,slug,locale,status,is_home,published_at')
            ->where('parent_id', $category->id)
            ->where('type', $category->type)
            ->where('locale', $category->locale)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->filter(fn (CmsCategory $child): bool => $this->landingPageIsPublic($child->landingPage));
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<int, array<string, mixed>>
     */
    private function posts(array $block, ?CmsPage $page, ?CmsCategory $category, ?CmsTag $tag, bool $isTagSource): array
    {
        $locale = $tag?->locale ?? $category?->locale ?? $page?->locale ?? app()->getLocale();
        $query = CmsPost::query()
            ->with(['featuredMedia.translations', 'categories', 'tags'])
            ->where('locale', $locale)
            ->where('status', 'published')
            ->where('noindex', false)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            });

        if ($category instanceof CmsCategory) {
            $categoryIds = $this->categoryAndDescendantIds($category);
            $query->whereHas('categories', fn (Builder $query): Builder => $query->whereIn('cms_categories.id', $categoryIds));
        }

        if ($tag instanceof CmsTag) {
            $query->whereHas('tags', fn (Builder $query): Builder => $query->whereKey($tag->id));
        } elseif ($isTagSource && ($block['tag_source'] ?? 'current') !== 'all') {
            return [];
        } elseif ($isTagSource) {
            $query->whereHas('tags', fn (Builder $query): Builder => $query->where('cms_tags.is_active', true));
        }

        $searchQuery = $this->searchQuery($block);

        if ($searchQuery !== '') {
            $query->where(function (Builder $query) use ($searchQuery): void {
                $query->where('title', 'like', '%'.$searchQuery.'%')
                    ->orWhere('excerpt', 'like', '%'.$searchQuery.'%');
            });
        }

        $sortField = in_array($block['sort_field'] ?? null, ['published_at', 'title', 'created_at'], true)
            ? (string) $block['sort_field']
            : 'published_at';
        $sortDirection = ($block['sort_direction'] ?? null) === 'asc' ? 'asc' : 'desc';
        $limit = min(max((int) ($block['limit'] ?? 24), 1), 100);

        return $query
            ->orderBy($sortField, $sortDirection)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (CmsPost $post): array => $this->postPayload($post, $tag, $isTagSource))
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function categoryAndDescendantIds(CmsCategory $category): array
    {
        $categories = CmsCategory::query()
            ->where('type', $category->type)
            ->where('locale', $category->locale)
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
     * @return array<string, mixed>
     */
    private function categoryPayload(CmsCategory $category): array
    {
        return [
            'id' => $category->id,
            'title' => $category->title,
            'slug' => $category->slug,
            'url' => $this->categoryUrl($category),
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
    private function postPayload(CmsPost $post, ?CmsTag $activeTag = null, bool $showTags = false): array
    {
        $taxonomyItems = $this->taxonomyItems($post, $activeTag, $showTags);

        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'url' => $this->urlBuilder->postPath($post),
            'excerpt' => $post->excerpt,
            'published_at' => $post->published_at?->toDateString(),
            'featured_media' => $this->mediaUrl->payload($post->featuredMedia, $post->locale),
            'categories' => $showTags ? [] : $taxonomyItems,
            'taxonomy_items' => $taxonomyItems,
            'taxonomy_prefix' => $showTags ? '#' : '',
        ];
    }

    /**
     * @return array<int, array{id: int, title: string, slug: string}>
     */
    private function taxonomyItems(CmsPost $post, ?CmsTag $activeTag, bool $showTags): array
    {
        if ($activeTag instanceof CmsTag) {
            return [[
                'id' => (int) $activeTag->id,
                'title' => (string) $activeTag->title,
                'slug' => (string) $activeTag->slug,
            ]];
        }

        $items = $showTags ? $post->tags : $post->categories;

        return $items
            ->where('is_active', true)
            ->values()
            ->map(fn (CmsCategory|CmsTag $item): array => [
                'id' => (int) $item->id,
                'title' => (string) $item->title,
                'slug' => (string) $item->slug,
            ])
            ->all();
    }

    private function categoryUrl(CmsCategory $category): string
    {
        if ($this->landingPageIsPublic($category->landingPage)) {
            $pages = CmsPage::query()
                ->get(['id', 'parent_id', 'slug', 'locale', 'is_home'])
                ->keyBy('id');

            return $this->urlBuilder->pagePath($category->landingPage, $pages);
        }

        $categories = CmsCategory::query()
            ->where('type', $category->type)
            ->where('locale', $category->locale)
            ->get(['id', 'parent_id', 'slug', 'locale'])
            ->keyBy('id');

        return $this->urlBuilder->categoryPath($category, $categories);
    }

    private function landingPageIsPublic(?CmsPage $page): bool
    {
        return $page instanceof CmsPage
            && $page->status === 'published'
            && ($page->published_at === null || $page->published_at->isPast());
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function searchQuery(array $block): string
    {
        if (! (bool) ($block['show_search'] ?? false)) {
            return '';
        }

        return mb_substr(trim((string) request()->query('q', '')), 0, 80);
    }
}
