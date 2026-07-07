<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsPlaceableBlockRevision;
use App\Models\Cms\CmsSection;
use App\Support\Cms\CmsBlockRegistry;
use App\Support\Cms\CmsResponsiveLayoutNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SaveCmsSectionsAction
{
    public function __construct(
        private readonly CmsBlockRegistry $blockRegistry,
        private readonly CmsResponsiveLayoutNormalizer $layoutNormalizer,
        private readonly GenerateCmsHtmlAnchorAction $htmlAnchorAction,
    ) {}

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $sections
     * @param  array<int, string>  $zones
     */
    public function handle(Model $owner, array $sections, array $zones): void
    {
        foreach ($zones as $zone) {
            if (! array_key_exists($zone, $sections)) {
                continue;
            }

            $this->syncZone($owner, $zone, $sections[$zone] ?? []);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function syncZone(Model $owner, string $zone, array $sections): void
    {
        $keptSectionIds = [];

        foreach (array_values($sections) as $sectionIndex => $sectionData) {
            $section = $this->resolveSection($owner, $zone, (int) ($sectionData['id'] ?? 0));
            $sectionSettings = $this->htmlAnchorAction->handle(
                $section,
                $this->sectionSettings($sectionData['settings'] ?? [], $zone),
                [$sectionData['name'] ?? null, $zone, $this->ownerLocale($owner), 'section'],
            );
            $section->fill([
                'zone' => $zone,
                'name' => $this->nullableString($sectionData['name'] ?? null),
                'sort_order' => $sectionIndex,
                'is_active' => (bool) ($sectionData['is_active'] ?? true),
                'visible_mobile' => (bool) ($sectionData['visible_mobile'] ?? true),
                'visible_tablet' => (bool) ($sectionData['visible_tablet'] ?? true),
                'visible_desktop' => (bool) ($sectionData['visible_desktop'] ?? true),
                'settings' => $sectionSettings,
            ]);
            $owner->sections()->save($section);

            $keptSectionIds[] = (int) $section->id;
            $this->syncPlacements($section, $sectionData['placements'] ?? [], $this->ownerLocale($owner));
        }

        $owner->sections()
            ->where('zone', $zone)
            ->when($keptSectionIds !== [], fn ($query) => $query->whereNotIn('id', $keptSectionIds))
            ->update(['is_active' => false]);
    }

    private function resolveSection(Model $owner, string $zone, int $sectionId): CmsSection
    {
        if ($sectionId <= 0) {
            return new CmsSection;
        }

        return $owner->sections()
            ->where('zone', $zone)
            ->where('id', $sectionId)
            ->firstOrNew();
    }

    /**
     * @param  array<int, array<string, mixed>>  $placements
     */
    private function syncPlacements(CmsSection $section, array $placements, ?string $ownerLocale): void
    {
        $this->syncPlacementRows($section, null, null, $placements, $ownerLocale, 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $placements
     */
    private function syncPlacementRows(?CmsSection $section, ?CmsBlockPlacement $parentPlacement, ?string $slotKey, array $placements, ?string $ownerLocale, int $depth): void
    {
        $keptPlacementIds = [];
        $placements = $this->layoutNormalizer->resolvePlacementLayoutCollisions($placements);

        foreach (array_values($placements) as $placementIndex => $placementData) {
            $placement = $this->resolvePlacement($section, $parentPlacement, $slotKey, (int) ($placementData['id'] ?? 0));
            $block = $this->saveBlock(
                $placementData['block'] ?? [],
                $placement->exists ? $placement->block()->first() : null,
            );

            if ($parentPlacement instanceof CmsBlockPlacement && $slotKey !== null) {
                $this->assertChildBlockAllowed($parentPlacement, $slotKey, $block);
            }

            $placementSettings = $this->htmlAnchorAction->handle(
                $placement,
                $this->placementSettings($placementData['settings'] ?? [], $block->type, $section?->zone ?? 'slot'),
                [$block->name, $block->type, $section?->zone ?? $slotKey, $ownerLocale, 'block'],
            );
            $placement->fill([
                'cms_section_id' => $section?->id,
                'parent_placement_id' => $parentPlacement?->id,
                'slot_key' => $slotKey,
                'cms_block_id' => $block->id,
                'sort_order' => $placementIndex,
                'is_active' => (bool) ($placementData['is_active'] ?? true),
                'visible_mobile' => (bool) ($placementData['visible_mobile'] ?? true),
                'visible_tablet' => (bool) ($placementData['visible_tablet'] ?? true),
                'visible_desktop' => (bool) ($placementData['visible_desktop'] ?? true),
                'mobile_span' => $this->span($placementData['mobile_span'] ?? 12),
                'tablet_span' => $this->span($placementData['tablet_span'] ?? 12),
                'desktop_span' => $this->span($placementData['desktop_span'] ?? 12),
                'layout_config' => $this->layoutNormalizer->normalizeLayout(
                    is_array($placementData['layout_config'] ?? null) ? $placementData['layout_config'] : null,
                    $this->span($placementData['desktop_span'] ?? 12),
                ),
                'style_config' => $this->layoutNormalizer->normalizeStyle(
                    is_array($placementData['style_config'] ?? null) ? $placementData['style_config'] : null,
                ),
                'height_mode' => $this->heightMode($placementData['height_mode'] ?? 'auto'),
                'height_value' => $this->nullableString($placementData['height_value'] ?? null),
                'cache_strategy' => $this->cacheStrategy($placementData['cache_strategy'] ?? 'inherit'),
                'settings' => $placementSettings,
            ]);
            $placement->save();

            $keptPlacementIds[] = (int) $placement->id;

            $this->syncPlacementSlots($placement, $placementData['slots'] ?? [], $ownerLocale, $depth);
        }

        $query = $parentPlacement instanceof CmsBlockPlacement
            ? $parentPlacement->childPlacements()->where('slot_key', $slotKey)
            : $section?->placements()->root();

        $query
            ->when($keptPlacementIds !== [], fn ($query) => $query->whereNotIn('id', $keptPlacementIds))
            ->update(['is_active' => false]);
    }

    private function resolvePlacement(?CmsSection $section, ?CmsBlockPlacement $parentPlacement, ?string $slotKey, int $placementId): CmsBlockPlacement
    {
        if ($placementId <= 0) {
            return new CmsBlockPlacement;
        }

        if ($parentPlacement instanceof CmsBlockPlacement) {
            return $parentPlacement->childPlacements()
                ->where('slot_key', $slotKey)
                ->where('id', $placementId)
                ->firstOrNew();
        }

        return $section?->placements()
            ->root()
            ->where('id', $placementId)
            ->firstOrNew() ?? new CmsBlockPlacement;
    }

    /**
     * @param  array<string, mixed>  $slots
     */
    private function syncPlacementSlots(CmsBlockPlacement $placement, array $slots, ?string $ownerLocale, int $depth): void
    {
        if ($depth >= 1) {
            return;
        }

        $slotDefinitions = $this->slotDefinitions($placement);

        foreach ($slotDefinitions as $slotDefinition) {
            $slotKey = (string) ($slotDefinition['key'] ?? '');

            if ($slotKey === '') {
                continue;
            }

            $slotData = is_array($slots[$slotKey] ?? null) ? $slots[$slotKey] : [];
            $placements = $this->slotPlacements($slotData);
            $maxItems = array_key_exists('max_items', $slotDefinition) && $slotDefinition['max_items'] !== null
                ? (int) $slotDefinition['max_items']
                : null;

            if ($maxItems !== null && count($placements) > $maxItems) {
                throw ValidationException::withMessages([
                    'sections' => __('cms_admin_ui.validation.slot_max_items_exceeded'),
                ]);
            }

            $this->syncPlacementRows(null, $placement, $slotKey, $placements, $ownerLocale, $depth + 1);
        }
    }

    /**
     * @param  array<string, mixed>  $slotData
     * @return array<int, array<string, mixed>>
     */
    private function slotPlacements(array $slotData): array
    {
        if (array_is_list($slotData)) {
            return $slotData;
        }

        return is_array($slotData['placements'] ?? null) ? $slotData['placements'] : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function slotDefinitions(CmsBlockPlacement $placement): array
    {
        $block = $placement->block()->with(['placeableBlock', 'placeableBlockRevision'])->first();
        $schema = $block?->placeableBlockRevision?->schema ?? $block?->placeableBlock?->schema ?? [];

        return collect(is_array($schema['slots'] ?? null) ? $schema['slots'] : [])
            ->filter(fn (mixed $slot): bool => is_array($slot))
            ->values()
            ->all();
    }

    private function assertChildBlockAllowed(CmsBlockPlacement $parentPlacement, string $slotKey, CmsBlock $childBlock): void
    {
        $slotDefinition = collect($this->slotDefinitions($parentPlacement))
            ->first(fn (array $slot): bool => ($slot['key'] ?? null) === $slotKey);

        if (! is_array($slotDefinition)) {
            throw ValidationException::withMessages([
                'sections' => __('cms_admin_ui.validation.slot_unknown'),
            ]);
        }

        $allowedBlockKeys = array_map('strval', $slotDefinition['allowed_block_keys'] ?? []);
        $childBlockKey = (string) ($childBlock->placeableBlock()->value('key') ?? '');

        if ($childBlockKey === '' || ! in_array($childBlockKey, $allowedBlockKeys, true)) {
            throw ValidationException::withMessages([
                'sections' => __('cms_admin_ui.validation.slot_child_block_forbidden'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $blockData
     */
    private function saveBlock(array $blockData, ?CmsBlock $existingBlock): CmsBlock
    {
        $block = $existingBlock ?? new CmsBlock;
        $placeableBlock = $this->resolvePlaceableBlock($blockData);
        $revision = $this->placeableBlockRevision($placeableBlock);
        $rendererKey = (string) $placeableBlock->renderer_key;

        $block->fill([
            'cms_placeable_block_id' => $placeableBlock->id,
            'placeable_block_revision_id' => $revision?->id,
            'type' => $rendererKey,
            'name' => $this->nullableString($blockData['name'] ?? $blockData['title'] ?? null),
            'content' => $this->blockContent($rendererKey, $blockData, $placeableBlock),
            'settings' => [],
            'is_shared' => false,
            'is_dynamic' => false,
            'cache_strategy' => $this->cacheStrategy($blockData['cache_strategy'] ?? 'inherit'),
        ])->save();

        return $block;
    }

    private function placeableBlock(int $placeableBlockId): CmsPlaceableBlock
    {
        return CmsPlaceableBlock::query()->findOrFail($placeableBlockId);
    }

    /**
     * @param  array<string, mixed>  $blockData
     */
    private function resolvePlaceableBlock(array $blockData): CmsPlaceableBlock
    {
        $placeableBlockId = (int) ($blockData['cms_placeable_block_id'] ?? 0);

        if ($placeableBlockId > 0) {
            return $this->placeableBlock($placeableBlockId);
        }

        $rendererKey = (string) ($blockData['renderer_key'] ?? $blockData['type'] ?? '');

        return CmsPlaceableBlock::query()
            ->where('renderer_key', $rendererKey)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->firstOrFail();
    }

    private function placeableBlockRevision(CmsPlaceableBlock $placeableBlock): ?CmsPlaceableBlockRevision
    {
        if ($placeableBlock->relationLoaded('revisions')) {
            return $placeableBlock->revisions
                ->first(fn (CmsPlaceableBlockRevision $revision): bool => $revision->status === 'published' && $revision->published_at !== null);
        }

        return $placeableBlock
            ->revisions()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->orderByDesc('revision_number')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $blockData
     * @return array<string, mixed>
     */
    private function blockContent(string $type, array $blockData, CmsPlaceableBlock $placeableBlock): array
    {
        $data = match ($type) {
            'text' => [
                'title' => $blockData['title'] ?? null,
                'text' => $blockData['text'] ?? null,
            ],
            'breadcrumb' => [
                'show_current' => (bool) ($blockData['show_current'] ?? true),
                'show_on_home' => (bool) ($blockData['show_on_home'] ?? true),
                'compact' => (bool) ($blockData['compact'] ?? false),
                'home_icon' => $this->mdiIconClass($blockData['home_icon'] ?? null, 'mdi-home'),
                'separator' => $this->breadcrumbSeparator($blockData['separator'] ?? null),
            ],
            'quote', 'testimonial' => [
                'text' => $blockData['text'] ?? null,
                'source' => $blockData['source'] ?? null,
            ],
            'image' => [
                'media_asset_id' => $blockData['media_asset_id'] ?? null,
                'caption' => $blockData['caption'] ?? null,
            ],
            'button' => [
                'label' => $blockData['label'] ?? null,
                'url' => $blockData['url'] ?? null,
            ],
            'form' => [
                'form_translation_key' => $blockData['form_translation_key'] ?? $blockData['form_key'] ?? null,
            ],
            'custom_head_code', 'custom_body_end_code' => [
                'code' => $blockData['code'] ?? null,
            ],
            'list_rows', 'list_grid' => [
                'title' => $blockData['title'] ?? null,
                'source_type' => $blockData['source_type'] ?? 'category',
                'category_source' => $blockData['category_source'] ?? 'all',
                'category_id' => $blockData['category_id'] ?? null,
                'tag_source' => $blockData['tag_source'] ?? 'all',
                'tag_id' => $blockData['tag_id'] ?? null,
                'show_only_subcategories' => (bool) ($blockData['show_only_subcategories'] ?? false),
                'limit' => min(max((int) ($blockData['limit'] ?? 24), 1), 100),
                'sort_field' => $blockData['sort_field'] ?? 'published_at',
                'sort_direction' => $blockData['sort_direction'] ?? 'desc',
                'show_search' => (bool) ($blockData['show_search'] ?? false),
                'show_excerpt' => (bool) ($blockData['show_excerpt'] ?? true),
                'show_image' => (bool) ($blockData['show_image'] ?? true),
                'show_date' => (bool) ($blockData['show_date'] ?? true),
                'show_categories' => (bool) ($blockData['show_categories'] ?? true),
                'empty_text' => $blockData['empty_text'] ?? null,
            ],
            default => $this->registryBlockContent($type, $blockData, $placeableBlock),
        };

        return array_filter($data, fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function mdiIconClass(mixed $value, string $fallback): string
    {
        $icon = is_scalar($value) ? trim((string) $value) : '';

        return preg_match('/^mdi-[a-z0-9-]+$/', $icon) === 1 ? $icon : $fallback;
    }

    private function breadcrumbSeparator(mixed $value): string
    {
        $separator = is_scalar($value) ? trim((string) $value) : '';

        return in_array($separator, ['›', '>', '/', '•'], true) ? $separator : '›';
    }

    /**
     * @param  array<string, mixed>  $blockData
     * @return array<string, mixed>
     */
    private function registryBlockContent(string $type, array $blockData, CmsPlaceableBlock $placeableBlock): array
    {
        $content = [];

        foreach ($this->fieldsForBlock($type, $placeableBlock) as $field) {
            $content[$field] = $field === 'media_asset_ids'
                ? $this->mediaAssetIds($blockData[$field] ?? [])
                : ($this->blockRegistry->repeaterFieldNamesFor($type, $field) !== []
                    ? $this->blockRegistry->normalizeRepeaterItems($type, $field, $blockData[$field] ?? [])
                    : ($blockData[$field] ?? null));
        }

        if ($type === 'address_block') {
            $content['_contact_defaults_applied'] = (bool) ($blockData['_contact_defaults_applied'] ?? false);
        }

        return $content;
    }

    /**
     * @return array<int, string>
     */
    private function fieldsForBlock(string $type, CmsPlaceableBlock $placeableBlock): array
    {
        $fields = $this->blockRegistry->fieldsFor($type);

        if ($fields !== []) {
            return $fields;
        }

        $schemaFields = $placeableBlock->schema['fields'] ?? [];

        return collect(is_array($schemaFields) ? $schemaFields : [])
            ->filter(fn (mixed $field): bool => is_string($field) && preg_match('/^[a-z0-9_]+$/', $field) === 1)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function mediaAssetIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function sectionSettings(array $settings, string $zone): array
    {
        $allowedLayoutTypes = $zone === 'header'
            ? ['standard', 'grid']
            : ['standard', 'hero', 'two_columns', 'grid'];
        $scrollBehavior = in_array($zone, ['header', 'footer'], true)
            && in_array($settings['scroll_behavior'] ?? null, ['normal', 'sticky', 'auto_hide'], true)
            ? $settings['scroll_behavior']
            : 'normal';

        return array_filter([
            'html_anchor' => $this->nullableString($settings['html_anchor'] ?? null),
            'layout_type' => in_array($settings['layout_type'] ?? null, $allowedLayoutTypes, true)
                ? $settings['layout_type']
                : 'standard',
            'width_mode' => in_array($settings['width_mode'] ?? null, ['content', 'display'], true)
                ? $settings['width_mode']
                : 'content',
            'spacing' => in_array($settings['spacing'] ?? null, ['none', 'compact', 'normal', 'spacious'], true)
                ? $settings['spacing']
                : 'none',
            'scroll_behavior' => $scrollBehavior,
            'background' => $this->layoutNormalizer->normalizeBackground(
                is_array($settings['background'] ?? null) ? $settings['background'] : null,
            ),
            'box' => $this->layoutNormalizer->normalizeBoxSpacing(
                is_array($settings['box'] ?? null) ? $settings['box'] : null,
            ),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function placementSettings(array $settings, string $blockType, string $zone): array
    {
        $input = $settings;
        $settings = array_filter([
            'html_anchor' => $this->nullableString($input['html_anchor'] ?? null),
            'alignment' => $this->alignment($input['alignment'] ?? null),
            'content_alignment' => $this->alignment($input['content_alignment'] ?? null),
        ], fn (mixed $value): bool => $value !== null);

        if ($this->isContentOverrideEligible($blockType, $zone)) {
            $contentKey = $this->contentKey($input['content_key'] ?? null);
            $editorLabel = $this->nullableString($input['editor_label'] ?? null);
            $pageEditable = array_key_exists('page_editable', $input)
                ? (bool) $input['page_editable']
                : $contentKey !== null;

            if ($contentKey !== null) {
                $settings['content_key'] = $contentKey;
            }

            if ($editorLabel !== null) {
                $settings['editor_label'] = $editorLabel;
            }

            if (array_key_exists('page_editable', $input) || $contentKey !== null) {
                $settings['page_editable'] = $pageEditable;
            }

            if (is_array($input['page_editable_fields'] ?? null)) {
                $fields = $this->stringList($input['page_editable_fields']);

                if ($fields !== []) {
                    $settings['page_editable_fields'] = $fields;
                }
            }

            if (is_array($input['page_editable_meta'] ?? null)) {
                $metaFields = collect($input['page_editable_meta'])
                    ->filter(fn (mixed $field): bool => $field === 'is_active')
                    ->values()
                    ->all();

                if ($metaFields !== []) {
                    $settings['page_editable_meta'] = $metaFields;
                }
            }
        }

        return $settings;
    }

    private function isContentOverrideEligible(string $blockType, string $zone): bool
    {
        if (! in_array($zone, ['content', 'slot'], true)) {
            return false;
        }

        return ! in_array($blockType, [
            'breadcrumb',
            'content_slot',
            'dynamic_field',
            'form',
            'list_grid',
            'list_rows',
        ], true);
    }

    private function contentKey(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $key = preg_replace('/[^a-z0-9_]+/', '_', mb_strtolower(trim((string) $value))) ?: '';
        $key = trim($key, '_');

        if ($key === '' || preg_match('/^[a-z][a-z0-9_]{0,79}$/', $key) !== 1) {
            return null;
        }

        return $key;
    }

    private function span(mixed $span): int
    {
        return min(max((int) $span, 1), 12);
    }

    private function alignment(mixed $alignment): ?string
    {
        return in_array($alignment, ['left', 'center', 'right'], true) ? $alignment : null;
    }

    private function heightMode(mixed $heightMode): string
    {
        return in_array($heightMode, ['auto', 'fixed', 'min'], true) ? $heightMode : 'auto';
    }

    private function cacheStrategy(mixed $cacheStrategy): string
    {
        return in_array($cacheStrategy, ['inherit', 'none', 'block', 'layout'], true) ? $cacheStrategy : 'inherit';
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_string($item) && preg_match('/^[a-z0-9_]+$/', $item) === 1)
            ->unique()
            ->values()
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function ownerLocale(Model $owner): ?string
    {
        $locale = $owner->getAttribute('locale');

        return is_scalar($locale) && trim((string) $locale) !== ''
            ? trim((string) $locale)
            : null;
    }
}
