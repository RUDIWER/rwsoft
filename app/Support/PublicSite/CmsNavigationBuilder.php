<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use Illuminate\Support\Collection;

class CmsNavigationBuilder
{
    public function __construct(
        private readonly PublicSafeUrl $safeUrl,
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsPublicUrlBuilder $urlBuilder,
    ) {}

    /**
     * @return array{header: array{title: ?string, items: array<int, array<string, mixed>>}, footer: array{title: ?string, items: array<int, array<string, mixed>>}}
     */
    public function handle(string $locale): array
    {
        return [
            'header' => $this->menuForPlacement('header', $locale),
            'footer' => $this->menuForPlacement('footer', $locale),
        ];
    }

    /**
     * @return array{title: ?string, items: array<int, array<string, mixed>>}
     */
    public function menuForId(int $menuId, string $locale, ?string $requiredPlacement = null): array
    {
        if ($menuId <= 0) {
            return ['title' => null, 'items' => []];
        }

        $menu = CmsMenu::query()
            ->whereKey($menuId)
            ->where('is_active', true)
            ->first();

        if ($requiredPlacement !== null && $menu instanceof CmsMenu && ! $menu->availableForPlacement($requiredPlacement)) {
            return ['title' => null, 'items' => []];
        }

        return $this->menuPayload($menu, $locale);
    }

    /**
     * @return array{title: ?string, items: array<int, array<string, mixed>>}
     */
    private function menuForPlacement(string $placement, string $locale): array
    {
        return $this->menuPayload($this->baseMenuForPlacement($placement), $locale);
    }

    /**
     * @return array{title: ?string, items: array<int, array<string, mixed>>}
     */
    private function menuPayload(?CmsMenu $menu, string $locale): array
    {
        $defaultLocale = $this->languageSettings->defaultLocale();

        if (! $menu instanceof CmsMenu) {
            return ['title' => null, 'items' => []];
        }

        $menu->loadMissing([
            'translations',
            'items.page:id,parent_id,title,slug,locale,translation_key,status,is_home,published_at',
            'items.post:id,title,slug,locale,translation_key,status,published_at',
        ]);

        $items = $menu->items
            ->where('is_active', true)
            ->groupBy(fn (CmsMenuItem $item): string => (string) ($item->translation_key ?: 'item-'.$item->id))
            ->map(fn (Collection $group): ?CmsMenuItem => $this->itemForLocale($group, $locale, $defaultLocale))
            ->filter()
            ->filter(fn (CmsMenuItem $item): bool => $this->menuItemIsRenderable($item, $locale))
            ->sortBy('sort_order')
            ->values();

        return [
            'title' => $this->menuTitleForLocale($menu, $locale, $defaultLocale),
            'items' => $this->menuItemTree($items, $locale),
        ];
    }

