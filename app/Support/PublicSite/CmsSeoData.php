<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsTag;
use Illuminate\Http\Request;

class CmsSeoData
{
    public function __construct(
        private readonly CmsStructuredDataBuilder $structuredDataBuilder,
        private readonly CmsCanonicalUrlPolicy $canonicalUrlPolicy,
    ) {}

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    public function forPage(CmsPage $page, array $site, Request $request): array
    {
        return $this->build(
            title: $page->seo_title ?: $page->title,
            description: $page->seo_description ?: ($page->short_description ?: ($site['seo_default_description'] ?? null)),
            canonicalUrl: $this->canonicalUrlPolicy->toAbsoluteUrl($page->canonical_url, $request->url(), $page->locale, $request),
            noindex: (bool) $page->noindex || (bool) ($site['global_noindex'] ?? false),
            ogImage: $page->og_image_path,
            request: $request,
            jsonLd: $this->structuredDataBuilder->encode($this->structuredDataBuilder->forPage($page, $site)),
        );
    }

    /**
     * @param  array<string, mixed>  $site
     * @param  array<string, mixed>|null  $featuredMedia
     * @return array<string, mixed>
     */
    public function forPost(CmsPost $post, array $site, Request $request, ?array $featuredMedia): array
    {
        return array_merge($this->build(
            title: $post->seo_title ?: $post->title,
            description: $post->seo_description ?: ($post->excerpt ?: ($site['seo_default_description'] ?? null)),
            canonicalUrl: $this->canonicalUrlPolicy->toAbsoluteUrl($post->canonical_url, $request->url(), $post->locale, $request),
            noindex: (bool) $post->noindex || (bool) ($site['global_noindex'] ?? false),
            ogImage: $featuredMedia['url'] ?? $post->og_image_path,
            request: $request,
            type: 'article',
            jsonLd: $this->structuredDataBuilder->encode($this->structuredDataBuilder->forPost($post, $site, $featuredMedia)),
        ), [
            'article_published_time' => $post->published_at?->toAtomString(),
            'article_modified_time' => $post->updated_at?->toAtomString(),
            'article_section' => $post->categories->first()?->title,
            'article_tags' => $post->tags->pluck('title')->values()->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    public function forPostIndex(array $site, Request $request): array
    {
        $locale = (string) ($site['current_locale'] ?? app()->getLocale());
        $title = trim(public_text('post_index.seo_title', 'Blogs', $locale));
        $description = trim(public_text('post_index.seo_description', 'Laatste gepubliceerde blogs.', $locale));

        return $this->build(
            title: ($title !== '' ? $title : (string) ($site['seo_default_title'] ?? $site['name'] ?? config('app.name'))).' - '.($site['name'] ?? config('app.name')),
            description: $description !== '' ? $description : ($site['seo_default_description'] ?? null),
            canonicalUrl: $request->url(),
            noindex: (bool) ($site['global_noindex'] ?? false),
            ogImage: null,
            request: $request,
            jsonLd: null,
        );
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    public function forDocumentation(string $title, ?string $description, bool $noindex, array $site, Request $request): array
    {
        return $this->build(
            title: $title !== '' ? $title : (string) ($site['seo_default_title'] ?? $site['name'] ?? config('app.name')),
            description: $description ?: ($site['seo_default_description'] ?? null),
            canonicalUrl: $request->url(),
            noindex: $noindex || (bool) ($site['global_noindex'] ?? false),
            ogImage: null,
            request: $request,
            jsonLd: null,
        );
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    public function forCategory(CmsCategory $category, array $site, Request $request): array
    {
        return $this->build(
            title: $category->title,
            description: $category->description ?: ($site['seo_default_description'] ?? null),
            canonicalUrl: $request->url(),
            noindex: (bool) ($site['global_noindex'] ?? false),
            ogImage: null,
            request: $request,
            jsonLd: $this->structuredDataBuilder->encode($this->structuredDataBuilder->forCategory($category, $site)),
        );
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    public function forTag(CmsTag $tag, array $site, Request $request): array
    {
        return $this->build(
            title: '#'.$tag->title,
            description: $tag->description ?: ($site['seo_default_description'] ?? null),
            canonicalUrl: $request->url(),
            noindex: (bool) ($site['global_noindex'] ?? false),
            ogImage: null,
            request: $request,
            jsonLd: $this->structuredDataBuilder->encode($this->structuredDataBuilder->forTag($tag, $site)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(string $title, ?string $description, string $canonicalUrl, bool $noindex, ?string $ogImage, Request $request, string $type = 'website', ?string $jsonLd = null): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'canonical_url' => $canonicalUrl,
            'robots' => $noindex ? 'noindex,nofollow' : 'index,follow',
            'og_type' => $type,
            'og_title' => $title,
            'og_description' => $description,
            'og_url' => $request->url(),
            'og_image' => $ogImage,
            'og_site_name' => $request->attributes->get('site_name') ?: null,
            'og_locale' => str_replace('-', '_', app()->getLocale()),
            'twitter_card' => $ogImage ? 'summary_large_image' : 'summary',
            'twitter_title' => $title,
            'twitter_description' => $description,
            'twitter_image' => $ogImage,
            'json_ld' => $jsonLd,
        ];
    }
}
