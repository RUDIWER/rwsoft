<?php

namespace App\Support\Cms;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsPlaceableBlockRevision;

class CmsPlaceableBlockResolver
{
    public function publishedRevisionForBlock(?CmsBlock $block): ?CmsPlaceableBlockRevision
    {
        if (! ($block instanceof CmsBlock) || ! ($block->placeableBlock instanceof CmsPlaceableBlock)) {
            return null;
        }

        if ($block->placeableBlock->status !== 'published') {
            return null;
        }

        if ($block->placeable_block_revision_id) {
            $revision = $block->relationLoaded('placeableBlockRevision')
                ? $block->placeableBlockRevision
                : $block->placeableBlockRevision()->first();

            if ($this->isPublishedRevision($revision, $block->placeableBlock)) {
                return $revision;
            }
        }

        if ($block->placeableBlock->relationLoaded('revisions')) {
            return $block->placeableBlock->revisions
                ->first(fn (CmsPlaceableBlockRevision $revision): bool => $this->isPublishedRevision($revision, $block->placeableBlock));
        }

        return $block->placeableBlock
            ->revisions()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function payloadForBlock(?CmsBlock $block): ?array
    {
        $revision = $this->publishedRevisionForBlock($block);

        if (! $revision instanceof CmsPlaceableBlockRevision || ! $block?->placeableBlock instanceof CmsPlaceableBlock) {
            return null;
        }

        return [
            'id' => (int) $block->placeableBlock->id,
            'key' => (string) $block->placeableBlock->key,
            'name' => (string) $block->placeableBlock->name,
            'category' => (string) ($revision->category ?: $block->placeableBlock->category ?: 'content'),
            'source' => (string) ($revision->source ?: $block->placeableBlock->source ?: 'user'),
            'revision_id' => (int) $revision->id,
            'revision_number' => (int) $revision->revision_number,
            'allowed_zones' => $revision->allowed_zones ?? [],
            'rendering_mode' => (string) $revision->rendering_mode,
            'renderer_key' => $revision->renderer_key,
            'template_source' => (string) ($revision->template_source ?? ''),
            'css_source' => (string) ($revision->css_source ?? ''),
            'schema' => $revision->schema ?? [],
            'defaults' => $revision->defaults ?? [],
            'capabilities' => $revision->capabilities ?? [],
            'behavior_config' => $revision->behavior_config ?? [],
            'context_config' => $revision->context_config ?? [],
            'admin_component_key' => $revision->admin_component_key,
            'package_key' => $revision->package_key,
            'is_locked' => (bool) $revision->is_locked,
            'requires_permission' => $revision->requires_permission,
            'published_at' => $revision->published_at?->toIso8601String(),
        ];
    }

    private function isPublishedRevision(?CmsPlaceableBlockRevision $revision, CmsPlaceableBlock $placeableBlock): bool
    {
        return $revision instanceof CmsPlaceableBlockRevision
            && (int) $revision->cms_placeable_block_id === (int) $placeableBlock->id
            && $revision->status === 'published'
            && $revision->published_at !== null;
    }
}
