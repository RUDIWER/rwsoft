<?php

namespace App\Support\Cms\Search\Providers;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsDocCollection;
use App\Models\Cms\CmsDocPage;
use App\Models\Cms\CmsDocVersion;
use App\Models\Cms\CmsModule;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsTag;
use App\Support\Cms\Docs\CmsDocsMarkdownRenderer;
use App\Support\Cms\Markdown\CmsContentMarkdownExtractor;
use App\Support\Cms\Markdown\CmsMarkdownNormalizer;
use App\Support\Cms\Search\CmsSearchDocumentData;
use App\Support\Cms\Search\Contracts\CmsSearchDocumentProvider;
use App\Support\PublicSite\CmsBlockPayloadBuilder;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsPageCompositionBuilder;
use App\Support\PublicSite\CmsPublicUrlBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CmsPublicContentSearchDocumentProvider implements CmsSearchDocumentProvider
{
    public function __construct(
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsPublicUrlBuilder $urlBuilder,
        private readonly CmsPageCompositionBuilder $pageCompositionBuilder,
        private readonly CmsBlockPayloadBuilder $blockPayloadBuilder,
        private readonly CmsContentMarkdownExtractor $extractor,
        private readonly CmsMarkdownNormalizer $normalizer,
        private readonly CmsDocsMarkdownRenderer $docsMarkdownRenderer,
    ) {}

    /**
     * @return array<int, string>
     */
    public function sourceTypes(): array
    {
        return (array) config('cms_search.source_types', []);
    }

    /**
     * @return iterable<int, CmsSearchDocumentData>
     */
    public function documents(?string $locale = null): iterable
    {
        foreach ($this->locales($locale) as $currentLocale) {
            yield from $this->pageDocuments($currentLocale);
            yield from $this->postDocuments($currentLocale);
            yield from $this->blogIndexDocuments($currentLocale);
            yield from $this->categoryDocuments($currentLocale);
            yield from $this->categoryIndexDocuments($currentLocale);
            yield from $this->tagDocuments($currentLocale);
            yield from $this->tagIndexDocuments($currentLocale);
            yield from $this->docsDocuments($currentLocale);
        }
    }

    /**
     * @return array<int, string>
     */
    private function locales(?string $locale): array
    {
        if (is_string($locale) && trim($locale) !== '') {
            return [trim($locale)];
        }

        return $this->languageSettings->activeLocales();
    }

    /**
     * @return iterable<int, CmsSearchDocumentData>
     */
    private function pageDocuments(string $locale): iterable
    {
        if (! Schema::hasTable('cms_pages')) {
            return;
        }

        $pages = CmsPage::query()
            ->where('locale', $locale)
            ->where('status', 'published')
            ->where('is_searchable', true)
            ->where('noindex', false)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($pages as $page) {
            $composition = $this->pageCompositionBuilder->handle($page);
            $body = $this->extractor->fromSections($composition['sections']['content'] ?? []);
            $canonicalUrl = $this->urlBuilder->pageUrl($page, $pages->keyBy('id'));
            $canonicalPath = parse_url($canonicalUrl, PHP_URL_PATH) ?: null;
            $pageMarkdownPath = $this->markdownPath($locale, 'pages/'.$this->pathWithoutLocale($canonicalPath, $locale));

            yield new CmsSearchDocumentData(
                sourceType: 'page',
                sourceKey: (string) $page->id,
                sourceId: (int) $page->id,
                locale: $locale,
                title: (string) $page->title,
                slug: (string) $page->slug,
                summary: $page->short_description ?: $page->seo_description,
                canonicalPath: $canonicalPath,
                canonicalUrl: $canonicalUrl,
                markdownPath: $pageMarkdownPath,
                markdownUrl: url($pageMarkdownPath),
                markdown: $this->normalizer->document((string) $page->title, $page->short_description ?: $page->seo_description, $body, $canonicalUrl),
                plainText: $this->normalizer->plain((string) $page->title.' '.($page->short_description ?: '').' '.$body),
                metadata: ['is_home' => (bool) $page->is_home],
                publishedAt: $page->published_at,
                sourceUpdatedAt: $page->updated_at,
            );
        }
    }

    /**
     * @return iterable<int, CmsSearchDocumentData>
     */
    private function postDocuments(string $locale): iterable
    {
        if (! Schema::hasTable('cms_posts')) {
            return;
        }

        $posts = CmsPost::query()
            ->with(['categories', 'tags'])
            ->where('locale', $locale)
            ->where('status', 'published')
            ->where('is_searchable', true)
            ->where('noindex', false)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        foreach ($posts as $post) {
            $blocks = $this->blockPayloadBuilder->handle($post->content_blocks ?? [], post: $post);
            $body = $this->extractor->fromBlocks($blocks);
            $canonicalPath = $this->urlBuilder->postPath($post);
            $canonicalUrl = url($canonicalPath);
            $markdownPath = $this->markdownPath($locale, 'blogs/'.$post->slug);

            yield new CmsSearchDocumentData(
                sourceType: 'post',
                sourceKey: (string) $post->id,
                sourceId: (int) $post->id,
                locale: $locale,
                title: (string) $post->title,
                slug: (string) $post->slug,
                summary: $post->excerpt ?: $post->seo_description,
                canonicalPath: $canonicalPath,
                canonicalUrl: $canonicalUrl,
                markdownPath: $markdownPath,
                markdownUrl: url($markdownPath),
                markdown: $this->normalizer->document((string) $post->title, $post->excerpt ?: $post->seo_description, $body, $canonicalUrl),
                plainText: $this->normalizer->plain((string) $post->title.' '.($post->excerpt ?: '').' '.$body),
                metadata: [
                    'categories' => $post->categories->pluck('title')->values()->all(),
                    'tags' => $post->tags->pluck('title')->values()->all(),
                ],
                publishedAt: $post->published_at,
                sourceUpdatedAt: $post->updated_at,
            );
        }
    }

    /**
     * @return iterable<int, CmsSearchDocumentData>
     */
    private function blogIndexDocuments(string $locale): iterable
    {
        if (! Schema::hasTable('cms_posts')) {
            return;
        }

        $posts = $this->publishedPosts($locale)->limit(50)->get(['id', 'title', 'slug', 'excerpt', 'published_at']);

        if ($posts->isEmpty()) {
            return;
        }

        $title = public_text('post_index.title', 'Blogs', $locale);
        $summary = public_text('post_index.lead', 'Latest published blog posts and updates.', $locale);
        $body = $posts
            ->map(fn (CmsPost $post): string => '- ['.$post->title.']('.$this->urlBuilder->postPath($post).')'.($post->excerpt ? ' - '.$this->normalizer->plain($post->excerpt) : ''))
            ->implode("\n");
        $canonicalPath = $this->urlBuilder->postIndexPath($locale);
        $markdownPath = $this->markdownPath($locale, 'blogs');

        yield new CmsSearchDocumentData(
            sourceType: 'blog_index',
            sourceKey: 'blog-index',
            sourceId: null,
            locale: $locale,
            title: $title,
            slug: 'blogs',
            summary: $summary,
            canonicalPath: $canonicalPath,
            canonicalUrl: url($canonicalPath),
            markdownPath: $markdownPath,
            markdownUrl: url($markdownPath),
            markdown: $this->normalizer->document($title, $summary, $body, url($canonicalPath)),
            plainText: $this->normalizer->plain($title.' '.$summary.' '.$body),
            metadata: ['post_count' => $posts->count()],
        );
    }

    /**
     * @return iterable<int, CmsSearchDocumentData>
     */
    private function categoryDocuments(string $locale): iterable
    {
        if (! Schema::hasTable('cms_categories')) {
            return;
        }

        $categories = CmsCategory::query()->where('locale', $locale)->where('is_active', true)->get()->keyBy('id');

        foreach ($categories as $category) {
            $posts = $this->publishedPosts($locale)
                ->whereHas('categories', fn (Builder $query) => $query->whereKey($category->id))
                ->limit(50)
                ->get(['id', 'title', 'slug', 'excerpt']);

            if ($posts->isEmpty()) {
                continue;
            }

            $canonicalPath = $this->urlBuilder->categoryPath($category, $categories);
            $markdownPath = $this->markdownPath($locale, 'blogs/categories/'.$this->categoryRelativePath($category, $categories));
            $body = trim((string) $category->description)."\n\n".$posts
                ->map(fn (CmsPost $post): string => '- ['.$post->title.']('.$this->urlBuilder->postPath($post).')')
                ->implode("\n");

            yield new CmsSearchDocumentData(
                sourceType: 'category',
                sourceKey: (string) $category->id,
                sourceId: (int) $category->id,
                locale: $locale,
                title: (string) $category->title,
                slug: (string) $category->slug,
                summary: $category->description,
                canonicalPath: $canonicalPath,
                canonicalUrl: url($canonicalPath),
                markdownPath: $markdownPath,
                markdownUrl: url($markdownPath),
                markdown: $this->normalizer->document((string) $category->title, $category->description, $body, url($canonicalPath)),
                plainText: $this->normalizer->plain($category->title.' '.$category->description.' '.$body),
                metadata: ['post_count' => $posts->count()],
                sourceUpdatedAt: $category->updated_at,
            );
        }
    }

    /**
     * @return iterable<int, CmsSearchDocumentData>
     */
    private function categoryIndexDocuments(string $locale): iterable
    {
        if (! Schema::hasTable('cms_categories')) {
            return;
        }

        $categories = CmsCategory::query()->where('locale', $locale)->where('is_active', true)->orderBy('sort_order')->orderBy('title')->get();

        if ($categories->isEmpty()) {
            return;
        }

        $title = public_text('taxonomy.category_index_title', 'Categories', $locale);
        $summary = public_text('taxonomy.category_index_lead', 'Browse all categories.', $locale);
        $canonicalPath = $this->urlBuilder->categoryIndexPath($locale);
        $markdownPath = $this->markdownPath($locale, 'blogs/categories');
        $categoryMap = $categories->keyBy('id');
        $body = $categories
            ->map(fn (CmsCategory $category): string => '- ['.$category->title.']('.$this->urlBuilder->categoryPath($category, $categoryMap).')'.($category->description ? ' - '.$this->normalizer->plain($category->description) : ''))
            ->implode("\n");

        yield new CmsSearchDocumentData(
            sourceType: 'category_index',
            sourceKey: 'category-index',
            sourceId: null,
            locale: $locale,
            title: $title,
            slug: 'categories',
            summary: $summary,
            canonicalPath: $canonicalPath,
            canonicalUrl: url($canonicalPath),
            markdownPath: $markdownPath,
            markdownUrl: url($markdownPath),
            markdown: $this->normalizer->document($title, $summary, $body, url($canonicalPath)),
            plainText: $this->normalizer->plain($title.' '.$summary.' '.$body),
            metadata: ['category_count' => $categories->count()],
        );
    }

    /**
     * @return iterable<int, CmsSearchDocumentData>
     */
    private function tagDocuments(string $locale): iterable
    {
        if (! Schema::hasTable('cms_tags')) {
            return;
        }

        $tags = CmsTag::query()->where('locale', $locale)->where('is_active', true)->orderBy('title')->get();

        foreach ($tags as $tag) {
            $posts = $this->publishedPosts($locale)
                ->whereHas('tags', fn (Builder $query) => $query->whereKey($tag->id))
                ->limit(50)
                ->get(['id', 'title', 'slug', 'excerpt']);

            if ($posts->isEmpty()) {
                continue;
            }

            $canonicalPath = $this->urlBuilder->tagPath($tag);
            $markdownPath = $this->markdownPath($locale, 'blogs/tags/'.$tag->slug);
            $body = trim((string) $tag->description)."\n\n".$posts
                ->map(fn (CmsPost $post): string => '- ['.$post->title.']('.$this->urlBuilder->postPath($post).')')
                ->implode("\n");

            yield new CmsSearchDocumentData(
                sourceType: 'tag',
                sourceKey: (string) $tag->id,
                sourceId: (int) $tag->id,
                locale: $locale,
                title: (string) $tag->title,
                slug: (string) $tag->slug,
                summary: $tag->description,
                canonicalPath: $canonicalPath,
                canonicalUrl: url($canonicalPath),
                markdownPath: $markdownPath,
                markdownUrl: url($markdownPath),
                markdown: $this->normalizer->document((string) $tag->title, $tag->description, $body, url($canonicalPath)),
                plainText: $this->normalizer->plain($tag->title.' '.$tag->description.' '.$body),
                metadata: ['post_count' => $posts->count()],
                sourceUpdatedAt: $tag->updated_at,
            );
        }
    }

    /**
     * @return iterable<int, CmsSearchDocumentData>
     */
    private function tagIndexDocuments(string $locale): iterable
    {
        if (! Schema::hasTable('cms_tags')) {
            return;
        }

        $tags = CmsTag::query()->where('locale', $locale)->where('is_active', true)->orderBy('title')->get();

        if ($tags->isEmpty()) {
            return;
        }

        $title = public_text('taxonomy.tag_index_title', 'Tags', $locale);
        $summary = public_text('taxonomy.tag_index_lead', 'Browse all tags.', $locale);
        $canonicalPath = $this->urlBuilder->tagIndexPath($locale);
        $markdownPath = $this->markdownPath($locale, 'blogs/tags');
        $body = $tags
            ->map(fn (CmsTag $tag): string => '- ['.$tag->title.']('.$this->urlBuilder->tagPath($tag).')'.($tag->description ? ' - '.$this->normalizer->plain($tag->description) : ''))
            ->implode("\n");

        yield new CmsSearchDocumentData(
            sourceType: 'tag_index',
            sourceKey: 'tag-index',
            sourceId: null,
            locale: $locale,
            title: $title,
            slug: 'tags',
            summary: $summary,
            canonicalPath: $canonicalPath,
            canonicalUrl: url($canonicalPath),
            markdownPath: $markdownPath,
            markdownUrl: url($markdownPath),
            markdown: $this->normalizer->document($title, $summary, $body, url($canonicalPath)),
            plainText: $this->normalizer->plain($title.' '.$summary.' '.$body),
            metadata: ['tag_count' => $tags->count()],
        );
    }

    /**
     * @return iterable<int, CmsSearchDocumentData>
     */
    private function docsDocuments(string $locale): iterable
    {
        if (! $this->docsModuleActive()) {
            return;
        }

        $collections = CmsDocCollection::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        if ($collections->isEmpty()) {
            return;
        }

        $docsPath = $this->docsPath($locale);
        $docsBody = $collections->map(fn (CmsDocCollection $collection): string => '- ['.$collection->name.']('.$this->docsPath($locale, $collection->slug).')')->implode("\n");

        yield new CmsSearchDocumentData('docs_index', 'docs-index', null, $locale, public_text('docs.index.title', 'Documentation', $locale), 'docs', public_text('docs.index.lead', 'Browse documentation collections.', $locale), $docsPath, url($docsPath), $this->markdownPath($locale, 'docs'), url($this->markdownPath($locale, 'docs')), $this->normalizer->document(public_text('docs.index.title', 'Documentation', $locale), null, $docsBody, url($docsPath)), $this->normalizer->plain($docsBody));

        foreach ($collections as $collection) {
            $collectionPath = $this->docsPath($locale, $collection->slug);
            $versions = $collection->versions()->where('is_active', true)->orderByDesc('is_default')->orderBy('sort_order')->get();
            $collectionBody = $versions->map(fn (CmsDocVersion $version): string => '- ['.$version->label.']('.$this->docsPath($locale, $collection->slug, $version->slug).')')->implode("\n");

            yield new CmsSearchDocumentData('docs_collection', (string) $collection->id, (int) $collection->id, $locale, (string) $collection->name, (string) $collection->slug, $collection->description, $collectionPath, url($collectionPath), $this->markdownPath($locale, 'docs/'.$collection->slug), url($this->markdownPath($locale, 'docs/'.$collection->slug)), $this->normalizer->document((string) $collection->name, $collection->description, $collectionBody, url($collectionPath)), $this->normalizer->plain($collection->name.' '.$collection->description.' '.$collectionBody), ['version_count' => $versions->count()], sourceUpdatedAt: $collection->updated_at);

            foreach ($versions as $version) {
                $pages = $version->pages()->where('status', 'published')->where('locale', $locale)->orderBy('sort_order')->orderBy('title')->get();

                if ($pages->isEmpty()) {
                    continue;
                }

                $versionPath = $this->docsPath($locale, $collection->slug, $version->slug);
                $versionBody = $pages->map(fn (CmsDocPage $page): string => '- ['.$page->title.']('.$this->docsPath($locale, $collection->slug, $version->slug, $page->path).')')->implode("\n");
                yield new CmsSearchDocumentData('docs_version', (string) $version->id, (int) $version->id, $locale, (string) $version->label, (string) $version->slug, $version->description, $versionPath, url($versionPath), $this->markdownPath($locale, 'docs/'.$collection->slug.'/'.$version->slug), url($this->markdownPath($locale, 'docs/'.$collection->slug.'/'.$version->slug)), $this->normalizer->document((string) $version->label, $version->description, $versionBody, url($versionPath)), $this->normalizer->plain($version->label.' '.$version->description.' '.$versionBody), ['page_count' => $pages->count(), 'collection' => $collection->slug], sourceUpdatedAt: $version->updated_at);

                foreach ($pages as $page) {
                    $path = $this->docsPath($locale, $collection->slug, $version->slug, $page->path);
                    $markdownPath = $this->markdownPath($locale, 'docs/'.$collection->slug.'/'.$version->slug.'/'.$page->path);
                    $rendered = $this->docsMarkdownRenderer->render((string) $page->body, $locale);

                    yield new CmsSearchDocumentData('doc_page', (string) $page->id, (int) $page->id, $locale, (string) $page->title, (string) $page->path, $page->seo_description, $path, url($path), $markdownPath, url($markdownPath), $this->normalizer->document((string) $page->title, $page->seo_description, (string) $page->body, url($path)), (string) ($page->plain_text ?: $rendered['plain_text']), ['collection' => $collection->slug, 'version' => $version->slug, 'path' => $page->path], noindex: (bool) $page->noindex, publishedAt: $page->published_at, sourceUpdatedAt: $page->updated_at);
                }
            }
        }
    }

    private function docsModuleActive(): bool
    {
        return Schema::hasTable('cms_modules')
            && Schema::hasTable('cms_doc_collections')
            && Schema::hasTable('cms_doc_versions')
            && Schema::hasTable('cms_doc_pages')
            && CmsModule::query()->where('key', 'docs')->where('status', 'active')->exists();
    }

    /**
     * @return Builder<CmsPost>
     */
    private function publishedPosts(string $locale): Builder
    {
        return CmsPost::query()
            ->where('locale', $locale)
            ->where('status', 'published')
            ->where('is_searchable', true)
            ->where('noindex', false)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    private function markdownPath(string $locale, string $path): string
    {
        return $this->prefixedPath($locale, 'markdown/'.trim($path, '/'));
    }

    private function docsPath(string $locale, ?string $collection = null, ?string $version = null, ?string $path = null): string
    {
        $relativePath = 'docs/'.collect([$collection, $version, $path])->filter()->map(fn (string $part): string => trim($part, '/'))->implode('/');

        return $this->prefixedPath($locale, $relativePath);
    }

    private function prefixedPath(string $locale, string $relativePath): string
    {
        return '/'.trim($locale, '/').'/'.trim($relativePath, '/');
    }

    private function pathWithoutLocale(?string $path, string $locale): string
    {
        $path = '/'.trim((string) $path, '/');
        $prefix = rtrim($this->languageSettings->pathPrefix($locale), '/');

        if ($prefix !== '' && str_starts_with($path, $prefix.'/')) {
            return trim(substr($path, strlen($prefix)), '/') ?: 'home';
        }

        if ($prefix !== '' && $path === $prefix) {
            return 'home';
        }

        return trim($path, '/') ?: 'home';
    }

    /**
     * @param  Collection<int, CmsCategory>  $categories
     */
    private function categoryRelativePath(CmsCategory $category, $categories): string
    {
        return $this->urlBuilder->categoryRelativePath($category, $categories);
    }
}
