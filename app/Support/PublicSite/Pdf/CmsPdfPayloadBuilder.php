<?php

namespace App\Support\PublicSite\Pdf;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use App\Support\Cms\CmsHtmlSanitizer;
use App\Support\PublicSite\CmsBlockPayloadBuilder;
use App\Support\PublicSite\CmsPageCompositionBuilder;
use App\Support\PublicSite\CmsPublicUrlBuilder;
use App\Support\PublicSite\CmsTemplateCompositionBuilder;
use App\Support\PublicSite\CmsTemplateResolver;
use App\Support\PublicSite\PublicMediaUrl;
use Illuminate\Support\Collection;

class CmsPdfPayloadBuilder
{
    public function __construct(
        private readonly CmsPageCompositionBuilder $pageCompositionBuilder,
        private readonly CmsTemplateResolver $templateResolver,
        private readonly CmsTemplateCompositionBuilder $templateCompositionBuilder,
        private readonly CmsBlockPayloadBuilder $blockPayloadBuilder,
        private readonly PublicMediaUrl $mediaUrl,
        private readonly CmsPublicUrlBuilder $urlBuilder,
        private readonly CmsHtmlSanitizer $htmlSanitizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function page(CmsPage $page, string $locale): array
    {
        $template = $this->templateResolver->resolve('page.detail', $locale, $page);
        $composition = $this->pageCompositionBuilder->handle($page);
        $contentSections = $composition['sections']['content'] ?? [];
        $pageItem = array_merge($composition['page'], [
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'template_data' => $page->template_data ?? [],
        ]);

        if ($template instanceof CmsTemplate) {
            $composition = $this->templateCompositionBuilder->handle(
                $template,
                $pageItem,
                [
                    'page' => array_merge($composition['page'], [
                        'content' => $contentSections,
                    ]),
                    'template' => [],
                ],
                [
                    'content' => [
                        'sections' => $contentSections,
                    ],
                ],
                page: $page,
            );
        }

        return $this->payload(
            type: 'page',
            title: (string) $page->title,
            locale: $locale,
            url: $pageItem['url'] ?? $this->pageUrl($page),
            description: $page->short_description,
            publishedAt: $page->published_at?->toDateString(),
            blocks: $this->blocksFromSections($composition['sections']['content'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function post(CmsPost $post, string $locale): array
    {
        $featuredMedia = $this->mediaUrl->payload($post->featuredMedia, $locale);
        $postItem = [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'locale' => $post->locale,
            'excerpt' => $post->excerpt,
            'published_at' => $post->published_at?->toDateString(),
            'featured_media' => $featuredMedia,
            'categories' => $this->taxonomyPayload($post->categories),
            'tags' => $this->tagPayload($post->tags),
            'template_class' => 'blog',
            'template_key' => 'blog.detail',
        ];
        $blocks = $this->blockPayloadBuilder->handle($post->content_blocks ?? [], post: $post);
        $template = $this->templateResolver->resolve('blog.detail', $locale, $post);

        if ($template instanceof CmsTemplate) {
            $composition = $this->templateCompositionBuilder->handle(
                $template,
                $postItem,
                [
                    'blog' => array_merge($postItem, [
                        'content' => $blocks,
                    ]),
                ],
                [
                    'content' => $blocks,
                ],
                post: $post,
            );
            $blocks = $this->blocksFromSections($composition['sections']['content'] ?? []);
        }

        if ($featuredMedia !== null) {
            array_unshift($blocks, [
                'renderer_key' => 'image',
                'media' => $featuredMedia,
                'caption' => $featuredMedia['caption'] ?? null,
            ]);
        }

        return $this->payload(
            type: 'post',
            title: (string) $post->title,
            locale: $locale,
            url: $this->urlBuilder->postUrl($post),
            description: $post->excerpt,
            publishedAt: $post->published_at?->toDateString(),
            blocks: $blocks,
            meta: [
                'categories' => $postItem['categories'],
                'tags' => $postItem['tags'],
            ],
        );
    }

    /**
     * @param  Collection<int, CmsPost>  $posts
     * @return array<string, mixed>
     */
    public function categoryArchive(CmsCategory $category, Collection $posts, string $locale): array
    {
        return $this->archivePayload(
            type: 'category_archive',
            title: (string) $category->title,
            locale: $locale,
            url: $this->categoryUrl($category),
            description: $category->description,
            posts: $posts,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function categoryDetail(CmsCategory $category, string $locale): array
    {
        return $this->taxonomyDetailPayload('category_detail', $category, $category->landingPage, $locale);
    }

    /**
     * @param  Collection<int, CmsPost>  $posts
     * @return array<string, mixed>
     */
    public function tagArchive(CmsTag $tag, Collection $posts, string $locale): array
    {
        return $this->archivePayload(
            type: 'tag_archive',
            title: '#'.(string) $tag->title,
            locale: $locale,
            url: $this->urlBuilder->tagUrl($tag),
            description: $tag->description,
            posts: $posts,
            taxonomyPrefix: '#',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function tagDetail(CmsTag $tag, string $locale): array
    {
        return $this->taxonomyDetailPayload('tag_detail', $tag, $tag->landingPage, $locale);
    }

    /**
     * @return array<string, mixed>
     */
    private function taxonomyDetailPayload(string $type, CmsCategory|CmsTag $taxonomy, ?CmsPage $page, string $locale): array
    {
        $blocks = $page instanceof CmsPage
            ? $this->blockPayloadBuilder->handle($page->content_blocks ?? [], page: $page)
            : [];

        return $this->payload(
            type: $type,
            title: (string) $taxonomy->title,
            locale: $locale,
            url: $taxonomy instanceof CmsCategory ? $this->categoryUrl($taxonomy) : $this->urlBuilder->tagUrl($taxonomy),
            description: $page?->short_description ?: $taxonomy->description,
            publishedAt: $page?->published_at?->toDateString(),
            blocks: $blocks,
        );
    }

    /**
     * @param  Collection<int, CmsPost>  $posts
     * @return array<string, mixed>
     */
    private function archivePayload(string $type, string $title, string $locale, string $url, ?string $description, Collection $posts, string $taxonomyPrefix = ''): array
    {
        return $this->payload(
            type: $type,
            title: $title,
            locale: $locale,
            url: $url,
            description: $description,
            publishedAt: null,
            blocks: [],
            items: $posts
                ->map(fn (CmsPost $post): array => $this->postListItem($post, $taxonomyPrefix))
                ->values()
                ->all(),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $meta
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function payload(string $type, string $title, string $locale, string $url, ?string $description, ?string $publishedAt, array $blocks, array $meta = [], array $items = []): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'locale' => $locale,
            'url' => $url,
            'description' => $description,
            'published_at' => $publishedAt,
            'blocks' => $this->safeBlocks($blocks),
            'items' => $items,
            'meta' => $meta,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function blocksFromSections(array $sections): array
    {
        return collect($sections)
            ->flatMap(fn (array $section): array => $section['placements'] ?? [])
            ->map(fn (array $placement): array => is_array($placement['block'] ?? null) ? $placement['block'] : [])
            ->filter(fn (array $block): bool => $block !== [])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function safeBlocks(array $blocks): array
    {
        $allowedRenderers = [
            'accordion',
            'address_block',
            'breadcrumb',
            'button',
            'content_list',
            'download_list',
            'feature_card',
            'form',
            'image',
            'list_grid',
            'list_rows',
            'logo_strip',
            'markdown_text',
            'quote',
            'rich_text',
            'site_button',
            'stats',
            'testimonial',
            'text',
            'video',
        ];

        return collect($blocks)
            ->filter(fn (array $block): bool => in_array((string) ($block['renderer_key'] ?? ''), $allowedRenderers, true))
            ->map(fn (array $block): array => $this->safeBlock($block))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function safeBlock(array $block): array
    {
        if (($block['renderer_key'] ?? null) === 'rich_text' && array_key_exists('html', $block)) {
            $block['html'] = $this->htmlSanitizer->clean($block['html']);
        }

        return $block;
    }

    /**
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

    /**
     * @return array<string, mixed>
     */
    private function postListItem(CmsPost $post, string $taxonomyPrefix = ''): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'url' => $this->urlBuilder->postUrl($post),
            'excerpt' => $post->excerpt,
            'published_at' => $post->published_at?->toDateString(),
            'featured_media' => $this->mediaUrl->payload($post->featuredMedia, $post->locale),
            'taxonomy_items' => $this->taxonomyPayload($post->categories),
            'taxonomy_prefix' => $taxonomyPrefix,
        ];
    }

    private function pageUrl(CmsPage $page): string
    {
        $pages = CmsPage::query()
            ->get(['id', 'parent_id', 'slug', 'locale', 'is_home'])
            ->keyBy('id');

        return $this->urlBuilder->pageUrl($page, $pages);
    }

    private function categoryUrl(CmsCategory $category): string
    {
        $categories = CmsCategory::query()
            ->where('type', $category->type)
            ->where('locale', $category->locale)
            ->get(['id', 'parent_id', 'slug', 'locale', 'type'])
            ->keyBy('id');

        return $this->urlBuilder->categoryUrl($category, $categories);
    }
}
