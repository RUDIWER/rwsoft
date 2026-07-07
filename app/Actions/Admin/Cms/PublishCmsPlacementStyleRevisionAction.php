<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsBlockPlacementStyleRevision;
use App\Support\Cms\CmsResponsiveLayoutNormalizer;
use Illuminate\Support\Facades\DB;

class PublishCmsPlacementStyleRevisionAction
{
    public function __construct(
        private readonly CmsResponsiveLayoutNormalizer $layoutNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $styleConfig
     */
    public function handle(CmsBlockPlacement $placement, string $cssSource, array $styleConfig, ?int $authorId): CmsBlockPlacementStyleRevision
    {
        return DB::transaction(function () use ($placement, $cssSource, $styleConfig, $authorId): CmsBlockPlacementStyleRevision {
            $normalizedStyleConfig = $this->layoutNormalizer->normalizeStyle($styleConfig);
            $normalizedStyleConfig['developer'] = [
                'css_source' => trim($cssSource),
            ];
            $revisionNumber = ((int) $placement->styleRevisions()->max('revision_number')) + 1;

            $revision = $placement->styleRevisions()->create([
                'revision_number' => $revisionNumber,
                'status' => 'published',
                'title' => __('cms_admin_ui.components.block_editor.style_revision_title', ['number' => $revisionNumber]),
                'style_config' => $normalizedStyleConfig,
                'css_source' => trim($cssSource),
                'snapshot_hash' => hash('sha256', json_encode([
                    'placement_id' => $placement->id,
                    'style_config' => $normalizedStyleConfig,
                    'css_source' => trim($cssSource),
                ], JSON_THROW_ON_ERROR)),
                'author_id' => $authorId,
                'metadata' => ['source' => 'layout_builder'],
                'published_at' => now(),
            ]);

            $placement->forceFill([
                'style_config' => $normalizedStyleConfig,
                'published_style_revision_id' => $revision->id,
            ])->save();

            return $revision;
        });
    }

    public function republish(CmsBlockPlacement $placement, CmsBlockPlacementStyleRevision $revision): CmsBlockPlacementStyleRevision
    {
        return DB::transaction(function () use ($placement, $revision): CmsBlockPlacementStyleRevision {
            $placement->forceFill([
                'style_config' => $this->layoutNormalizer->normalizeStyle($revision->style_config ?? []),
                'published_style_revision_id' => $revision->id,
            ])->save();

            return $revision;
        });
    }
}
