<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsRevision;
use App\Models\Cms\CmsTag;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RestoreCmsPostRevisionAction
{
    public function __construct(
        private readonly BuildCmsPostRevisionSnapshotAction $buildSnapshot,
        private readonly CreateCmsRevisionAction $createRevision,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CmsPost $post, CmsRevision $revision, string $mode, ?int $authorId = null): array
    {
        return DB::transaction(function () use ($post, $revision, $mode, $authorId): array {
            $lockedPost = CmsPost::query()->lockForUpdate()->findOrFail($post->id);
            $this->assertRevisionMatches($lockedPost, $revision);

            $this->createRevision->handle(
                $lockedPost,
                'restore_backup',
                $this->buildSnapshot->handle($lockedPost),
                $authorId,
                __('cms_admin_ui.revisions.restore_backup_title'),
                forceCreate: true,
            );

            $snapshot = $revision->snapshot['post'] ?? [];
            $warnings = ['missing_categories' => 0, 'missing_tags' => 0];

            $this->restoreContentFields($lockedPost, $snapshot);

            if ($mode === 'full') {
                $this->restoreStructureFields($lockedPost, $snapshot);
                $warnings['missing_categories'] = $this->syncCategories($lockedPost, $snapshot['category_ids'] ?? []);
                $warnings['missing_tags'] = $this->syncTags($lockedPost, $snapshot['tag_ids'] ?? []);
            }

            $this->createRevision->handle(
                $lockedPost->fresh() ?: $lockedPost,
                'restore',
                $this->buildSnapshot->handle($lockedPost->fresh() ?: $lockedPost),
                $authorId,
                __('cms_admin_ui.revisions.restored_revision_title', ['number' => $revision->revision_number]),
                (int) $revision->id,
                ['restore_mode' => $mode, 'warnings' => $warnings],
            );

            return $warnings;
        });
    }

    private function assertRevisionMatches(CmsPost $post, CmsRevision $revision): void
    {
        abort_unless($revision->subject_type === CmsPost::class && (int) $revision->subject_id === (int) $post->id, 404);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function restoreContentFields(CmsPost $post, array $snapshot): void
    {
        $post->forceFill(Arr::only($snapshot, [
            'title',
            'excerpt',
            'seo_title',
            'seo_description',
            'canonical_url',
            'og_image_path',
            'noindex',
            'is_searchable',
        ]))->save();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function restoreStructureFields(CmsPost $post, array $snapshot): void
    {
        $post->forceFill(Arr::only($snapshot, [
            'status',
            'content_blocks',
            'featured_media_asset_id',
            'is_featured',
            'published_at',
            'settings',
        ]))->save();
    }

    /**
     * @param  array<int, mixed>  $categoryIds
     */
    private function syncCategories(CmsPost $post, array $categoryIds): int
    {
        $requestedIds = collect($categoryIds)->map(fn (mixed $id): int => (int) $id)->filter()->values();
        $existingIds = CmsCategory::query()
            ->whereIn('id', $requestedIds)
            ->where('type', 'post')
            ->where('locale', $post->locale)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $post->categories()->sync($existingIds);

        return $requestedIds->diff($existingIds)->count();
    }

    /**
     * @param  array<int, mixed>  $tagIds
     */
    private function syncTags(CmsPost $post, array $tagIds): int
    {
        $requestedIds = collect($tagIds)->map(fn (mixed $id): int => (int) $id)->filter()->values();
        $existingIds = CmsTag::query()
            ->whereIn('id', $requestedIds)
            ->where('locale', $post->locale)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $post->tags()->sync($existingIds);

        return $requestedIds->diff($existingIds)->count();
    }
}
