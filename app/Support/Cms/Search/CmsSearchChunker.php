<?php

namespace App\Support\Cms\Search;

use Illuminate\Support\Str;

class CmsSearchChunker
{
    /**
     * @return array<int, array{chunk_index: int, heading: string|null, anchor: string|null, content_markdown: string, content_text: string, token_count: int, metadata: array<string, mixed>}>
     */
    public function chunk(string $markdown, ?int $maxLength = null): array
    {
        $maxLength ??= (int) config('cms_search.chunk_size', 3000);
        $markdown = trim(str_replace(["\r\n", "\r"], "\n", $markdown));

        if ($markdown === '') {
            return [];
        }

        $sections = preg_split('/(?=^#{1,6}\s+)/m', $markdown) ?: [$markdown];
        $chunks = [];
        $chunkIndex = 0;

        foreach ($sections as $section) {
            $section = trim($section);

            if ($section === '') {
                continue;
            }

            $heading = $this->extractHeading($section);
            $anchor = $heading !== null ? Str::slug($heading) : null;
            $buffer = '';
            $parts = preg_split("/\n\s*\n/", $section) ?: [$section];

            foreach ($parts as $part) {
                $part = trim($part);

                if ($part === '') {
                    continue;
                }

                $candidate = trim($buffer === '' ? $part : $buffer."\n\n".$part);

                if ($buffer !== '' && Str::length($candidate) > $maxLength) {
                    $chunks[] = $this->makeChunk($chunkIndex++, $heading, $anchor, $buffer);
                    $buffer = $part;

                    continue;
                }

                $buffer = $candidate;
            }

            if ($buffer !== '') {
                $chunks[] = $this->makeChunk($chunkIndex++, $heading, $anchor, $buffer);
            }
        }

        return $chunks;
    }

    private function extractHeading(string $markdown): ?string
    {
        if (! preg_match('/^#{1,6}\s+(.*)$/m', $markdown, $matches)) {
            return null;
        }

        return trim((string) $matches[1]);
    }

    /**
     * @return array{chunk_index: int, heading: string|null, anchor: string|null, content_markdown: string, content_text: string, token_count: int, metadata: array<string, mixed>}
     */
    private function makeChunk(int $chunkIndex, ?string $heading, ?string $anchor, string $contentMarkdown): array
    {
        $contentText = $this->toPlainText($contentMarkdown);

        return [
            'chunk_index' => $chunkIndex,
            'heading' => $heading,
            'anchor' => $anchor,
            'content_markdown' => $contentMarkdown,
            'content_text' => $contentText,
            'token_count' => str_word_count($contentText),
            'metadata' => [
                'heading' => $heading,
                'anchor' => $anchor,
            ],
        ];
    }

    public function toPlainText(string $markdown): string
    {
        $text = preg_replace('/```.*?```/s', ' ', $markdown) ?? $markdown;
        $text = preg_replace('/`([^`]*)`/', '$1', $text) ?? $text;
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/', ' ', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/^#{1,6}\s+/m', '', $text) ?? $text;
        $text = preg_replace('/[*_>~#-]+/', ' ', $text) ?? $text;
        $text = strip_tags($text);

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
