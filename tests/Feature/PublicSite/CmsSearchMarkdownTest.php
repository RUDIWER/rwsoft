<?php

namespace Tests\Feature\PublicSite;

use App\Models\Cms\CmsSearchDocument;

class CmsSearchMarkdownTest extends PublicCmsTestCase
{
    public function test_raw_markdown_endpoint_returns_only_public_indexable_document(): void
    {
        $this->storeSetting('general', 'default_locale', 'sr');
        $document = $this->createSearchDocument([
            'title' => 'Searchable page',
            'slug' => 'searchable-page',
            'locale' => 'sr',
            'markdown_path' => '/sr/markdown/pages/searchable-page',
            'markdown_url' => url('/sr/markdown/pages/searchable-page'),
            'markdown' => "# Searchable page\n\nVisible markdown body.",
        ]);
        $this->createSearchDocument([
            'title' => 'Hidden page',
            'slug' => 'hidden-page',
            'locale' => 'sr',
            'markdown_path' => '/sr/markdown/pages/hidden-page',
            'markdown_url' => url('/sr/markdown/pages/hidden-page'),
            'markdown' => "# Hidden page\n\nHidden markdown body.",
            'noindex' => true,
        ]);

        $this
            ->get('/sr/markdown/pages/searchable-page')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->assertHeader('Link', '<'.$document->canonical_url.'>; rel="canonical"')
            ->assertSee('Visible markdown body.', false);

        $this->get('/sr/markdown/pages/hidden-page')->assertNotFound();
    }

    public function test_search_endpoint_filters_private_documents(): void
    {
        $this->storeSetting('general', 'default_locale', 'sq');
        $publicDocument = $this->createSearchDocument([
            'title' => 'Needle public result',
            'locale' => 'sq',
            'plain_text' => 'needlepublicresult public body',
        ]);
        $publicDocument->chunks()->create([
            'chunk_index' => 0,
            'heading' => 'Needle public result',
            'anchor' => 'needle-public-result',
            'content_markdown' => 'needlepublicresult public body',
            'content_text' => 'needlepublicresult public body',
            'token_count' => 2,
            'metadata' => [],
        ]);
        $privateDocument = $this->createSearchDocument([
            'title' => 'Needle private result',
            'locale' => 'sq',
            'plain_text' => 'needlepublicresult private body',
            'is_searchable' => false,
        ]);
        $privateDocument->chunks()->create([
            'chunk_index' => 0,
            'heading' => 'Needle private result',
            'anchor' => 'needle-private-result',
            'content_markdown' => 'needlepublicresult private body',
            'content_text' => 'needlepublicresult private body',
            'token_count' => 2,
            'metadata' => [],
        ]);

        $this
            ->getJson('/sq/search/results?q=needlepublicresult')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Needle public result'])
            ->assertJsonMissing(['title' => 'Needle private result']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSearchDocument(array $overrides = []): CmsSearchDocument
    {
        return CmsSearchDocument::query()->create(array_merge([
            'source_type' => 'page',
            'source_key' => uniqid('page-', true),
            'source_id' => null,
            'locale' => 'sr',
            'title' => 'Search page',
            'slug' => 'search-page',
            'summary' => null,
            'canonical_path' => '/search-page',
            'canonical_url' => url('/search-page'),
            'markdown_path' => '/sr/markdown/pages/search-page',
            'markdown_url' => url('/sr/markdown/pages/search-page'),
            'source_updated_at' => now(),
            'published_at' => now(),
            'is_active' => true,
            'is_searchable' => true,
            'noindex' => false,
            'markdown_hash' => hash('sha256', '# Search page'),
            'plain_text_hash' => hash('sha256', 'Search page'),
            'markdown' => '# Search page',
            'plain_text' => 'Search page',
            'metadata' => [],
            'indexed_at' => now(),
        ], $overrides));
    }
}
