<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsTag;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CmsPublicUrlBuilder
{
    public function __construct(private readonly CmsLanguageSettings $languageSettings) {}

    /**
     * @param  Collection<int, CmsPage>  $pages
     */
    public function pagePath(CmsPage $page, Collection $pages): string
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

            $current = $pages->get((int) $current->parent_id);
        }

        return $this->languageSettings->pathPrefix($page->locale).'/'.implode('/', $segments);
    }

    /**
     * @param  Collection<int, CmsPage>  $pages
     */
    public function pageUrl(CmsPage $page, Collection $pages): string
    {
        return url($this->pagePath($page, $pages));
    }

    public function postPath(CmsPost $post): string
    {
        return $this->postIndexPath($post->locale).'/'.$post->slug;
    }

    public function postIndexPath(?string $locale): string
    {
        return $this->languageSettings->pathPrefix($locale).'/blogs';
    }

    public function postUrl(CmsPost $post): string
    {
        return url($this->postPath($post));
    }

    /**
     * @param  Collection<int, CmsCategory>  $categories
     */
    public function categoryPath(CmsCategory $category, Collection $categories): string
    {
        return $this->categoryIndexPath($category->locale).'/'.$this->categoryRelativePath($category, $categories);
    }

    public function categoryIndexPath(?string $locale): string
    {
        return $this->languageSettings->pathPrefix($locale).'/blogs/categories';
    }

    public function categoryDetailPath(CmsCategory $category, Collection $categories): string
    {
        return $this->categoryPath($category, $categories).'/info';
    }

    /**
     * @param  Collection<int, CmsCategory>  $categories
     */
    public function categoryUrl(CmsCategory $category, Collection $categories): string
    {
        return url($this->categoryPath($category, $categories));
    }

    /**
     * @param  Collection<int, CmsCategory>  $categories
     */
    public function categoryRelativePath(CmsCategory $category, Collection $categories): string
    {
        $segments = [];
        $current = $category;

        while ($current instanceof CmsCategory) {
            array_unshift($segments, $current->slug);

            if ($current->parent_id === null) {
                break;
            }

            $current = $categories->get((int) $current->parent_id);
        }

        return implode('/', $segments);
    }

    public function tagPath(CmsTag $tag): string
    {
        return $this->tagIndexPath($tag->locale).'/'.$tag->slug;
    }

    public function tagIndexPath(?string $locale): string
    {
        return $this->languageSettings->pathPrefix($locale).'/blogs/tags';
    }

    public function tagDetailPath(CmsTag $tag): string
    {
        return $this->tagPath($tag).'/info';
    }

    public function tagUrl(CmsTag $tag): string
    {
        return url($this->tagPath($tag));
    }

    public function sitemapUrl(string $type): string
    {
        return url('/sitemap-'.$type.'.xml');
    }

    public function canonicalIsExternal(?string $canonicalUrl): bool
    {
        if (! is_string($canonicalUrl) || trim($canonicalUrl) === '') {
            return false;
        }

        if (Str::startsWith($canonicalUrl, '/')) {
            return false;
        }

        $host = parse_url($canonicalUrl, PHP_URL_HOST);

        return is_string($host) && $host !== request()->getHost();
    }
}
