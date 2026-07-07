<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Support\Cms\CmsPlaceableBlockResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class CmsPageCompositionBuilder
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
        private readonly CmsPublicUrlBuilder $urlBuilder,
    ) {}

    /**
     * @return array{page: array<string, mixed>, layout: null, sections: array{head: array<int, array<string, mixed>>, header: array<int, array<string, mixed>>, header_scroll: array<int, array<string, mixed>>, header_sticky: array<int, array<string, mixed>>, content: array<int, array<string, mixed>>, footer: array<int, array<string, mixed>>, footer_scroll: array<int, array<string, mixed>>, footer_sticky: array<int, array<string, mixed>>, body_end: array<int, array<string, mixed>>}, styles: array<int, array<string, mixed>>}
     */
    public function handle(CmsPage $page): array
    {
        $page->loadMissing([
            'sections.placements.block.placeableBlock.revisions',
            'sections.placements.block.placeableBlockRevision',
            'sections.placements.publishedStyleRevision',
            'sections.placements.childPlacements.block.placeableBlock.revisions',
            'sections.placements.childPlacements.block.placeableBlockRevision',
            'sections.placements.childPlacements.publishedStyleRevision',
        ]);

        $exclusionIds = $page->blockExclusions()
            ->pluck('cms_block_placement_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $overrides = $page->blockOverrides()
            ->where('is_active', true)
            ->get()
            ->keyBy('cms_block_placement_id');

        $includedPlacementIds = collect();

        $contentSections = $this->sectionPayloads(
            $page->sections->where('zone', 'content'),
            $page,
            $exclusionIds,
            $overrides,
            $includedPlacementIds,
            'page',
        );

        $sharedContentSections = $this->sharedSectionPayloads(
            $page,
            $exclusionIds,
            $overrides,
            $includedPlacementIds,
        );

        $sections = [
            'head' => [],
            'header' => [],
            'header_scroll' => [],
            'header_sticky' => [],
            'content' => array_values(array_merge($contentSections, $sharedContentSections)),
            'footer' => [],
            'footer_scroll' => [],
            'footer_sticky' => [],
            'body_end' => [],
        ];

        return [
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'locale' => $page->locale,
                'url' => $this->pageUrl($page),
                'short_description' => $page->short_description,
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'published_at' => $page->published_at?->toDateString(),
                'updated_at' => $page->updated_at?->toDateString(),
                'settings' => $page->settings ?? [],
                'background_media' => $this->backgroundMediaPayload(
                    is_array($page->settings['page_style'] ?? null) ? $page->settings['page_style'] : [],
                    (string) $page->locale,
                ),
            ],
            'layout' => null,
            'sections' => $sections,
            'styles' => $this->styleCollector->handle($sections),
        ];
    }

    private function pageUrl(CmsPage $page): string
    {
        $pages = CmsPage::query()
            ->get(['id', 'parent_id', 'slug', 'locale', 'is_home'])
            ->keyBy('id');

        return $this->urlBuilder->pageUrl($page, $pages);
    }

    /**
     * @param  Collection<int, CmsSection>|EloquentCollection<int, CmsSection>  $sections
     * @param  array<int, int>  $exclusionIds
     * @param  Collection<int, mixed>  $overrides
     * @param  Collection<int, int>  $includedPlacementIds
     * @return array<int, array<string, mixed>>
     */
    private function sectionPayloads(
        Collection|EloquentCollection $sections,
        CmsPage $page,
        array $exclusionIds,
        Collection $overrides,
        Collection $includedPlacementIds,
        string $source,
    ): array {
        return $sections
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->map(function (CmsSection $section) use ($page, $exclusionIds, $overrides, $includedPlacementIds, $source): array {
                $placements = $this->placementPayloads(
                    $section->placements,
                    $page,
                    $exclusionIds,
                    $overrides,
                    $includedPlacementIds,
                    (string) $section->zone,
                );

                return [
                    'id' => $section->id,
                    'source' => $source,
                    'zone' => $section->zone,
                    'name' => $section->name,
                    'sort_order' => $section->sort_order,
                    'visible_mobile' => $section->visible_mobile,
                    'visible_tablet' => $section->visible_tablet,
                    'visible_desktop' => $section->visible_desktop,
                    'settings' => $section->settings ?? [],
                    'background_media' => $this->backgroundMediaPayload($section->settings ?? [], (string) $page->locale),
                    'placements' => $placements,
                ];
            })
            ->filter(fn (array $section): bool => $section['placements'] !== [])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $exclusionIds
     * @param  Collection<int, mixed>  $overrides
     * @param  Collection<int, int>  $includedPlacementIds
     * @return array<int, array<string, mixed>>
     */
    private function sharedSectionPayloads(
        CmsPage $page,
        array $exclusionIds,
        Collection $overrides,
        Collection $includedPlacementIds,
    ): array {
        $placements = CmsBlockPlacement::query()
            ->with([
                'block.placeableBlock.revisions',
                'block.placeableBlockRevision',
                'publishedStyleRevision',
                'section',
                'scopes',
                'childPlacements.block.placeableBlock.revisions',
                'childPlacements.block.placeableBlockRevision',
                'childPlacements.publishedStyleRevision',
            ])
            ->active()
            ->whereHas('block', fn ($query) => $query->shared())
            ->whereHas('scopes', function ($query) use ($page): void {
                $query
                    ->active()
                    ->where(function ($scopeQuery) use ($page): void {
                        $scopeQuery
                            ->where('locale', $page->locale)
                            ->orWhereNull('locale');
                    })
                    ->where(function ($scopeQuery) use ($page): void {
                        $scopeQuery
                            ->where('scope_type', 'all_pages')
                            ->orWhere(function ($pageScopeQuery) use ($page): void {
                                $pageScopeQuery
                                    ->where('scope_type', 'page')
                                    ->where('scope_value', (string) $page->id);
                            });
                    });
            })
            ->get()
            ->reject(fn (CmsBlockPlacement $placement): bool => $includedPlacementIds->contains($placement->id))
            ->groupBy(fn (CmsBlockPlacement $placement): int => (int) $placement->cms_section_id);

        return $placements
            ->map(function (Collection $sectionPlacements) use ($page, $exclusionIds, $overrides, $includedPlacementIds): ?array {
                $section = $sectionPlacements->first()?->section;

                if (! $section instanceof CmsSection || ! $section->is_active) {
                    return null;
                }

                $payloads = $this->placementPayloads(
                    $sectionPlacements,
                    $page,
                    $exclusionIds,
                    $overrides,
                    $includedPlacementIds,
                    (string) $section->zone,
                );

                if ($payloads === []) {
                    return null;
                }

                return [
                    'id' => $section->id,
                    'source' => 'shared',
                    'zone' => $section->zone,
                    'name' => $section->name,
                    'sort_order' => $section->sort_order,
                    'visible_mobile' => $section->visible_mobile,
                    'visible_tablet' => $section->visible_tablet,
                    'visible_desktop' => $section->visible_desktop,
                    'settings' => $section->settings ?? [],
                    'background_media' => $this->backgroundMediaPayload($section->settings ?? [], (string) $page->locale),
                    'placements' => $payloads,
                ];
            })
            ->filter()
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, CmsBlockPlacement>|EloquentCollection<int, CmsBlockPlacement>  $placements
     * @param  array<int, int>  $exclusionIds
     * @param  Collection<int, mixed>  $overrides
     * @param  Collection<int, int>  $includedPlacementIds
     * @return array<int, array<string, mixed>>
     */
    private function placementPayloads(
        Collection|EloquentCollection $placements,
        CmsPage $page,
        array $exclusionIds,
        Collection $overrides,
        Collection $includedPlacementIds,
        string $sectionZone,
    ): array {
        $placementRows = $placements
            ->where('is_active', true)
            ->reject(fn (CmsBlockPlacement $placement): bool => in_array($placement->id, $exclusionIds, true))
            ->sortBy('sort_order')
            ->values();

        $blocks = $placementRows
            ->map(function (CmsBlockPlacement $placement) use ($overrides, $sectionZone): array {
                $override = $overrides->get($placement->id);

                return array_replace_recursive(
                    [
                        'cms_placeable_block_id' => $placement->block->cms_placeable_block_id,
                        'placeable_block_revision_id' => $placement->block->placeable_block_revision_id,
                        'renderer_key' => $placement->block->placeableBlock?->renderer_key,
                        'placeable_block' => $this->placeableBlockResolver->payloadForBlock($placement->block),
                        'placement_zone' => $sectionZone,
                    ],
                    $placement->block->content ?? [],
                    $override?->content ?? [],
                );
            })
            ->all();

        $payloads = $this->blockPayloadBuilder->handle($blocks, $page);

        return $placementRows
            ->map(function (CmsBlockPlacement $placement, int $index) use ($payloads, $overrides, $includedPlacementIds, $page): array {
                $override = $overrides->get($placement->id);
                $includedPlacementIds->push((int) $placement->id);

                return [
                    'id' => $placement->id,
                    'block_id' => $placement->cms_block_id,
                    'sort_order' => $placement->sort_order,
                    'visible_mobile' => $placement->visible_mobile,
                    'visible_tablet' => $placement->visible_tablet,
                    'visible_desktop' => $placement->visible_desktop,
                    'mobile_span' => $placement->mobile_span,
                    'tablet_span' => $placement->tablet_span,
                    'desktop_span' => $placement->desktop_span,
                    'layout_config' => $placement->layout_config ?? [],
                    'style_config' => $placement->style_config ?? [],
                    'published_style_revision' => $placement->publishedStyleRevision ? [
                        'id' => (int) $placement->publishedStyleRevision->id,
                        'revision_number' => (int) $placement->publishedStyleRevision->revision_number,
                        'css_source' => (string) $placement->publishedStyleRevision->css_source,
                    ] : null,
                    'height_mode' => $placement->height_mode,
                    'height_value' => $placement->height_value,
                    'cache_strategy' => $placement->cache_strategy,
                    'settings' => array_replace_recursive(
                        $placement->block->settings ?? [],
                        $placement->settings ?? [],
                        $override?->settings ?? [],
                    ),
                    'has_override' => $override !== null,
                    'block' => $payloads[$index] ?? ['renderer_key' => $placement->block->placeableBlock?->renderer_key],
                    'slots' => $this->childSlotPayloads($placement, $page),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function childSlotPayloads(CmsBlockPlacement $parentPlacement, CmsPage $page): array
    {
        $groups = $parentPlacement->childPlacements
            ->where('is_active', true)
            ->groupBy('slot_key');

        return $groups
            ->map(function (Collection $placements, string $slotKey) use ($parentPlacement, $page): array {
                $slotDefinition = $this->slotDefinition($parentPlacement, $slotKey);
                $placementRows = $placements->sortBy('sort_order')->values();
                $blocks = $placementRows
                    ->map(fn (CmsBlockPlacement $placement): array => array_replace_recursive(
                        [
                            'cms_placeable_block_id' => $placement->block->cms_placeable_block_id,
                            'placeable_block_revision_id' => $placement->block->placeable_block_revision_id,
                            'renderer_key' => $placement->block->placeableBlock?->renderer_key,
                            'placeable_block' => $this->placeableBlockResolver->payloadForBlock($placement->block),
                            'placement_zone' => 'slot',
                        ],
                        $placement->block->content ?? [],
                    ))
                    ->all();
                $payloads = $this->blockPayloadBuilder->handle($blocks, $page);

                return [
                    'key' => $slotKey,
                    'layout' => $slotDefinition['layout'] ?? 'stack',
                    'columns' => $slotDefinition['columns'] ?? 12,
                    'responsive' => $slotDefinition['responsive'] ?? 'same',
                    'placements' => $placementRows
                        ->map(fn (CmsBlockPlacement $placement, int $index): array => [
                            'id' => $placement->id,
                            'block_id' => $placement->cms_block_id,
                            'sort_order' => $placement->sort_order,
                            'visible_mobile' => $placement->visible_mobile,
                            'visible_tablet' => $placement->visible_tablet,
                            'visible_desktop' => $placement->visible_desktop,
                            'mobile_span' => $placement->mobile_span,
                            'tablet_span' => $placement->tablet_span,
                            'desktop_span' => $placement->desktop_span,
                            'layout_config' => $placement->layout_config ?? [],
                            'style_config' => $placement->style_config ?? [],
                            'height_mode' => $placement->height_mode,
                            'height_value' => $placement->height_value,
                            'cache_strategy' => $placement->cache_strategy,
                            'settings' => array_replace_recursive(
                                $placement->block->settings ?? [],
                                $placement->settings ?? [],
                            ),
                            'block' => $payloads[$index] ?? ['renderer_key' => $placement->block->placeableBlock?->renderer_key],
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
