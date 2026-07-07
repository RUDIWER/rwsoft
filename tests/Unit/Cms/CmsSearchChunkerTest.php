<?php

namespace Tests\Unit\Cms;

use App\Support\Cms\Search\CmsSearchChunker;
use PHPUnit\Framework\TestCase;

class CmsSearchChunkerTest extends TestCase
{
    public function test_it_chunks_markdown_by_headings_and_extracts_plain_text(): void
    {
        $chunker = new CmsSearchChunker;

        $chunks = $chunker->chunk(<<<'MARKDOWN'
        # Introduction

        Welcome to the [public docs](/docs).

        ## Installation

        Run `php artisan install` before continuing.
        MARKDOWN, 80);

        $this->assertCount(2, $chunks);
        $this->assertSame(0, $chunks[0]['chunk_index']);
        $this->assertSame('Introduction', $chunks[0]['heading']);
        $this->assertSame('introduction', $chunks[0]['anchor']);
        $this->assertStringContainsString('Welcome to the public docs', $chunks[0]['content_text']);
        $this->assertSame('Installation', $chunks[1]['heading']);
        $this->assertStringContainsString('php artisan install', $chunks[1]['content_text']);
    }

    public function test_it_returns_no_chunks_for_empty_markdown(): void
    {
        $this->assertSame([], (new CmsSearchChunker)->chunk('   ', 80));
    }
}
