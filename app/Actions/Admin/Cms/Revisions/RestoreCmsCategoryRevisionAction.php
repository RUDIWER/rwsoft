<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsRevision;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestoreCmsCategoryRevisionAction
{
    public function __construct(
        private readonly BuildCmsCategoryRevisionSnapshotAction $buildSnapshot,
        private readonly CreateCmsRevisionAction $createRevision,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CmsCategory $category, CmsRevision $revision, string $mode, ?int $authorId = null): array
    {
        return DB::transaction(function () use ($category, $revision, $mode, $authorId): array {
            $lockedCategory = CmsCategory::query()->with('landingPage')->lockForUpdate()->findOrFail($category->id);
            $this->assertRevisionMatches($lockedCategory, $revision);

            $snapshot = $revision->snapshot['category'] ?? [];

            if ($mode === 'full') {
                $this->assertTaxonomyRestoreIsSafe($lockedCategory, $snapshot);
            }

            $this->createRevision->handle(
                $lockedCategory,
                'restore_backup',
                $this->buildSnapshot->handle($lockedCategory),
                $authorId,
                __('cms_admin_ui.revisions.restore_backup_title'),
                forceCreate: true,
            );

            $this->restoreCategoryFields($lockedCategory, $snapshot, $mode);
            $this->restoreLandingPageFields($lockedCategory, $snapshot['landing_page'] ?? [], $mode);

            $warnings = ['blocked_relations' => 0];

            $this->createRevision->handle(
                $lockedCategory->fresh() ?: $lockedCategory,
                'restore',
                $this->buildSnapshot->handle($lockedCategory->fresh() ?: $lockedCategory),
                $authorId,
                __('cms_admin_ui.revisions.restored_revision_title', ['number' => $revision->revision_number]),
                (int) $revision->id,
                ['restore_mode' => $mode, 'warnings' => $warnings],
            );

            return $warnings;
        });
    }

    private function assertRevisionMatches(CmsCategory $category, CmsRevision $revision): void
    {
        abort_unless($revision->subject_type === CmsCategory::class && (int) $revision->subject_id === (int) $category->id, 404);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     *
     * @throws ValidationException
     */
    private function assertTaxonomyRestoreIsSafe(CmsCategory $category, array $snapshot): void
    {
        $isUsed = $category->posts()->exists();

        if ($isUsed && (! (bool) ($snapshot['is_active'] ?? true) || (string) ($snapshot['type'] ?? '') !== (string) $category->type || (string) ($snapshot['locale'] ?? '') !== (string) $category->locale)) {
            throw ValidationException::withMessages(['revision' => __('cms_admin_ui.revisions.taxonomy_restore_used_term_blocked')]);
        }

        $parentId = (int) ($snapshot['parent_id'] ?? 0);

        if ($parentId > 0 && in_array($parentId, $this->descendantIds($category->id), true)) {
            throw ValidationException::withMessages(['revision' => __('cms_admin_ui.revisions.taxonomy_restore_parent_cycle')]);
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function restoreCategoryFields(CmsCategory $category, array $snapshot, string $mode): void
    {
        $fields = ['title', 'slug', 'description'];

        if ($mode === 'full') {
            $fields = array_merge($fields, ['parent_id', 'type', 'locale', 'sort_order', 'is_active', 'settings']);
        }

        $category->forceFill(Arr::only($snapshot, $fields))->save();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function restoreLandingPageFields(CmsCategory $category, array $snapshot, string $mode): void
    {
        $page = $category->landingPage;

        if (! $page || $snapshot === []) {
            return;
        }

        $fields = ['short_description', 'content_blocks', 'seo_title', 'seo_description', 'canonical_url', 'og_image_path', 'noindex', 'is_searchable'];

        if ($mode === 'full') {
            $fields = array_merge($fields, ['status', 'template', 'published_at', 'settings']);
        }

        $page->forceFill(Arr::only($snapshot, $fields))->save();
    }

    /**
     * @return array<int, int>
     */
    private function descendantIds(int $categoryId): array
    {
        $categories = CmsCategory::query()->get(['id', 'parent_id']);
        $descendantIds = [$categoryId];
        $frontier = [$categoryId];

        while ($frontier !== []) {
            $children = $categories
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();

            $frontier = array_values(array_diff($children, $descendantIds));
            $descendantIds = array_values(array_unique(array_merge($descendantIds, $frontier)));
        }

        return $descendantIds;
    }
}