    private function baseMenuForPlacement(string $placement): ?CmsMenu
    {
        return CmsMenu::query()
            ->whereJsonContains('placements', $placement)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

    private function menuTitleForLocale(CmsMenu $menu, string $locale, string $defaultLocale): ?string
    {
        $translations = $menu->relationLoaded('translations')
            ? $menu->translations
            : $menu->translations()->get();
        $localizedTitle = trim((string) $translations->firstWhere('locale', $locale)?->title);

        if ($localizedTitle !== '') {
            return $localizedTitle;
        }

        $defaultTitle = trim((string) $translations->firstWhere('locale', $defaultLocale)?->title);

        if ($defaultTitle !== '') {
            return $defaultTitle;
        }

        $fallbackTitle = trim((string) $menu->title);

        return $fallbackTitle !== '' ? $fallbackTitle : null;
    }

    /**
     * @param  Collection<int, CmsMenuItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function menuItemTree(Collection $items, string $locale, ?int $parentId = null): array
    {
        $rows = [];

        foreach ($items->where('parent_id', $parentId) as $item) {
            $rows[] = [
                'id' => $item->id,
                'label' => $item->label,
                'type' => $item->type,
                'url' => $this->menuItemUrl($item, $locale),
                'target' => $item->target,
                'rel' => $item->rel,
                'children' => $this->menuItemTree($items, $locale, $item->id),
            ];
        }

        return $rows;
    }

    private function menuItemUrl(CmsMenuItem $item, string $locale): string
    {
        if (in_array($item->type, ['page', 'category'], true) && $item->page instanceof CmsPage) {
            $page = $this->pageForLocale($item->page, $locale);

            return $page instanceof CmsPage ? $this->pageUrl($page) : '#';
        }

        if ($item->type === 'post' && $item->post instanceof CmsPost) {
            $post = $this->postForLocale($item->post, $locale);

            return $post instanceof CmsPost ? $this->urlBuilder->postPath($post) : '#';
        }

        return $this->safeUrl->handle($item->url) ?: '#';
    }

    /**
     * @param  Collection<int, CmsMenuItem>  $group
     */
    private function itemForLocale(Collection $group, string $locale, string $defaultLocale): ?CmsMenuItem
    {
        $localeItem = $group->firstWhere('locale', $locale);

        if ($localeItem instanceof CmsMenuItem) {
            return $localeItem;
        }

        $fallbackItem = $group->firstWhere('locale', $defaultLocale) ?? $group->firstWhere('locale', null);

        if ($locale === $defaultLocale && $fallbackItem instanceof CmsMenuItem) {
            return $fallbackItem;
        }

        return null;
    }

    private function menuItemIsRenderable(CmsMenuItem $item, string $locale): bool
    {
        if (in_array($item->type, ['page', 'category'], true)) {
            $page = $item->page instanceof CmsPage ? $this->pageForLocale($item->page, $locale) : null;

            return $page instanceof CmsPage
                && $this->pageUrl($page) !== '#';
        }

        if ($item->type === 'post') {
            return $item->post instanceof CmsPost
                && $this->postForLocale($item->post, $locale) instanceof CmsPost;
        }

        return $this->safeUrl->handle($item->url) !== null;
    }

    public function pageUrl(CmsPage $page): string
    {
        if ($page->is_home) {
            return $this->languageSettings->pathPrefix($page->locale) ?: '/';
        }

        $segments = $this->pagePathSegments($page);

        return $segments === [] ? '#' : $this->languageSettings->pathPrefix($page->locale).'/'.implode('/', $segments);
    }

    /**
     * @return array<int, string>
     */
    public function pagePathSegments(CmsPage $page): array
    {
        $segments = [];
        $current = $page;

        while ($current instanceof CmsPage) {
            if (! $this->pageIsPublic($current)) {
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

    private function pageIsPublic(CmsPage $page): bool
    {
        return $page->status === 'published'
            && ($page->published_at === null || $page->published_at->isPast());
    }

    private function pageForLocale(CmsPage $sourcePage, string $locale): ?CmsPage
    {
        if ((string) $sourcePage->locale === $locale && $this->pageIsPublic($sourcePage)) {
            return $sourcePage;
        }

        if (blank($sourcePage->translation_key)) {
            return null;
        }

        return CmsPage::query()
            ->where('translation_key', $sourcePage->translation_key)
            ->where('locale', $locale)
            ->where('status', 'published')
            ->where(function ($query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->first(['id', 'parent_id', 'title', 'slug', 'locale', 'translation_key', 'status', 'is_home', 'published_at']);
    }

    private function postForLocale(CmsPost $sourcePost, string $locale): ?CmsPost
    {
        if ((string) $sourcePost->locale === $locale && $this->postIsPublic($sourcePost)) {
            return $sourcePost;
        }

        if (blank($sourcePost->translation_key)) {
            return null;
        }

        return CmsPost::query()
            ->where('translation_key', $sourcePost->translation_key)
            ->where('locale', $locale)
            ->where('status', 'published')
            ->where(function ($query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->first(['id', 'title', 'slug', 'locale', 'translation_key', 'status', 'published_at']);
    }

    private function postIsPublic(CmsPost $post): bool
    {
        return $post->status === 'published'
            && ($post->published_at === null || $post->published_at->isPast());
    }
}
