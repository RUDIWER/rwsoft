<?php

namespace App\Support\PublicSite;

use App\Actions\Admin\Base\RenderPlaceholdersAction;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsTag;

class CmsStructuredDataBuilder
{
    public function __construct(
        private readonly CmsPublicUrlBuilder $urlBuilder,
        private readonly PublicStorageUrl $storageUrl,
    ) {}

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>|null
     */
    public function forPage(CmsPage $page, array $site): ?array
    {
        if ((bool) $page->noindex || (bool) ($site['global_noindex'] ?? false)) {
            return null;
        }

        $schemaType = (string) ($page->settings['structured_data_schema_type'] ?? 'auto');

        if ($schemaType === 'None') {
            return null;
        }

        $pages = CmsPage::query()->get(['id', 'parent_id', 'slug', 'locale', 'is_home'])->keyBy('id');
        $url = $this->urlBuilder->pageUrl($page, $pages);
        $title = $page->seo_title ?: $page->title;
        $description = $page->seo_description ?: $page->short_description;
        $type = $schemaType !== 'auto' ? $schemaType : 'WebPage';
        $graph = $this->baseGraph($site, $url);
        $graph[] = $this->webPageNode($title, $description, $url, $page->updated_at?->toAtomString(), $type);
        $graph[] = $this->breadcrumbNode($url, [['name' => $title, 'url' => $url]]);

        return $this->graph($graph, $this->extraGraph($page->settings['structured_data_extra'] ?? null, 'cms.page.json_ld', $this->pagePlaceholderData($page, $site, $url)));
    }

