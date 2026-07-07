<?php

namespace App\Support\Cms\Markdown;

class CmsMarkdownNormalizer
{
    public function normalize(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $markdown = preg_replace('/[ \t]+$/m', '', $markdown) ?? $markdown;
        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown) ?? $markdown;

        return trim($markdown)."\n";
    }

    public function document(string $title, ?string $summary, string $body, ?string $canonicalUrl = null): string
    {
        $parts = ['# '.$this->plain($title)];

        if (is_string($summary) && trim($summary) !== '') {
            $parts[] = $this->plain($summary);
        }

        if (is_string($canonicalUrl) && trim($canonicalUrl) !== '') {
            $parts[] = 'Canonical: '.$canonicalUrl;
        }

        if (trim($body) !== '') {
            $parts[] = trim($body);
        }

        return $this->normalize(implode("\n\n", $parts));
    }

    public function plain(mixed $value): string
    {
        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
