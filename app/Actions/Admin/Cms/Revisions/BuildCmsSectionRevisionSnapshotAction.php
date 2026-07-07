<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsSection;
use Illuminate\Support\Collection;

class BuildCmsSectionRevisionSnapshotAction
{
    /**
     * @param  Collection<int, CmsSection>  $sections
     * @return array<int, array<string, mixed>>
     */
    public function handle(Collection $sections): array
    {
        return $sections
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->map(fn (CmsSection $section): array => $this->sectionPayload($section))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionPayload(CmsSection $section): array
    {
        return [
            'id' => $section->id,
            'revision_key' => $section->revision_key,
            'zone' => $section->zone,
            'name' => $section->name,
            'sort_order' => (int) $section->sort_order,
            'is_active' => (bool) $section->is_active,
            'visible_mobile' => (bool) $section->visible_mobile,
            'visible_tablet' => (bool) $section->visible_tablet,
            'visible_desktop' => (bool) $section->visible_desktop,
            'settings' => $section->settings ?? [],
            'placements' => $section->placements
                ->where('is_active', true)
                ->sortBy('sort_order')
                ->map(fn (CmsBlockPlacement $placement): array => $this->placementPayload($placement))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function placementPayload(CmsBlockPlacement $placement): array
    {
        return [
            'id' => $placement->id,
            'revision_key' => $placement->revision_key,
            'sort_order' => (int) $placement->sort_order,
            'is_active' => (bool) $placement->is_active,
            'visible_mobile' => (bool) $placement->visible_mobile,
            'visible_tablet' => (bool) $placement->visible_tablet,
            'visible_desktop' => (bool) $placement->visible_desktop,
            'mobile_span' => (int) $placement->mobile_span,
            'tablet_span' => (int) $placement->tablet_span,
            'desktop_span' => (int) $placement->desktop_span,
            'layout_config' => $placement->layout_config ?? [],
            'style_config' => $placement->style_config ?? [],
            'height_mode' => $placement->height_mode,
            'height_value' => $placement->height_value,
            'cache_strategy' => $placement->cache_strategy,
            'settings' => $placement->settings ?? [],
            'block' => $this->blockPayload($placement->block),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blockPayload(?CmsBlock $block): array
    {
        if (! $block instanceof CmsBlock) {
            return [];
        }

        return [
            'id' => $block->id,
            'revision_key' => $block->revision_key,
            'type' => $block->type,
            'name' => $block->name,
            'content' => $block->content ?? [],
            'settings' => $block->settings ?? [],
            'is_shared' => (bool) $block->is_shared,
            'is_dynamic' => (bool) $block->is_dynamic,
            'cache_strategy' => $block->cache_strategy,
            'created_by' => $block->created_by,
        ];
    }
}