    /**
     * @param  array<string, mixed>  $site
     * @param  array<string, mixed>|null  $featuredMedia
     * @return array<string, mixed>|null
     */
    public function forPost(CmsPost $post, array $site, ?array $featuredMedia = null): ?array
    {
        if ((bool) $post->noindex || (bool) ($site['global_noindex'] ?? false)) {
            return null;
        }

        $schemaType = (string) ($post->settings['structured_data_schema_type'] ?? 'auto');

        if ($schemaType === 'None') {
            return null;
        }

        $url = $this->urlBuilder->postUrl($post);
        $title = $post->seo_title ?: $post->title;
        $description = $post->seo_description ?: $post->excerpt;
        $image = $featuredMedia['url'] ?? $this->mediaUrl($post->featuredMedia);
        $type = $schemaType !== 'auto' ? $schemaType : 'BlogPosting';
        $graph = $this->baseGraph($site, $url);
        $article = $this->webPageNode($title, $description, $url, $post->updated_at?->toAtomString(), $type);
        $article['headline'] = $title;
        $article['datePublished'] = $post->published_at?->toAtomString();
        $article['dateModified'] = $post->updated_at?->toAtomString();
        $article['publisher'] = ['@id' => url('/').'#organization'];
        $article['author'] = ['@type' => 'Organization', 'name' => $site['name'] ?? config('app.name')];

        if ($image) {
            $article['image'] = [$image];
        }

        $graph[] = $article;
        $graph[] = $this->breadcrumbNode($url, [
            ['name' => public_text('breadcrumb.posts', 'Blogs', $post->locale), 'url' => url($this->urlBuilder->postIndexPath($post->locale))],
            ['name' => $title, 'url' => $url],
        ]);

        $post->loadMissing(['categories', 'tags']);

        return $this->graph($graph, $this->extraGraph($post->settings['structured_data_extra'] ?? null, 'cms.post.json_ld', $this->postPlaceholderData($post, $site, $url, $image)));
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>|null
     */
    public function forCategory(CmsCategory $category, array $site): ?array
    {
        if ((bool) ($site['global_noindex'] ?? false)) {
            return null;
        }

        $categories = CmsCategory::query()->get(['id', 'parent_id', 'slug', 'locale'])->keyBy('id');
        $url = $this->urlBuilder->categoryUrl($category, $categories);

        return $this->graph([
            ...$this->baseGraph($site, $url),
            $this->webPageNode($category->title, $category->description, $url, $category->updated_at?->toAtomString(), 'CollectionPage'),
            $this->breadcrumbNode($url, [['name' => $category->title, 'url' => $url]]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>|null
     */
    public function forTag(CmsTag $tag, array $site): ?array
    {
        if ((bool) ($site['global_noindex'] ?? false)) {
            return null;
        }

        $url = $this->urlBuilder->tagUrl($tag);

        return $this->graph([
            ...$this->baseGraph($site, $url),
            $this->webPageNode('#'.$tag->title, $tag->description, $url, $tag->updated_at?->toAtomString(), 'CollectionPage'),
            $this->breadcrumbNode($url, [['name' => '#'.$tag->title, 'url' => $url]]),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $graph
     */
    public function encode(?array $graph): ?string
    {
        if ($graph === null) {
            return null;
        }

        return json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: null;
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function placeholders(string $context): array
    {
        return RenderPlaceholdersAction::placeholders($context);
    }

    /**
     * @param  array<int, array<string, mixed>>  $graph
     * @param  array<int, mixed>  $extraGraph
     * @return array<string, mixed>
     */
    private function graph(array $graph, array $extraGraph = []): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter([...$graph, ...$extraGraph])),
        ];
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<int, array<string, mixed>>
     */
    private function baseGraph(array $site, string $url): array
    {
        $siteName = (string) ($site['name'] ?? config('app.name'));

        return [
            [
                '@type' => 'Organization',
                '@id' => url('/').'#organization',
                'name' => $siteName,
                'url' => url('/'),
            ],
            [
                '@type' => 'WebSite',
                '@id' => url('/').'#website',
                'url' => url('/'),
                'name' => $siteName,
                'publisher' => ['@id' => url('/').'#organization'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function webPageNode(string $title, ?string $description, string $url, ?string $dateModified, string $type): array
    {
        return array_filter([
            '@type' => $type,
            '@id' => $url.'#webpage',
            'url' => $url,
            'name' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => url('/').'#website'],
            'dateModified' => $dateModified,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    private function breadcrumbNode(string $url, array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            '@id' => $url.'#breadcrumb',
            'itemListElement' => collect($items)
                ->values()
                ->map(fn (array $item, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url'],
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, mixed>
     */
    private function extraGraph(mixed $template, string $context, array $data): array
    {
        if (! is_string($template) || trim($template) === '') {
            return [];
        }

        $rendered = RenderPlaceholdersAction::handle($template, $context, $data);
        $decoded = json_decode($rendered, true);

        if (! is_array($decoded)) {
            return [];
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        return [$decoded];
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    private function pagePlaceholderData(CmsPage $page, array $site, string $url): array
    {
        return [
            'page' => [
                'title' => $page->title,
                'short_description' => $page->short_description,
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'url' => $url,
                'published_at' => $page->published_at?->toAtomString(),
                'updated_at' => $page->updated_at?->toAtomString(),
            ],
            'site' => [
                'name' => $site['name'] ?? config('app.name'),
                'url' => url('/'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    private function postPlaceholderData(CmsPost $post, array $site, string $url, ?string $image): array
    {
        return [
            'post' => [
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'seo_title' => $post->seo_title,
                'seo_description' => $post->seo_description,
                'url' => $url,
                'published_at' => $post->published_at?->toAtomString(),
                'updated_at' => $post->updated_at?->toAtomString(),
                'featured_image_url' => $image,
                'categories' => $post->categories->pluck('title')->all(),
                'tags' => $post->tags->pluck('title')->all(),
            ],
            'site' => [
                'name' => $site['name'] ?? config('app.name'),
                'url' => url('/'),
            ],
        ];
    }

    private function mediaUrl(mixed $media): ?string
    {
        if (! $media || ! isset($media->path)) {
            return null;
        }

        return $this->storageUrl->url($media->path, $media->disk ?: 'public');
    }
}
