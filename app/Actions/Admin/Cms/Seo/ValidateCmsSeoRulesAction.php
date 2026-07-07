<?php

namespace App\Actions\Admin\Cms\Seo;

use App\Support\Cms\Seo\CmsSeoSettings;

class ValidateCmsSeoRulesAction
{
    public function __construct(private readonly CmsSeoSettings $settings) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{errors: array<int, string>, warnings: array<int, string>}
     */
    public function handle(array $data, string $type = 'page', bool $publishing = false): array
    {
        $settings = $this->settings->values();
        $errors = [];
        $warnings = [];

        $this->validateRequiredOnPublish($data, $settings, $publishing, $errors);
        $this->validateLength('title', __('cms_admin_ui.seo.fields.h1'), $data['title'] ?? null, (int) $settings['seo_h1_min_length'], (int) $settings['seo_h1_max_length'], $warnings);
        $this->validateLength('slug', __('cms_admin_ui.seo.fields.slug'), $data['slug'] ?? null, (int) $settings['seo_slug_min_length'], (int) $settings['seo_slug_max_length'], $errors, required: true);
        $this->validateSlug($data['slug'] ?? null, $errors);
        $this->validateLength('seo_title', __('cms_admin_ui.seo.fields.meta_title'), $data['seo_title'] ?? null, (int) $settings['seo_meta_title_min_length'], (int) $settings['seo_meta_title_max_length'], $warnings);
        $this->validateLength('seo_description', __('cms_admin_ui.seo.fields.meta_description'), $data['seo_description'] ?? null, (int) $settings['seo_meta_description_min_length'], (int) $settings['seo_meta_description_max_length'], $warnings);
        $this->validateUrlLength($data['canonical_url'] ?? null, (int) $settings['seo_url_max_length'], $warnings);
        $this->validateJsonLd($data['structured_data_extra'] ?? null, $errors);
        $this->validateHeadings($data, $settings, $warnings);
        $this->validateContentQuality($data, $settings, $publishing, $warnings);

        if ($type === 'post' && (bool) $settings['seo_require_og_image_for_posts'] && blank($data['og_image_path'] ?? null) && blank($data['featured_media_asset_id'] ?? null)) {
            $warnings[] = __('cms_admin_ui.seo.messages.og_image_missing');
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int|bool|string>  $settings
     * @param  array<int, string>  $errors
     */
    private function validateRequiredOnPublish(array $data, array $settings, bool $publishing, array &$errors): void
    {
        if (! $publishing) {
            return;
        }

        if ((bool) $settings['seo_require_meta_title_on_publish'] && blank($data['seo_title'] ?? null)) {
            $errors[] = __('cms_admin_ui.seo.messages.meta_title_required');
        }

        if ((bool) $settings['seo_require_meta_description_on_publish'] && blank($data['seo_description'] ?? null)) {
            $errors[] = __('cms_admin_ui.seo.messages.meta_description_required');
        }

        if ((bool) $settings['seo_require_json_ld'] && blank($data['structured_data_extra'] ?? null)) {
            $errors[] = __('cms_admin_ui.seo.messages.json_ld_required');
        }
    }

    /**
     * @param  array<int, string>  $messages
     */
    private function validateLength(string $field, string $label, mixed $value, ?int $min, int $max, array &$messages, bool $required = false): void
    {
        $text = trim((string) $value);

        if ($text === '') {
            if ($required) {
                $messages[] = __('cms_admin_ui.seo.messages.required', ['field' => $label]);
            }

            return;
        }

        $length = mb_strlen($text);

        if ($min !== null && $length < $min) {
            $messages[] = __('cms_admin_ui.seo.messages.too_short', ['field' => $label, 'min' => $min, 'current' => $length]);
        }

        if ($length > $max) {
            $messages[] = __('cms_admin_ui.seo.messages.too_long', ['field' => $label, 'max' => $max, 'current' => $length]);
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function validateSlug(mixed $value, array &$errors): void
    {
        $slug = trim((string) $value);

        if ($slug === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1) {
            return;
        }

        $errors[] = __('cms_admin_ui.seo.messages.slug_invalid');
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function validateUrlLength(mixed $value, int $max, array &$warnings): void
    {
        $url = trim((string) $value);

        if ($url !== '' && mb_strlen($url) > $max) {
            $warnings[] = __('cms_admin_ui.seo.messages.too_long', ['field' => __('cms_admin_ui.seo.fields.url'), 'max' => $max, 'current' => mb_strlen($url)]);
        }
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function validateJsonLd(mixed $value, array &$errors): void
    {
        $json = trim((string) $value);

        if ($json === '') {
            return;
        }

        json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = __('cms_admin_ui.seo.messages.json_ld_invalid');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int|bool|string>  $settings
     * @param  array<int, string>  $warnings
     */
    private function validateHeadings(array $data, array $settings, array &$warnings): void
    {
        $headings = $this->headingsFromData($data);
        $h1Count = collect($headings)->where('level', 1)->count();

        if ((bool) $settings['seo_require_single_h1'] && $h1Count !== 1) {
            $warnings[] = __('cms_admin_ui.seo.messages.h1_count_invalid', ['count' => $h1Count]);
        }

        foreach ($headings as $heading) {
            $max = match ((int) $heading['level']) {
                1 => (int) $settings['seo_h1_max_length'],
                2 => (int) $settings['seo_h2_max_length'],
                3 => (int) $settings['seo_h3_max_length'],
                default => null,
            };

            if ($max !== null && mb_strlen($heading['text']) > $max) {
                $warnings[] = __('cms_admin_ui.seo.messages.heading_too_long', [
                    'field' => 'H'.$heading['level'],
                    'max' => $max,
                    'current' => mb_strlen($heading['text']),
                ]);
            }
        }

        if ((bool) $settings['seo_require_valid_heading_hierarchy']) {
            $this->validateHeadingHierarchy($headings, $warnings);
        }
    }

    /**
     * @param  array<int, array{level: int, text: string}>  $headings
     * @param  array<int, string>  $warnings
     */
    private function validateHeadingHierarchy(array $headings, array &$warnings): void
    {
        $previousLevel = null;

        foreach ($headings as $heading) {
            $level = (int) $heading['level'];

            if ($previousLevel !== null && $level > $previousLevel + 1) {
                $warnings[] = __('cms_admin_ui.seo.messages.heading_hierarchy_invalid');

                return;
            }

            $previousLevel = $level;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int|bool|string>  $settings
     * @param  array<int, string>  $warnings
     */
    private function validateContentQuality(array $data, array $settings, bool $publishing, array &$warnings): void
    {
        if (! $publishing) {
            return;
        }

        $minimumWords = (int) ($settings['seo_content_min_words'] ?? 0);

        if ($minimumWords <= 0) {
            return;
        }

        $wordCount = $this->wordCount($this->textFromData($data));

        if ($wordCount < $minimumWords) {
            $warnings[] = __('cms_admin_ui.seo.messages.thin_content', [
                'current' => $wordCount,
                'min' => $minimumWords,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{level: int, text: string}>
     */
    private function headingsFromData(array $data): array
    {
        $headings = [];
        $title = trim((string) ($data['title'] ?? ''));

        if ($title !== '') {
            $headings[] = ['level' => 1, 'text' => $title];
        }

        foreach ($this->blocksFromData($data) as $block) {
            $heading = trim((string) ($block['title'] ?? $block['heading'] ?? ''));

            if ($heading === '') {
                continue;
            }

            $headings[] = [
                'level' => $this->headingLevel($block['heading_level'] ?? $block['level'] ?? 2),
                'text' => $heading,
            ];
        }

        return $headings;
    }

    private function headingLevel(mixed $value): int
    {
        $level = is_string($value) && preg_match('/h([1-6])/i', $value, $matches) === 1
            ? (int) $matches[1]
            : (int) $value;

        return min(6, max(1, $level ?: 2));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function textFromData(array $data): string
    {
        $parts = [
            $data['title'] ?? null,
            $data['short_description'] ?? null,
            $data['excerpt'] ?? null,
            $data['description'] ?? null,
        ];

        foreach ($this->blocksFromData($data) as $block) {
            $parts[] = $this->textFromBlock($block);
        }

        if (is_array($data['template_data'] ?? null)) {
            $parts[] = $this->textFromTemplateData($data['template_data']);
        }

        return trim(implode(' ', array_filter(array_map(static fn (mixed $part): string => trim(strip_tags((string) $part)), $parts))));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function textFromTemplateData(array $data): string
    {
        $parts = [];

        foreach ($data as $value) {
            if (is_string($value)) {
                $parts[] = $value;
            } elseif (is_array($value)) {
                $parts[] = $this->textFromTemplateData($value);
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function textFromBlock(array $block): string
    {
        $parts = [];
        $textKeys = ['title', 'heading', 'subtitle', 'eyebrow', 'text', 'caption', 'label', 'source', 'summary', 'empty_text'];

        foreach ($textKeys as $key) {
            if (isset($block[$key]) && is_scalar($block[$key])) {
                $parts[] = (string) $block[$key];
            }
        }

        foreach (['items', 'buttons', 'links'] as $key) {
            foreach ((array) ($block[$key] ?? []) as $item) {
                if (is_array($item)) {
                    $parts[] = $this->textFromBlock($item);
                }
            }
        }

        return implode(' ', $parts);
    }

    private function wordCount(string $text): int
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);

        return count($matches[0] ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function blocksFromData(array $data): array
    {
        $blocks = collect((array) ($data['content_blocks'] ?? []))
            ->map(fn (mixed $block): mixed => $this->normalizeBlock($block));

        if (isset($data['sections']) && is_array($data['sections'])) {
            $blocks = $blocks->merge(collect($data['sections'])->flatten(1)
                ->flatMap(fn (mixed $section): array => is_array($section) ? ($section['placements'] ?? []) : [])
                ->map(fn (mixed $placement): mixed => is_array($placement) ? $this->normalizeBlock($placement['block'] ?? []) : [])
            );
        }

        return $blocks
            ->filter(fn (mixed $block): bool => is_array($block))
            ->values()
            ->all();
    }

    private function normalizeBlock(mixed $block): mixed
    {
        if (! is_array($block)) {
            return $block;
        }

        if (isset($block['content']) && is_array($block['content'])) {
            return array_merge(['type' => $block['type'] ?? null], $block['content']);
        }

        return $block;
    }
}
