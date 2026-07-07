<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use Illuminate\Support\Collection;

class CmsBreadcrumbBuilder
{
    public function __construct(
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsPublicUrlBuilder $urlBuilder,
        private readonly CmsPublicTextResolver $publicTextResolver,
    ) {}

    /**
     * @return array<int, array{label: string, url: string|null, is_current: bool}>
     */
    public function handle(?CmsPage $page = null, ?CmsPost $post = null, bool $showCurrent = true): array
    {
        $items = $post instanceof CmsPost
            ? $this->postItems($post)
            : $this->pageItems($page);

        if (! $showCurrent && count($items) > 1) {
            array_pop($items);
            $lastIndex = count($items) - 1;

            if ($lastIndex >= 0) {
                $items[$lastIndex]['is_current'] = true;
                $items[$lastIndex]['url'] = null;
            }
        }

        return $items;
    }

    /**
     * @return array<int, array{label: string, url: string|null, is_current: bool}>
     */
    private function pageItems(?CmsPage $page): array
    {
        if (! $page instanceof CmsPage) {
            return [];
        }

        $category = CmsCategory::query()
            ->with('landingPage:id,parent_id,title,slug,locale,status,is_home,published_at')
            ->where('landing_page_id', $page->id)
            ->where('is_active', true)
            ->first();

        if ($category instanceof CmsCategory) {
            return $this->categoryItems($category);
        }

        return $this->pageTreeItems($page);
    }

    /**
     * @return array<int, array{label: string, url: string|null, is_current: bool}>
     */
    private function postItems(CmsPost $post): array
    {
        $post->loadMissing(['categories.landingPage']);
        $category = $post->categories
            ->where('is_active', true)
            ->sortBy([
                ['sort_order', 'asc'],
                ['title', 'asc'],
            ])
            ->first();

        $items = $category instanceof CmsCategory
            ? $this->categoryItems($category, true, false)
            : [$this->homeItem((string) $post->locale), [
                'label' => $this->postIndexLabel((string) $post->locale),
                'url' => $this->urlBuilder->postIndexPath($post->locale),
                'is_current' => false,
            ]];

        $items[] = [
            'label' => (string) $post->title,
            'url' => null,
            'is_current' => true,
        ];

        return $items;
    }

    /**
     * @return array<int, array{label: string, url: string|null, is_current: bool}>
     */
    private function categoryItems(CmsCategory $category, bool $includeCurrent = true, bool $markCurrent = true): array
    {
        $categories = CmsCategory::query()
            ->with('landingPage:id,parent_id,title,slug,locale,status,is_home,published_at')
            ->where('type', $category->type)
            ->where('locale', $category->locale)
            ->where('is_active', true)
            ->get(['id', 'parent_id', 'type', 'title', 'slug', 'locale', 'landing_page_id', 'sort_order', 'is_active'])
            ->keyBy('id');
        $chain = [];
        $current = $categories->get((int) $category->id) ?? $category;

        while ($current instanceof CmsCategory) {
            if (! $this->pageIsPublic($current->landingPage)) {
                break;
            }

            array_unshift($chain, $current);

            if (! $current->parent_id) {
                break;
            }

            $current = $categories->get((int) $current->parent_id);
        }

        if (! $includeCurrent) {
            array_pop($chain);
        }

        $items = [
            $this->homeItem((string) $category->locale),
            ...array_map(fn (CmsCategory $item): array => [
                'label' => (string) $item->title,
                'url' => $this->categoryUrl($item),
                'is_current' => false,
            ], $chain),
        ];

        return $markCurrent ? $this->markCurrent($items) : $items;
    }

    /**
     * @return array<int, array{label: string, url: string|null, is_current: bool}>
     */
    private function pageTreeItems(CmsPage $page): array
    {
        $pages = CmsPage::query()
            ->get(['id', 'parent_id', 'title', 'slug', 'locale', 'status', 'is_home', 'published_at'])
            ->keyBy('id');
        $chain = [];
        $current = $pages->get((int) $page->id) ?? $page;

        while ($current instanceof CmsPage) {
            if (! $this->pageIsPublic($current)) {
                break;
            }

            if (! $current->is_home) {
                array_unshift($chain, $current);
            }

            if (! $current->parent_id) {
                break;
            }

            $current = $pages->get((int) $current->parent_id);
        }

        return $this->markCurrent([
            $this->homeItem((string) $page->locale),
            ...array_map(fn (CmsPage $item): array => [
                'label' => (string) $item->title,
                'url' => $this->urlBuilder->pagePath($item, $pages),
                'is_current' => false,
            ], $chain),
        ]);
    }

    /**
     * @param  array<int, array{label: string, url: string|null, is_current: bool}>  $items
     * @return array<int, array{label: string, url: string|null, is_current: bool}>
     */
    private function markCurrent(array $items): array
    {
        $lastIndex = count($items) - 1;

        foreach ($items as $index => $item) {
            $items[$index]['is_current'] = $index === $lastIndex;
            $items[$index]['url'] = $index === $lastIndex ? null : $item['url'];
        }

        return $items;
    }

    /**
     * @return array{label: string, url: string|null, is_current: bool}
     */
    private function homeItem(string $locale): array
    {
        return [
            'label' => $this->publicTextResolver->get('breadcrumb.home', $locale),
            'url' => $this->languageSettings->pathPrefix($locale) ?: '/',
            'is_current' => false,
        ];
    }

    private function postIndexLabel(string $locale): string
    {
        return $this->publicTextResolver->get('breadcrumb.posts', $locale);
    }

    private function categoryUrl(CmsCategory $category): string
    {
        if ($this->pageIsPublic($category->landingPage)) {
            $pages = CmsPage::query()
                ->get(['id', 'parent_id', 'slug', 'locale', 'is_home'])
                ->keyBy('id');

            return $this->urlBuilder->pagePath($category->landingPage, $pages);
        }

        /** @var Collection<int, CmsCategory> $categories */
        $categories = CmsCategory::query()
            ->where('type', $category->type)
            ->where('locale', $category->locale)
            ->get(['id', 'parent_id', 'slug', 'locale'])
            ->keyBy('id');

        return $this->urlBuilder->categoryPath($category, $categories);
    }

    private function pageIsPublic(?CmsPage $page): bool
    {
        return $page instanceof CmsPage
            && $page->status === 'published'
            && ($page->published_at === null || $page->published_at->isPast());
    }
}
