<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsSection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RestoreCmsSectionSnapshotsAction
{
    /**
     * @param  array<string, array<int, array<string, mixed>>>  $sectionsByZone
     * @param  array<int, string>  $zones
     * @return array<string, mixed>
     */
    public function handle(Model $owner, array $sectionsByZone, array $zones, ?int $createdBy = null): array
    {
        $warnings = [
            'deactivated_sections' => 0,
            'deactivated_placements' => 0,
        ];

        foreach ($zones as $zone) {
            $keptSectionIds = [];

            foreach (array_values($sectionsByZone[$zone] ?? []) as $sectionIndex => $sectionSnapshot) {
                $section = $this->restoreSection($owner, $zone, $sectionSnapshot, $sectionIndex);
                $keptSectionIds[] = (int) $section->id;

                $warnings['deactivated_placements'] += $this->restorePlacements($section, $sectionSnapshot['placements'] ?? [], $createdBy);
            }

            $deactivated = $owner->sections()
                ->where('zone', $zone)
                ->when($keptSectionIds !== [], fn ($query) => $query->whereNotIn('id', $keptSectionIds))
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $warnings['deactivated_sections'] += (int) $deactivated;
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $sectionSnapshot
     */
    private function restoreSection(Model $owner, string $zone, array $sectionSnapshot, int $sectionIndex): CmsSection
    {
        $revisionKey = $this->revisionKey($sectionSnapshot['revision_key'] ?? null);
        $section = $owner->sections()
            ->where('zone', $zone)
            ->where('revision_key', $revisionKey)
            ->first() ?? new CmsSection;

        $section->fill([
            'revision_key' => $revisionKey,
            'zone' => $zone,
            'name' => $this->nullableString($sectionSnapshot['name'] ?? null),
            'sort_order' => $sectionIndex,
            'is_active' => (bool) ($sectionSnapshot['is_active'] ?? true),
            'visible_mobile' => (bool) ($sectionSnapshot['visible_mobile'] ?? true),
            'visible_tablet' => (bool) ($sectionSnapshot['visible_tablet'] ?? true),
            'visible_desktop' => (bool) ($sectionSnapshot['visible_desktop'] ?? true),
            'settings' => is_array($sectionSnapshot['settings'] ?? null) ? $sectionSnapshot['settings'] : [],
        ]);

        $owner->sections()->save($section);

        return $section;
    }

    /**
     * @param  array<int, array<string, mixed>>  $placementSnapshots
     */
    private function restorePlacements(CmsSection $section, array $placementSnapshots, ?int $createdBy): int
    {
        $keptPlacementIds = [];

        foreach (array_values($placementSnapshots) as $placementIndex => $placementSnapshot) {
            $block = $this->restoreBlock($placementSnapshot['block'] ?? [], $createdBy);
            $placementRevisionKey = $this->revisionKey($placementSnapshot['revision_key'] ?? null);
            $placement = CmsBlockPlacement::query()
                ->where('revision_key', $placementRevisionKey)
                ->first() ?? new CmsBlockPlacement;

            $placement->fill([
                'revision_key' => $placementRevisionKey,
                'cms_block_id' => $block->id,
                'sort_order' => $placementIndex,
                'is_active' => (bool) ($placementSnapshot['is_active'] ?? true),
                'visible_mobile' => (bool) ($placementSnapshot['visible_mobile'] ?? true),
                'visible_tablet' => (bool) ($placementSnapshot['visible_tablet'] ?? true),
                'visible_desktop' => (bool) ($placementSnapshot['visible_desktop'] ?? true),
                'mobile_span' => $this->span($placementSnapshot['mobile_span'] ?? 12),
                'tablet_span' => $this->span($placementSnapshot['tablet_span'] ?? 12),
                'desktop_span' => $this->span($placementSnapshot['desktop_span'] ?? 12),
                'layout_config' => is_array($placementSnapshot['layout_config'] ?? null) ? $placementSnapshot['layout_config'] : [],
                'style_config' => is_array($placementSnapshot['style_config'] ?? null) ? $placementSnapshot['style_config'] : [],
                'height_mode' => $this->heightMode($placementSnapshot['height_mode'] ?? 'auto'),
                'height_value' => $this->nullableString($placementSnapshot['height_value'] ?? null),
                'cache_strategy' => $this->cacheStrategy($placementSnapshot['cache_strategy'] ?? 'inherit'),
                'settings' => is_array($placementSnapshot['settings'] ?? null) ? $placementSnapshot['settings'] : [],
            ]);

            $section->placements()->save($placement);
            $keptPlacementIds[] = (int) $placement->id;
        }

        return (int) $section->placements()
            ->when($keptPlacementIds !== [], fn ($query) => $query->whereNotIn('id', $keptPlacementIds))
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * @param  array<string, mixed>  $blockSnapshot
     */
    private function restoreBlock(array $blockSnapshot, ?int $createdBy): CmsBlock
    {
        $revisionKey = $this->revisionKey($blockSnapshot['revision_key'] ?? null);
        $block = CmsBlock::query()->where('revision_key', $revisionKey)->first() ?? new CmsBlock;

        $block->fill([
            'revision_key' => $revisionKey,
            'type' => $this->blockType($blockSnapshot['type'] ?? 'text'),
            'name' => $this->nullableString($blockSnapshot['name'] ?? null),
            'content' => is_array($blockSnapshot['content'] ?? null) ? $blockSnapshot['content'] : [],
            'settings' => is_array($blockSnapshot['settings'] ?? null) ? $blockSnapshot['settings'] : [],
            'is_shared' => (bool) ($blockSnapshot['is_shared'] ?? false),
            'is_dynamic' => (bool) ($blockSnapshot['is_dynamic'] ?? false),
            'cache_strategy' => $this->cacheStrategy($blockSnapshot['cache_strategy'] ?? 'inherit'),
            'created_by' => $block->created_by ?: ($blockSnapshot['created_by'] ?? $createdBy),
        ])->save();

        return $block;
    }

    private function revisionKey(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : (string) Str::ulid();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function span(mixed $value): int
    {
        return min(max((int) $value, 2), 12);
    }

    private function heightMode(mixed $value): string
    {
        return in_array($value, ['auto', 'fixed', 'min'], true) ? (string) $value : 'auto';
    }

    private function cacheStrategy(mixed $value): string
    {
        return in_array($value, ['inherit', 'none', 'block', 'layout'], true) ? (string) $value : 'inherit';
    }

    private function blockType(mixed $value): string
    {
        $type = is_scalar($value) ? (string) $value : 'text';

        return $type === '' ? 'text' : $type;
    }
}
