<?php

namespace App\Support\Cms;

class CmsHtmlSanitizer
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'ol' => ['start'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
    ];

    /**
     * @var array<int, string>
     */
    private const ALLOWED_TAGS = [
        'a',
        'b',
        'blockquote',
        'br',
        'code',
        'div',
        'em',
        'figcaption',
        'figure',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'hr',
        'i',
        'img',
        'li',
        'ol',
        'p',
        'pre',
        's',
        'span',
        'strong',
        'table',
        'tbody',
        'td',
        'th',
        'thead',
        'tr',
        'u',
        'ul',
    ];

    /**
     * @var array<int, string>
     */
    private const REMOVE_WITH_CONTENTS = [
        'applet',
        'button',
        'canvas',
        'embed',
        'form',
        'iframe',
        'input',
        'math',
        'meta',
        'object',
        'script',
        'select',
        'style',
        'svg',
        'textarea',
    ];

    public function clean(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $html = trim((string) $value);

        if ($html === '') {
            return null;
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div data-rw-root="1">'.$html.'</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementsByTagName('div')->item(0);

        if (! $root instanceof \DOMElement) {
            return null;
        }

        $this->sanitizeChildren($root);

        $clean = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            if ($child instanceof \DOMNode) {
                $clean .= $document->saveHTML($child) ?: '';
            }
        }

        $clean = trim($clean);

        return $clean !== '' ? $clean : null;
    }

    private function sanitizeChildren(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof \DOMNode) {
                continue;
            }

            if ($child instanceof \DOMElement) {
                $this->sanitizeElement($child);
            } elseif (! in_array($child->nodeType, [XML_TEXT_NODE, XML_CDATA_SECTION_NODE], true)) {
                $node->removeChild($child);
            }
        }
    }

    private function sanitizeElement(\DOMElement $element): void
    {
        $tagName = mb_strtolower($element->tagName);

        if (in_array($tagName, self::REMOVE_WITH_CONTENTS, true)) {
            $element->parentNode?->removeChild($element);

            return;
        }

        if (! in_array($tagName, self::ALLOWED_TAGS, true)) {
            $this->unwrapElement($element);

            return;
        }

        $this->sanitizeAttributes($element, $tagName);
        $this->sanitizeChildren($element);
    }

    private function unwrapElement(\DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent instanceof \DOMNode) {
            return;
        }

        while ($element->firstChild instanceof \DOMNode) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function sanitizeAttributes(\DOMElement $element, string $tagName): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (! $attribute instanceof \DOMAttr) {
                continue;
            }

            $name = mb_strtolower($attribute->name);
            $value = trim($attribute->value);
            $allowed = self::ALLOWED_ATTRIBUTES[$tagName] ?? [];

            if (! in_array($name, $allowed, true) || ! $this->safeAttributeValue($tagName, $name, $value)) {
                $element->removeAttributeNode($attribute);
            }
        }

        if ($tagName === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', $this->safeRel($element->getAttribute('rel')));
        }
    }

    private function safeAttributeValue(string $tagName, string $name, string $value): bool
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            return false;
        }

        if ($tagName === 'a' && $name === 'href') {
            return $this->safeHref($value);
        }

        if ($tagName === 'img' && $name === 'src') {
            return $this->safeMediaSrc($value);
        }

        if ($name === 'target') {
            return in_array($value, ['_self', '_blank'], true);
        }

        if ($name === 'loading') {
            return in_array($value, ['lazy', 'eager'], true);
        }

        if ($name === 'rel') {
            return $this->safeRel($value) !== '';
        }

        if (in_array($name, ['colspan', 'rowspan', 'start', 'width', 'height'], true)) {
            return preg_match('/^[1-9][0-9]{0,4}$/', $value) === 1;
        }

        if ($name === 'scope') {
            return in_array($value, ['col', 'row'], true);
        }

        return true;
    }

    private function safeHref(string $value): bool
    {
        $href = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($href === '' || preg_match('/[\x00-\x20\x7F]/', $href) === 1) {
            return false;
        }

        if (str_starts_with($href, '#')) {
            return true;
        }

        if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            return true;
        }

        if (preg_match('/^https?:\/\//i', $href) === 1) {
            return true;
        }

        if (preg_match('/^mailto:([^?]+)(\?.*)?$/i', $href, $matches) === 1) {
            return filter_var($matches[1], FILTER_VALIDATE_EMAIL) !== false;
        }

        if (preg_match('/^tel:\+?[0-9][0-9\s().-]{4,24}$/i', $href) === 1) {
            return true;
        }

        return false;
    }

    private function safeMediaSrc(string $value): bool
    {
        $src = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($src === '' || preg_match('/[\x00-\x20\x7F]/', $src) === 1) {
            return false;
        }

        if (str_starts_with($src, '/') && ! str_starts_with($src, '//')) {
            return true;
        }

        return preg_match('/^https?:\/\//i', $src) === 1;
    }

    private function safeRel(string $value): string
    {
        return collect(preg_split('/\s+/', $value) ?: [])
            ->merge(['noopener', 'noreferrer'])
            ->map(fn (string $part): string => preg_replace('/[^A-Za-z0-9_-]/', '', $part) ?: '')
            ->filter()
            ->unique()
            ->implode(' ');
    }
}
