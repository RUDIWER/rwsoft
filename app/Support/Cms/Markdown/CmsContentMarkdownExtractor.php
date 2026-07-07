<?php

namespace App\Support\Cms\Markdown;

use Illuminate\Support\Arr;

class CmsContentMarkdownExtractor
{
    public function __construct(private readonly CmsMarkdownNormalizer $normalizer) {}

    /**
     * @param  array<int, mixed>  $sections
     */
    public function fromSections(array $sections): string
    {
        $parts = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            foreach ((array) ($section['placements'] ?? []) as $placement) {
                if (is_array($placement)) {
                    $parts[] = $this->fromPlacement($placement);
                }
            }
        }

        return $this->normalizer->normalize(implode("\n\n", array_filter($parts)));
    }

    /**
     * @param  array<int, mixed>  $blocks
     */
    public function fromBlocks(array $blocks): string
    {
        $parts = [];

        foreach ($blocks as $block) {
            if (is_array($block)) {
                $parts[] = $this->fromBlock($block);
            }
        }

        return $this->normalizer->normalize(implode("\n\n", array_filter($parts)));
    }

    /**
     * @param  array<string, mixed>  $placement
     */
    private function fromPlacement(array $placement): string
    {
        $parts = [];
        $block = $placement['block'] ?? null;

        if (is_array($block)) {
            $parts[] = $this->fromBlock($block);
        }

        foreach ((array) ($placement['slots'] ?? []) as $slot) {
            if (is_array($slot)) {
                $parts[] = $this->fromBlocks((array) ($slot['blocks'] ?? []));
                $parts[] = $this->fromSections((array) ($slot['sections'] ?? []));
            }
        }

        return trim(implode("\n\n", array_filter($parts)));
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function fromBlock(array $block): string
    {
        $type = (string) ($block['renderer_key'] ?? $block['type'] ?? '');

        return match ($type) {
            'text', 'feature_card' => $this->titleText($block, 'title', 'text'),
            'rich_text' => $this->titleText($block, 'title', 'html'),
            'markdown_text' => $this->markdownText($block),
            'quote', 'testimonial' => $this->quote($block),
            'stats' => trim(implode(' ', array_filter([$block['value'] ?? null, $block['suffix'] ?? null, $block['label'] ?? null]))),
            'accordion', 'tabs', 'carousel' => $this->items($block, 'title', 'text'),
            'faq' => $this->items($block, 'question', 'answer'),
            'steps' => $this->listItems($block, ordered: true),
            'icon_list' => $this->listItems($block),
            'image' => $this->image($block),
            'button', 'site_button', 'site_link' => $this->link($block),
            'dynamic_field' => $this->dynamicField($block),
            'content_slot' => trim(implode("\n\n", [
                $this->fromBlocks((array) ($block['blocks'] ?? [])),
                $this->fromSections((array) ($block['sections'] ?? [])),
            ])),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function titleText(array $block, string $titleKey, string $textKey): string
    {
        $title = $this->normalizer->plain($block[$titleKey] ?? '');
        $text = $textKey === 'html'
            ? $this->normalizer->plain($block[$textKey] ?? '')
            : $this->normalizer->plain($block[$textKey] ?? '');

        return trim(implode("\n\n", array_filter([
            $title !== '' ? '## '.$title : null,
            $text,
        ])));
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function markdownText(array $block): string
    {
        $title = $this->normalizer->plain($block['title'] ?? '');
        $markdown = trim((string) ($block['markdown'] ?? ''));

        return trim(implode("\n\n", array_filter([
            $title !== '' ? '## '.$title : null,
            $markdown,
        ])));
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function quote(array $block): string
    {
        $text = $this->normalizer->plain($block['text'] ?? '');
        $source = $this->normalizer->plain($block['source'] ?? '');

        if ($text === '') {
            return '';
        }

        return '> '.$text.($source !== '' ? "\n> \n> - ".$source : '');
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function items(array $block, string $titleKey, string $textKey): string
    {
        return collect((array) ($block['items'] ?? []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item) use ($titleKey, $textKey): string {
                $title = $this->normalizer->plain($item[$titleKey] ?? '');
                $text = $this->normalizer->plain($item[$textKey] ?? '');

                return trim(implode("\n\n", array_filter([
                    $title !== '' ? '## '.$title : null,
                    $text,
                ])));
            })
            ->filter()
            ->implode("\n\n");
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function listItems(array $block, bool $ordered = false): string
    {
        return collect((array) ($block['items'] ?? []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->map(function (array $item, int $index) use ($ordered): string {
                $title = $this->normalizer->plain($item['title'] ?? '');
                $text = $this->normalizer->plain($item['text'] ?? '');
                $label = trim($title.($title !== '' && $text !== '' ? ': ' : '').$text);

                return $label === '' ? '' : ($ordered ? ($index + 1).'. ' : '- ').$label;
            })
            ->filter()
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function image(array $block): string
    {
        $caption = $this->normalizer->plain($block['caption'] ?? Arr::get($block, 'media.caption', ''));
        $alt = $this->normalizer->plain(Arr::get($block, 'media.alt_text', $caption));
        $url = (string) Arr::get($block, 'media.url', '');

        if ($url === '') {
            return $caption;
        }

        return '!['.$alt.']('.$url.')'.($caption !== '' ? "\n\n".$caption : '');
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function link(array $block): string
    {
        $label = $this->normalizer->plain($block['label'] ?? $block['link_label'] ?? '');
        $url = trim((string) ($block['url'] ?? $block['link_url'] ?? ''));

        return $label !== '' && $url !== '' ? '['.$label.']('.$url.')' : $label;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function dynamicField(array $block): string
    {
        $title = $this->normalizer->plain($block['title'] ?? '');
        $value = $block['value'] ?? null;

        if (is_array($value)) {
            $value = array_is_list($value)
                ? implode(', ', array_map(fn (mixed $item): string => $this->normalizer->plain($item), $value))
                : '';
        }

        $text = $this->normalizer->plain($value);

        return trim(implode("\n\n", array_filter([
            $title !== '' ? '## '.$title : null,
            $text,
        ])));
    }
}
