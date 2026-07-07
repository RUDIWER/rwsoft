<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsRevision;
use App\Models\Cms\CmsTag;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestoreCmsTagRevisionAction
{
    public function __construct(
        private readonly BuildCmsTagRevisionSnapshotAction $buildSnapshot,
        private readonly CreateCmsRevisionAction $createRevision,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CmsTag $tag, CmsRevision $revision, string $mode, ?int $authorId = null): array
    {
        return DB::transaction(function () use ($tag, $revision, $mode, $authorId): array {
            $lockedTag = CmsTag::query()->with('landingPage')->lockForUpdate()->findOrFail($tag->id);
            $this->assertRevisionMatches($lockedTag, $revision);

            $snapshot = $revision->snapshot['tag'] ?? [];

            if ($mode === 'full') {
                $this->assertTaxonomyRestoreIsSafe($lockedTag, $snapshot);
            }

            $this->createRevision->handle(
                $lockedTag,
                'restore_backup',
                $this->buildSnapshot->handle($lockedTag),
                $authorId,
                __('cms_admin_ui.revisions.restore_backup_title'),
                forceCreate: true,
            );

            $this->restoreTagFields($lockedTag, $snapshot, $mode);
            $this->restoreLandingPageFields($lockedTag, $snapshot['landing_page'] ?? [], $mode);

            $warnings = ['blocked_relations' => 0];

            $this->createRevision->handle(
                $lockedTag->fresh() ?: $lockedTag,
                'restore',
                $this->buildSnapshot->handle($lockedTag->fresh() ?: $lockedTag),
                $authorId,
                __('cms_admin_ui.revisions.restored_revision_title', ['number' => $revision->revision_number]),
                (int) $revision->id,
                ['restore_mode' => $mode, 'warnings' => $warnings],
            );

            return $warnings;
        });
    }

    private function assertRevisionMatches(CmsTag $tag, CmsRevision $revision): void
    {
        abort_unless($revision->subject_type === CmsTag::class && (int) $revision->subject_id === (int) $tag->id, 404);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     *
     * @throws ValidationException
     */
    private function assertTaxonomyRestoreIsSafe(CmsTag $tag, array $snapshot): void
    {
        $isUsed = $tag->posts()->exists();

        if ($isUsed && (! (bool) ($snapshot['is_active'] ?? true) || (string) ($snapshot['locale'] ?? '') !== (string) $tag->locale)) {
            throw ValidationException::withMessages(['revision' => __('cms_admin_ui.revisions.taxonomy_restore_used_term_blocked')]);
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function restoreTagFields(CmsTag $tag, array $snapshot, string $mode): void
    {
        $fields = ['title', 'slug', 'description'];

        if ($mode === 'full') {
            $fields = array_merge($fields, ['locale', 'is_active', 'settings']);
        }

        $tag->forceFill(Arr::only($snapshot, $fields))->save();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function restoreLandingPageFields(CmsTag $tag, array $snapshot, string $mode): void
    {
        $page = $tag->landingPage;

        if (! $page || $snapshot === []) {
            return;
        }

        $fields = ['short_description', 'content_blocks', 'seo_title', 'seo_description', 'canonical_url', 'og_image_path', 'noindex', 'is_searchable'];

        if ($mode === 'full') {
            $fields = array_merge($fields, ['status', 'template', 'published_at', 'settings']);
        }

        $page->forceFill(Arr::only($snapshot, $fields))->save();
    }
}
