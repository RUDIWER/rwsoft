<?php

namespace App\Support\Cms\Docs;

use App\Models\Cms\CmsMediaAsset;
use App\Support\PublicSite\PublicMediaUrl;
use Illuminate\Support\Str;

class CmsDocsMarkdownRenderer
{
    public function __construct(private readonly PublicMediaUrl $mediaUrl) {}

    /**
     * @return array{html: string, toc: array<int, array{level: int, id: string, title: string}>, plain_text: string}
     */
    public function render(?string $markdown, ?string $locale = null): array
    {
        $source = $this->replaceMediaTokens((string) $markdown, $locale);
        $admonitions = [];
        $source = $this->extractAdmonitions($source, $admonitions);
        $html = (string) Str::markdown($source, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        foreach ($admonitions as $placeholder => $admonitionHtml) {
            $html = str_replace('<p>'.$placeholder.'</p>', $admonitionHtml, $html);
            $html = str_replace($placeholder, $admonitionHtml, $html);
        }

        [$html, $toc] = $this->anchorHeadings($html);

        return [
            'html' => $html,
            'toc' => $toc,
            'plain_text' => trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        ];
    }

    /**
     * @param  array<string, string>  $admonitions
     */
    private function extractAdmonitions(string $source, array &$admonitions): string
    {
        return (string) preg_replace_callback(
            '/^:::(note|tip|info|warning|danger)(?:[ \t]+([^\n]+))?\R(.*?)\R:::[ \t]*$/ms',
            function (array $matches) use (&$admonitions): string {
                $type = (string) $matches[1];
                $title = trim((string) ($matches[2] ?? ''));
                $body = (string) ($matches[3] ?? '');
                $placeholder = 'CMS_DOC_ADMONITION_'.count($admonitions);
                $bodyHtml = (string) Str::markdown($body, [
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]);

                $admonitions[$placeholder] = view('public.system.docs.partials.admonition', [
                    'type' => $type,
                    'title' => $title !== '' ? $title : $this->defaultAdmonitionTitle($type),
                    'bodyHtml' => $bodyHtml,
                ])->render();

                return $placeholder;
            },
            $source
        );
    }

    private function replaceMediaTokens(string $source, ?string $locale): string
    {
        return (string) preg_replace_callback(
            '/!\[([^\]]*)\]\(media:(\d+)\)/',
            function (array $matches) use ($locale): string {
                $asset = CmsMediaAsset::query()->find((int) $matches[2]);
                $payload = $this->mediaUrl->payload($asset, $locale);

                if (! is_array($payload) || blank($payload['url'] ?? null)) {
                    return '';
                }

                $alt = trim((string) $matches[1]);
                $resolvedAlt = $alt !== '' ? $alt : (string) ($payload['alt_text'] ?? '');

                return '!['.$resolvedAlt.']('.(string) $payload['url'].')';
            },
            $source
        );
    }

    /**
     * @return array{0: string, 1: array<int, array{level: int, id: string, title: string}>}
     */
    private function anchorHeadings(string $html): array
    {
        $seen = [];
        $toc = [];

        $html = (string) preg_replace_callback(
            '/<h([2-4])>(.*?)<\/h\1>/s',
            function (array $matches) use (&$seen, &$toc): string {
                $level = (int) $matches[1];
                $innerHtml = (string) $matches[2];
                $title = trim(html_entity_decode(strip_tags($innerHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $baseId = Str::slug($title) ?: 'section';
                $id = $baseId;
                $counter = 2;

                while (isset($seen[$id])) {
                    $id = $baseId.'-'.$counter;
                    $counter++;
                }

                $seen[$id] = true;
                $toc[] = [
                    'level' => $level,
                    'id' => $id,
                    'title' => $title,
                ];

                return '<h'.$level.' id="'.e($id).'">'.$innerHtml.'</h'.$level.'>';
            },
            $html
        );

        return [$html, $toc];
    }

    private function defaultAdmonitionTitle(string $type): string
    {
        return match ($type) {
            'tip' => public_text('docs.admonitions.tip', 'Tip'),
            'info' => public_text('docs.admonitions.info', 'Info'),
            'warning' => public_text('docs.admonitions.warning', 'Warning'),
            'danger' => public_text('docs.admonitions.danger', 'Important'),
            default => public_text('docs.admonitions.note', 'Note'),
        };
    }
}
