<?php

namespace App\Support\PublicSite;

class CmsCompositionStyleCollector
{
    /**
     * @param  array<string, array<int, array<string, mixed>>>  $sectionsByZone
     * @return array<int, array<string, mixed>>
     */
    public function handle(array $sectionsByZone): array
    {
        $styles = [];
        $seen = [];

        foreach ($sectionsByZone as $sections) {
            foreach ($sections as $section) {
                $this->collectSectionStyles($section, $styles, $seen);
            }
        }

        return array_values($styles);
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, array<string, mixed>>  $styles
     * @param  array<string, bool>  $seen
     */
    private function collectSectionStyles(array $section, array &$styles, array &$seen): void
    {
        foreach (($section['placements'] ?? []) as $placement) {
            if (! is_array($placement)) {
                continue;
            }

            $this->collectPlacementStyles($placement, $styles, $seen);
        }
    }

    /**
     * @param  array<string, mixed>  $placement
     * @param  array<string, array<string, mixed>>  $styles
     * @param  array<string, bool>  $seen
     */
    private function collectPlacementStyles(array $placement, array &$styles, array &$seen): void
    {
        $block = is_array($placement['block'] ?? null) ? $placement['block'] : [];
        $placeableBlock = is_array($block['placeable_block'] ?? null) ? $block['placeable_block'] : [];
        $placeableBlockCss = trim((string) ($placeableBlock['css_source'] ?? ''));

        if ($placeableBlockCss !== '') {
            $revisionId = (int) ($placeableBlock['revision_id'] ?? 0);
            $key = $revisionId > 0
                ? 'placeable-block-revision:'.$revisionId
                : 'placeable-block-css:'.hash('sha256', $placeableBlockCss);

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $styles[$key] = [
                    'type' => 'placeable_block_revision',
                    'cms_placeable_block_id' => (int) ($placeableBlock['id'] ?? 0),
                    'revision_id' => $revisionId,
                    'css_source' => $placeableBlockCss,
                ];
            }
        }

        $styleRevision = is_array($placement['published_style_revision'] ?? null)
            ? $placement['published_style_revision']
            : [];
        $styleRevisionCss = trim((string) ($styleRevision['css_source'] ?? ''));

        if ($styleRevisionCss !== '') {
            $styleRevisionId = (int) ($styleRevision['id'] ?? 0);
            $key = $styleRevisionId > 0
                ? 'placement-style-revision:'.$styleRevisionId
                : 'placement-style-css:'.hash('sha256', $styleRevisionCss);

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $styles[$key] = [
                    'type' => 'placement_style_revision',
                    'revision_id' => $styleRevisionId,
                    'css_source' => $styleRevisionCss,
                ];
            }
        }

        foreach (($block['sections'] ?? []) as $section) {
            if (! is_array($section)) {
                continue;
            }

            $this->collectSectionStyles($section, $styles, $seen);
        }

        foreach (($placement['slots'] ?? []) as $slot) {
            if (! is_array($slot)) {
                continue;
            }

            foreach (($slot['placements'] ?? []) as $slotPlacement) {
                if (is_array($slotPlacement)) {
                    $this->collectPlacementStyles($slotPlacement, $styles, $seen);
                }
            }
        }
    }
}
