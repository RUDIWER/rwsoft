<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsTemplate;
use App\Support\Cms\CmsPlaceableBlockResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class CmsTemplateCompositionBuilder
{
    /**
     * @var array<int, array<string, mixed>|null>
     */
    private array $backgroundMediaCache = [];

    public function __construct(
        private readonly CmsBlockPayloadBuilder $blockPayloadBuilder,
        private readonly CmsPlaceableBlockResolver $placeableBlockResolver,
        private readonly CmsCompositionStyleCollector $styleCollector,
        private readonly PublicMediaUrl $mediaUrl,
    ) {}

    /**
     * @param  array<string, mixed>  $contentItem
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $contentSlots
     * @return array{page: array<string, mixed>, template: array<string, mixed>|null, layout: array<string, mixed>|null, sections: array{head: array<int, array<string, mixed>>, header: array<int, array<string, mixed>>, header_scroll: array<int, array<string, mixed>>, header_sticky: array<int, array<string, mixed>>, content: array<int, array<string, mixed>>, footer: array<int, array<string, mixed>>, footer_scroll: array<int, array<string, mixed>>, footer_sticky: array<int, array<string, mixed>>, body_end: array<int, array<string, mixed>>}, styles: array<int, array<string, mixed>>}
     */
    public function handle(CmsTemplate $template, array $contentItem, array $context, array $contentSlots = [], ?CmsPage $page = null, ?CmsPost $post = null): array
    {
        $template->loadMissing([
            'layout',
            'sections.placements.block.placeableBlock.revisions',
            'sections.placements.block.placeableBlockRevision',
            'sections.placements.publishedStyleRevision',
            'sections.placements.childPlacements.block.placeableBlock.revisions',
            'sections.placements.childPlacements.block.placeableBlockRevision',
            'sections.placements.childPlacements.publishedStyleRevision',
        ]);
        $layout = $this->resolveLayout($template);
        $layout?->loadMissing([
            'sections.placements.block.placeableBlock.revisions',
            'sections.placements.block.placeableBlockRevision',
            'sections.placements.publishedStyleRevision',
            'sections.placements.childPlacements.block.placeableBlock.revisions',
            'sections.placements.childPlacements.block.placeableBlockRevision',
            'sections.placements.childPlacements.publishedStyleRevision',
        ]);

        $headSections = $this->layoutSectionPayloads($layout?->sections?->where('zone', 'head') ?? collect(), $contentItem, $page, $post);
        $headerSections = $this->layoutSectionPayloads($layout?->sections?->where('zone', 'header') ?? collect(), $contentItem, $page, $post);
        $footerSections = $this->layoutSectionPayloads($layout?->sections?->where('zone', 'footer') ?? collect(), $contentItem, $page, $post);
        $bodyEndSections = $this->layoutSectionPayloads($layout?->sections?->where('zone', 'body_end') ?? collect(), $contentItem, $page, $post);

        $headerSectionGroups = $this->splitSectionsByScrollBehavior($headerSections);
        $footerSectionGroups = $this->splitSectionsByScrollBehavior($footerSections);

        $sections = [
            'head' => $headSections,
            'header' => $headerSections,
            'header_scroll' => $headerSectionGroups['scroll'],
            'header_sticky' => $headerSectionGroups['sticky'],
            'content' => $this->templateSectionPayloads(
                $template->sections->where('zone', 'content'),
                $contentItem,
                $context,
                $contentSlots,
                $page,
                $post,
            ),
            'footer' => $footerSections,
            'footer_scroll' => $footerSectionGroups['scroll'],
            'footer_sticky' => $footerSectionGroups['sticky'],
            'body_end' => $bodyEndSections,
        ];

        return [
            'page' => $contentItem,
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'template_class' => $template->template_class,
                'template_key' => $template->template_key,
                'locale' => $template->locale,
                'cache_strategy' => $template->cache_strategy,
                'settings' => $template->settings ?? [],
            ],
            'layout' => $layout instanceof CmsLayout ? [
                'id' => $layout->id,
                'name' => $layout->name,
                'locale' => $layout->locale,
                'cache_strategy' => $layout->cache_strategy,
                'settings' => array_merge($layout->settings ?? [], [
                    'scroll_mode' => $this->scrollMode($layout),
                ]),
                'background_media' => $this->backgroundMediaPayload($layout->settings ?? [], (string) $template->locale),
            ] : null,
            'sections' => $sections,
            'styles' => $this->styleCollector->handle($sections),
        ];
    }

    private function resolveLayout(CmsTemplate $template): ?CmsLayout
    {
        if ($template->layout instanceof CmsLayout && $template->layout->is_active && $template->layout->locale === $template->locale) {
            return $template->layout;
        }

        return CmsLayout::query()
            ->active()
            ->defaultForLocale($template->locale)
            ->first();
    }

    private function scrollMode(?CmsLayout $layout): string
    {
        $layoutScrollMode = $layout?->settings['scroll_mode'] ?? null;

        return in_array($layoutScrollMode, ['browser', 'internal'], true)
            ? $layoutScrollMode
            : 'browser';
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array{scroll: array<int, array<string, mixed>>, sticky: array<int, array<string, mixed>>}
     */
    private function splitSectionsByScrollBehavior(array $sections): array
    {
        return [
            'scroll' => $this->filterSectionsByScrollBehavior($sections, false),
            'sticky' => $this->filterSectionsByScrollBehavior($sections, true),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function filterSectionsByScrollBehavior(array $sections, bool $sticky): array
    {
        return collect($sections)
            ->filter(fn (array $section): bool => $this->isStickySection($section) === $sticky)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function isStickySection(array $section): bool
    {
        $settings = $section['settings'] ?? [];
        $behavior = is_array($settings) ? ($settings['scroll_behavior'] ?? null) : null;

        return in_array($behavior, ['sticky', 'auto_hide'], true);
    }

    /**
     * @param  Collection<int, CmsSection>|EloquentCollection<int, CmsSection>  $sections
     * @param  array<string, mixed>  $contentItem
     * @return array<int, array<string, mixed>>
     */
    private function layoutSectionPayloads(Collection|EloquentCollection $sections, array $contentItem, ?CmsPage $page = null, ?CmsPost $post = null): array
    {
        return $this->sectionPayloads($sections, $contentItem, [], [], $page, $post);
    }

    /**
     * @param  Collection<int, CmsSection>|EloquentCollection<int, CmsSection>  $sections
     * @param  array<string, mixed>  $contentItem
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $contentSlots
     * @return array<int, array<string, mixed>>
     */
    private function templateSectionPayloads(Collection|EloquentCollection $sections, array $contentItem, array $context, array $contentSlots, ?CmsPage $page = null, ?CmsPost $post = null): array
    {
        return $this->sectionPayloads($sections, $contentItem, $context, $contentSlots, $page, $post);
    }

    /**
     * @param  Collection<int, CmsSection>|EloquentCollection<int, CmsSection>  $sections
     * @param  array<string, mixed>  $contentItem
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $contentSlots
     * @return array<int, array<string, mixed>>
     */
    private function sectionPayloads(Collection|EloquentCollection $sections, array $contentItem, array $context, array $contentSlots, ?CmsPage $page = null, ?CmsPost $post = null): array
    {
        $contentKeys = [];

        return $sections
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->map(function (CmsSection $section) use ($contentItem, $context, $contentSlots, $page, $post, &$contentKeys): array {
                return [
                    'id' => $section->id,
                    'source' => $section->owner_type === CmsTemplate::class ? 'template' : 'layout',
                    'zone' => $section->zone,
                    'name' => $section->name,
                    'sort_order' => $section->sort_order,
                    'visible_mobile' => $section->visible_mobile,
                    'visible_tablet' => $section->visible_tablet,
                    'visible_desktop' => $section->visible_desktop,
                    'settings' => $section->settings ?? [],
                    'background_media' => $this->backgroundMediaPayload($section->settings ?? [], (string) ($contentItem['locale'] ?? '')),
                    'placements' => $this->placementPayloads($section->placements, $contentItem, $context, $contentSlots, (string) $section->zone, $page, $post, $contentKeys),
                ];
            })
            ->filter(fn (array $section): bool => $section['placements'] !== [])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, CmsBlockPlacement>|EloquentCollection<int, CmsBlockPlacement>  $placements
     * @param  array<string, mixed>  $contentItem
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $contentSlots
     * @return array<int, array<string, mixed>>
     */
    private function placementPayloads(Collection|EloquentCollection $placements, array $contentItem, array $context, array $contentSlots, string $sectionZone, ?CmsPage $page = null, ?CmsPost $post = null, array &$contentKeys = []): array
    {
        $templateBlockData = $this->templateBlockData($contentItem);
        $placementRows = $placements
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->values()
            ->map(function (CmsBlockPlacement $placement) use (&$contentKeys): array {
                return [
                    'placement' => $placement,
                    'content_key' => $this->uniqueContentKey($this->contentKey($placement), $contentKeys),
                ];
            })
            ->reject(fn (array $row): bool => $this->pageOverrideHidesPlacement($row['content_key'], $templateBlockData))
            ->values();

        $blocks = $placementRows
            ->map(fn (array $row): array => array_replace_recursive(
                [
                    'cms_placeable_block_id' => $row['placement']->block->cms_placeable_block_id,
                    'placeable_block_revision_id' => $row['placement']->block->placeable_block_revision_id,
                    'renderer_key' => $row['placement']->block->placeableBlock?->renderer_key,
                    'placeable_block' => $this->placeableBlockResolver->payloadForBlock($row['placement']->block),
                    'placement_zone' => $sectionZone,
                    'content_key' => $row['content_key'],
                    'page_editable_fields' => $this->pageEditableFields($row['placement']),
                ],
                $row['placement']->block->content ?? [],
            ))
            ->all();

        $payloads = $this->blockPayloadBuilder->handle(
            $blocks,
            $page,
            $post,
            templateContext: $context,
            contentSlots: $contentSlots,
            contentLocale: (string) ($contentItem['locale'] ?? ''),
            templateBlockData: $templateBlockData,
        );

        return $placementRows
            ->map(fn (array $row, int $index): array => [
                'id' => $row['placement']->id,
                'block_id' => $row['placement']->cms_block_id,
                'sort_order' => $row['placement']->sort_order,
                'visible_mobile' => $row['placement']->visible_mobile,
                'visible_tablet' => $row['placement']->visible_tablet,
                'visible_desktop' => $row['placement']->visible_desktop,
                'mobile_span' => $row['placement']->mobile_span,
                'tablet_span' => $row['placement']->tablet_span,
                'desktop_span' => $row['placement']->desktop_span,
                'layout_config' => $row['placement']->layout_config ?? [],
                'style_config' => $row['placement']->style_config ?? [],
                'published_style_revision' => $row['placement']->publishedStyleRevision ? [
                    'id' => (int) $row['placement']->publishedStyleRevision->id,
                    'revision_number' => (int) $row['placement']->publishedStyleRevision->revision_number,
                    'css_source' => (string) $row['placement']->publishedStyleRevision->css_source,
                ] : null,
                'height_mode' => $row['placement']->height_mode,
                'height_value' => $row['placement']->height_value,
                'cache_strategy' => $row['placement']->cache_strategy,
                'settings' => array_replace_recursive(
                    $row['placement']->block->settings ?? [],
                    $row['placement']->settings ?? [],
                ),
                'block' => $payloads[$index] ?? ['renderer_key' => $row['placement']->block->placeableBlock?->renderer_key],
                'slots' => $this->childSlotPayloads($row['placement'], $contentItem, $context, $contentSlots, $page, $post, $contentKeys),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $contentItem
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $contentSlots
     * @return array<string, array<string, mixed>>
     */
    private function childSlotPayloads(CmsBlockPlacement $parentPlacement, array $contentItem, array $context, array $contentSlots, ?CmsPage $page = null, ?CmsPost $post = null, array &$contentKeys = []): array
    {
        $templateBlockData = $this->templateBlockData($contentItem);
        $groups = $parentPlacement->childPlacements
            ->where('is_active', true)
            ->groupBy('slot_key');

        return $groups
            ->map(function (Collection $placements, string $slotKey) use ($parentPlacement, $contentItem, $context, $contentSlots, $page, $post, $templateBlockData, &$contentKeys): array {
                $slotDefinition = $this->slotDefinition($parentPlacement, $slotKey);
                $placementRows = $placements
                    ->sortBy('sort_order')
                    ->values()
                    ->map(function (CmsBlockPlacement $placement) use (&$contentKeys): array {
                        return [
                            'placement' => $placement,
                            'content_key' => $this->uniqueContentKey($this->contentKey($placement), $contentKeys),
                        ];
                    })
                    ->reject(fn (array $row): bool => $this->pageOverrideHidesPlacement($row['content_key'], $templateBlockData))
                    ->values();
                $blocks = $placementRows
                    ->map(fn (array $row): array => array_replace_recursive(
                        [
                            'cms_placeable_block_id' => $row['placement']->block->cms_placeable_block_id,
                            'placeable_block_revision_id' => $row['placement']->block->placeable_block_revision_id,
                            'renderer_key' => $row['placement']->block->placeableBlock?->renderer_key,
                            'placeable_block' => $this->placeableBlockResolver->payloadForBlock($row['placement']->block),
                            'placement_zone' => 'slot',
                            'content_key' => $row['content_key'],
                            'page_editable_fields' => $this->pageEditableFields($row['placement']),
                        ],
                        $row['placement']->block->content ?? [],
                    ))
                    ->all();
                $payloads = $this->blockPayloadBuilder->handle(
                    $blocks,
                    $page,
                    $post,
                    templateContext: $context,
                    contentSlots: $contentSlots,
                    contentLocale: (string) ($contentItem['locale'] ?? ''),
                    templateBlockData: $templateBlockData,
                );

                return [
                    'key' => $slotKey,
                    'layout' => $slotDefinition['layout'] ?? 'stack',
                    'columns' => $slotDefinition['columns'] ?? 12,
                    'responsive' => $slotDefinition['responsive'] ?? 'same',
                    'placements' => $placementRows
                        ->map(fn (array $row, int $index): array => [
                            'id' => $row['placement']->id,
                            'block_id' => $row['placement']->cms_block_id,
                            'sort_order' => $row['placement']->sort_order,
                            'visible_mobile' => $row['placement']->visible_mobile,
                            'visible_tablet' => $row['placement']->visible_tablet,
                            'visible_desktop' => $row['placement']->visible_desktop,
                            'mobile_span' => $row['placement']->mobile_span,
                            'tablet_span' => $row['placement']->tablet_span,
                            'desktop_span' => $row['placement']->desktop_span,
                            'layout_config' => $row['placement']->layout_config ?? [],
                            'style_config' => $row['placement']->style_config ?? [],
                            'height_mode' => $row['placement']->height_mode,
                            'height_value' => $row['placement']->height_value,
                            'cache_strategy' => $row['placement']->cache_strategy,
                            'settings' => array_replace_recursive(
                                $row['placement']->block->settings ?? [],
                                $row['placement']->settings ?? [],
                            ),
                            'block' => $payloads[$index] ?? ['renderer_key' => $row['placement']->block->placeableBlock?->renderer_key],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function slotDefinition(CmsBlockPlacement $parentPlacement, string $slotKey): array
    {
        $schema = $parentPlacement->block?->placeableBlockRevision?->schema
            ?? $parentPlacement->block?->placeableBlock?->schema
            ?? [];

        return collect(is_array($schema['slots'] ?? null) ? $schema['slots'] : [])
            ->first(fn (mixed $slot): bool => is_array($slot) && ($slot['key'] ?? null) === $slotKey) ?? [];
    }

    /**
     * @param  array<string, mixed>  $contentItem
     * @return array<string, mixed>
     */
    private function templateBlockData(array $contentItem): array
    {
        $blocks = Arr::get($contentItem, 'template_data.blocks', []);

        return is_array($blocks) ? $blocks : [];
    }

    private function contentKey(CmsBlockPlacement $placement): ?string
    {
        $settings = array_replace_recursive(
            $placement->block->settings ?? [],
            $placement->settings ?? [],
        );

        if (! (bool) ($settings['page_editable'] ?? filled($settings['content_key'] ?? null))) {
            return null;
        }

        $contentKey = is_scalar($settings['content_key'] ?? null) ? (string) $settings['content_key'] : '';
        $contentKey = preg_replace('/[^a-z0-9_]+/', '_', mb_strtolower(trim($contentKey))) ?: '';

        return $contentKey !== '' ? trim($contentKey, '_') : null;
    }

    /**
     * @param  array<string, mixed>  $templateBlockData
     */
    private function pageOverrideHidesPlacement(?string $contentKey, array $templateBlockData): bool
    {
        if ($contentKey === null || ! is_array($templateBlockData[$contentKey] ?? null)) {
            return false;
        }

        $isActive = data_get($templateBlockData[$contentKey], '_meta.is_active');

        return $isActive === false || $isActive === 0 || $isActive === '0' || $isActive === 'false';
    }

    /**
     * @param  array<string, bool>  $contentKeys
     */
    private function uniqueContentKey(?string $contentKey, array &$contentKeys): ?string
    {
        if ($contentKey === null || $contentKey === '') {
            return null;
        }

        if (! array_key_exists($contentKey, $contentKeys)) {
            $contentKeys[$contentKey] = true;

            return $contentKey;
        }

        $suffix = 2;
        $candidate = $contentKey.'_'.$suffix;

        while (array_key_exists($candidate, $contentKeys)) {
            $suffix++;
            $candidate = $contentKey.'_'.$suffix;
        }

        $contentKeys[$candidate] = true;

        return $candidate;
    }

    /**
     * @return array<int, string>|null
     */
    private function pageEditableFields(CmsBlockPlacement $placement): ?array
    {
        $settings = array_replace_recursive(
            $placement->block->settings ?? [],
            $placement->settings ?? [],
        );

        if (! array_key_exists('page_editable_fields', $settings)) {
            return null;
        }

        return collect(is_array($settings['page_editable_fields'] ?? null) ? $settings['page_editable_fields'] : [])
            ->filter(fn (mixed $field): bool => is_string($field) && $field !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>|null
     */
    private function backgroundMediaPayload(array $settings, string $locale): ?array
    {
        $background = is_array($settings['background'] ?? null) ? $settings['background'] : [];
        $mediaAssetId = (int) ($background['media_asset_id'] ?? 0);

        if ($mediaAssetId <= 0) {
            return null;
        }

        if (array_key_exists($mediaAssetId, $this->backgroundMediaCache)) {
            return $this->backgroundMediaCache[$mediaAssetId];
        }

        $asset = CmsMediaAsset::query()
            ->with('translations')
            ->whereNull('deleted_at')
            ->find($mediaAssetId);

        $this->backgroundMediaCache[$mediaAssetId] = $asset instanceof CmsMediaAsset
            ? $this->mediaUrl->payload($asset, $locale)
            : null;

        return $this->backgroundMediaCache[$mediaAssetId];
    }
}
