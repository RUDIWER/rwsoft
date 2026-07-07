<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsPlaceableBlockRevision;
use App\Support\Cms\CmsBlockRegistry;
use App\Support\PublicSite\CmsBlockPayloadBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Throwable;

class RenderCmsPlacementPreviewsAction
{
    public function __construct(
        private readonly CmsBlockPayloadBuilder $blockPayloadBuilder,
        private readonly CmsBlockRegistry $blockRegistry,
    ) {}

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $site
     * @param  array<string, mixed>  $navigation
     * @return array<string, string>
     */
    public function handle(array $section, string $zone, string $locale, string $device, array $site, array $navigation, ?string $themeCssUrl = null): array
    {
        $device = in_array($device, ['desktop', 'tablet', 'mobile'], true) ? $device : 'desktop';
        $placements = collect($section['placements'] ?? [])
            ->filter(fn (mixed $placement): bool => is_array($placement))
            ->values();
        $placeableBlocks = $this->placeableBlocks($placements);
        $previews = [];

        foreach ($placements as $placement) {
            $uid = trim((string) ($placement['uid'] ?? ''));

            if ($uid === '') {
                continue;
            }

            $previews[$uid] = $this->renderPlacementPreview(
                $section,
                $placement,
                $zone,
                $locale,
                $device,
                $site,
                $navigation,
                $placeableBlocks,
                $themeCssUrl,
            );
        }

        return $previews;
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $placement
     * @param  array<string, mixed>  $site
     * @param  array<string, mixed>  $navigation
     * @param  Collection<int, CmsPlaceableBlock>  $placeableBlocks
     */
    private function renderPlacementPreview(
        array $section,
        array $placement,
        string $zone,
        string $locale,
        string $device,
        array $site,
        array $navigation,
        Collection $placeableBlocks,
        ?string $themeCssUrl,
    ): string {
        $block = is_array($placement['block'] ?? null) ? $placement['block'] : [];

        if ($this->isBlockedPreview($block, $placeableBlocks)) {
            return $this->placeholderDocument(
                __('cms_admin_ui.layouts.sections.live_preview_placeholder_title'),
                __('cms_admin_ui.layouts.sections.live_preview_code_disabled'),
                $locale,
                $device,
                $themeCssUrl,
            );
        }

        try {
            $payloads = $this->blockPayloadBuilder->handle(
                [$this->blockInput($block, $zone, $placeableBlocks)],
                templateContext: $this->previewTemplateContext($section),
                contentSlots: $this->previewContentSlots(),
                contentLocale: $locale,
            );
            $placementPayload = $this->placementPayload($placement, $this->previewBlockPayload($payloads[0] ?? $block));

            return $this->withoutScripts(View::make('admin.cms.partials.placement-preview-document', [
                'placement' => $placementPayload,
                'section' => $this->sectionPayload($section, $zone, [$placementPayload]),
                'contentItem' => $this->contentItem($section, $locale),
                'site' => $site,
                'navigation' => $navigation,
                'translations' => [],
                'themeCssUrl' => $themeCssUrl,
                'locale' => $locale,
                'previewDevice' => $device,
            ])->render());
        } catch (Throwable $exception) {
            report($exception);

            return $this->placeholderDocument(
                __('cms_admin_ui.layouts.sections.live_preview_placeholder_title'),
                __('cms_admin_ui.layouts.sections.live_preview_unavailable'),
                $locale,
                $device,
                $themeCssUrl,
            );
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $placements
     * @return Collection<int, CmsPlaceableBlock>
     */
    private function placeableBlocks(Collection $placements): Collection
    {
        $ids = $placements
            ->map(fn (array $placement): int => (int) data_get($placement, 'block.cms_placeable_block_id'))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return CmsPlaceableBlock::query()
            ->with('latestPublishedRevision')
            ->whereIn('id', $ids)
            ->where('status', 'published')
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  Collection<int, CmsPlaceableBlock>  $placeableBlocks
     */
    private function isBlockedPreview(array $block, Collection $placeableBlocks): bool
    {
        $placeableBlockId = (int) ($block['cms_placeable_block_id'] ?? 0);

        if ($placeableBlockId <= 0) {
            $rendererKey = $block['renderer_key'] ?? $block['type'] ?? null;
            $definition = is_string($rendererKey) && $rendererKey !== ''
                ? $this->blockRegistry->definition($rendererKey)
                : [];

            return ($definition['category'] ?? null) === 'code'
                || ($definition['rendering_mode'] ?? null) === 'raw_code_permissioned';
        }

        $placeableBlock = $placeableBlocks->get($placeableBlockId);
        $revision = $placeableBlock?->latestPublishedRevision;

        return ! $revision instanceof CmsPlaceableBlockRevision
            || ($revision->category ?: $placeableBlock->category) === 'code'
            || $revision->rendering_mode === 'raw_code_permissioned';
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  Collection<int, CmsPlaceableBlock>  $placeableBlocks
     * @return array<string, mixed>
     */
    private function blockInput(array $block, string $zone, Collection $placeableBlocks): array
    {
        $placeableBlock = $placeableBlocks->get((int) ($block['cms_placeable_block_id'] ?? 0));
        $revision = $placeableBlock?->latestPublishedRevision;

        return array_replace_recursive([
            'cms_placeable_block_id' => (int) ($block['cms_placeable_block_id'] ?? 0),
            'placeable_block_revision_id' => (int) ($block['placeable_block_revision_id'] ?? 0) ?: null,
            'placement_zone' => $zone,
            'renderer_key' => $revision?->renderer_key ?: $placeableBlock?->renderer_key,
        ], $block);
    }

    /**
     * @param  array<string, mixed>  $placement
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function placementPayload(array $placement, array $block): array
    {
        return [
            'id' => (int) ($placement['id'] ?? 0),
            'block_id' => (int) ($placement['block_id'] ?? 0),
            'sort_order' => (int) ($placement['sort_order'] ?? 0),
            'visible_mobile' => (bool) ($placement['visible_mobile'] ?? true),
            'visible_tablet' => (bool) ($placement['visible_tablet'] ?? true),
            'visible_desktop' => (bool) ($placement['visible_desktop'] ?? true),
            'mobile_span' => (int) ($placement['mobile_span'] ?? 12),
            'tablet_span' => (int) ($placement['tablet_span'] ?? 12),
            'desktop_span' => (int) ($placement['desktop_span'] ?? 12),
            'layout_config' => is_array($placement['layout_config'] ?? null) ? $placement['layout_config'] : [],
            'style_config' => is_array($placement['style_config'] ?? null) ? $placement['style_config'] : [],
            'published_style_revision' => is_array($placement['published_style_revision'] ?? null) ? $placement['published_style_revision'] : null,
            'height_mode' => $placement['height_mode'] ?? 'auto',
            'height_value' => $placement['height_value'] ?? null,
            'cache_strategy' => $placement['cache_strategy'] ?? 'inherit',
            'settings' => is_array($placement['settings'] ?? null) ? $placement['settings'] : [],
            'block' => $block,
        ];
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<int, array<string, mixed>>  $placements
     * @return array<string, mixed>
     */
    private function sectionPayload(array $section, string $zone, array $placements): array
    {
        return [
            'id' => (int) ($section['id'] ?? 0),
            'source' => 'admin_preview',
            'zone' => $zone,
            'name' => (string) ($section['name'] ?? ''),
            'sort_order' => 0,
            'visible_mobile' => (bool) ($section['visible_mobile'] ?? true),
            'visible_tablet' => (bool) ($section['visible_tablet'] ?? true),
            'visible_desktop' => (bool) ($section['visible_desktop'] ?? true),
            'settings' => is_array($section['settings'] ?? null) ? $section['settings'] : [],
            'placements' => $placements,
        ];
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function contentItem(array $section, string $locale): array
    {
        return [
            'id' => (int) ($section['id'] ?? 0),
            'title' => (string) ($section['name'] ?? ''),
            'locale' => $locale,
            'settings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function previewTemplateContext(array $section): array
    {
        $context = [];
        $systemFields = [];
        $templateFields = [];

        foreach (($section['placements'] ?? []) as $placement) {
            if (! is_array($placement)) {
                continue;
            }

            $fieldKey = data_get($placement, 'block.field_key');

            if (! is_string($fieldKey) || $fieldKey === '') {
                continue;
            }

            Arr::set($context, $fieldKey, $this->previewFieldValue($fieldKey));

            if (str_starts_with($fieldKey, 'template.')) {
                $templateFields[] = [
                    'key' => substr($fieldKey, strlen('template.')),
                    'type' => 'textarea',
                    'required' => false,
                ];

                continue;
            }

            $systemFields[] = [
                'key' => $fieldKey,
                'enabled' => true,
            ];
        }

        $context['__template'] = [
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'locale' => app()->getLocale(),
            'data_contract' => [
                'system_fields' => collect($systemFields)->unique('key')->values()->all(),
                'template_fields' => collect($templateFields)->unique('key')->values()->all(),
            ],
        ];

        return $context;
    }

    private function previewFieldValue(string $fieldKey): mixed
    {
        return match ($fieldKey) {
            'page.title', 'blog.title', 'category.title', 'tag.title' => __('cms_admin_ui.layouts.sections.live_preview_sample_title'),
            'page.short_description', 'blog.excerpt', 'category.description', 'tag.description' => __('cms_admin_ui.layouts.sections.live_preview_sample_excerpt'),
            'blog.content', 'category.content', 'category.detail_content', 'tag.content', 'tag.detail_content' => __('cms_admin_ui.layouts.sections.live_preview_sample_content_text'),
            'page.breadcrumbs' => [
                ['label' => __('cms_admin_ui.layouts.sections.live_preview_sample_home'), 'url' => '#'],
                ['label' => __('cms_admin_ui.layouts.sections.live_preview_sample_title'), 'url' => null, 'is_current' => true],
            ],
            default => str_starts_with($fieldKey, 'template.')
                ? __('cms_admin_ui.layouts.sections.live_preview_sample_template_field')
                : __('cms_admin_ui.layouts.sections.live_preview_sample_text'),
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function previewContentSlots(): array
    {
        return [
            'content' => [
                'blocks' => [
                    [
                        'renderer_key' => 'dynamic_field',
                        'title' => __('cms_admin_ui.layouts.sections.live_preview_sample_content_title'),
                        'value' => __('cms_admin_ui.layouts.sections.live_preview_sample_content_text'),
                        'value_type' => 'scalar',
                        'heading_level' => 'none',
                    ],
                ],
                'sections' => [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function previewBlockPayload(array $block): array
    {
        if (($block['renderer_key'] ?? null) === 'breadcrumb' && empty($block['items'])) {
            $block['items'] = [
                ['label' => __('cms_admin_ui.layouts.sections.live_preview_sample_home'), 'url' => '#'],
                ['label' => __('cms_admin_ui.layouts.sections.live_preview_sample_title'), 'url' => null, 'is_current' => true],
            ];
        }

        return $block;
    }

    private function placeholderDocument(string $title, string $description, string $locale, string $device, ?string $themeCssUrl): string
    {
        return $this->withoutScripts(View::make('admin.cms.partials.placement-preview-document', [
            'placement' => null,
            'section' => [],
            'contentItem' => $this->contentItem([], $locale),
            'site' => ['current_locale' => $locale, 'default_locale' => $locale],
            'navigation' => [],
            'translations' => [],
            'themeCssUrl' => $themeCssUrl,
            'locale' => $locale,
            'previewDevice' => $device,
            'placeholderTitle' => $title,
            'placeholderDescription' => $description,
        ])->render());
    }

    private function withoutScripts(string $html): string
    {
        return preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
    }
}
