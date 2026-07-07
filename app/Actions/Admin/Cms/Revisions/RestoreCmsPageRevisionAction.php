<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsRevision;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RestoreCmsPageRevisionAction
{
    public function __construct(
        private readonly BuildCmsPageRevisionSnapshotAction $buildSnapshot,
        private readonly CreateCmsRevisionAction $createRevision,
        private readonly RestoreCmsSectionSnapshotsAction $restoreSections,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CmsPage $page, CmsRevision $revision, string $mode, ?int $authorId = null): array
    {
        return DB::transaction(function () use ($page, $revision, $mode, $authorId): array {
            $lockedPage = CmsPage::query()->lockForUpdate()->findOrFail($page->id);
            $this->assertRevisionMatches($lockedPage, $revision);

            $this->createRevision->handle(
                $lockedPage,
                'restore_backup',
                $this->buildSnapshot->handle($lockedPage),
                $authorId,
                __('cms_admin_ui.revisions.restore_backup_title'),
                forceCreate: true,
            );

            $snapshot = $revision->snapshot ?? [];
            $warnings = ['missing_blocks' => 0];

            $this->restorePageFields($lockedPage, $snapshot['page'] ?? []);

            if ($mode === 'full') {
                $warnings = array_merge($warnings, $this->restoreSections->handle(
                    $lockedPage,
                    $snapshot['sections'] ?? [],
                    ['content'],
                    $authorId,
                ));
            } else {
                $warnings['missing_blocks'] = $this->restoreExistingBlockContent($lockedPage, $snapshot['sections']['content'] ?? []);
            }

            $this->createRevision->handle(
                $lockedPage->fresh() ?: $lockedPage,
                'restore',
                $this->buildSnapshot->handle($lockedPage->fresh() ?: $lockedPage),
                $authorId,
                __('cms_admin_ui.revisions.restored_revision_title', ['number' => $revision->revision_number]),
                (int) $revision->id,
                ['restore_mode' => $mode, 'warnings' => $warnings],
            );

            return $warnings;
        });
    }

    private function assertRevisionMatches(CmsPage $page, CmsRevision $revision): void
    {
        abort_unless($revision->subject_type === CmsPage::class && (int) $revision->subject_id === (int) $page->id, 404);
    }

    /**
     * @param  array<string, mixed>  $pageSnapshot
     */
    private function restorePageFields(CmsPage $page, array $pageSnapshot): void
    {
        $page->forceFill(Arr::only($pageSnapshot, [
            'title',
            'short_description',
            'seo_title',
            'seo_description',
            'canonical_url',
            'og_image_path',
            'noindex',
            'is_searchable',
            'settings',
        ]))->save();
    }

    /**
     * @param  array<int, array<string, mixed>>  $sectionSnapshots
     */
    private function restoreExistingBlockContent(CmsPage $page, array $sectionSnapshots): int
    {
        $currentBlocks = CmsBlock::query()
            ->whereHas('placements.section', fn ($query) => $query
                ->where('owner_type', CmsPage::class)
                ->where('owner_id', $page->id)
                ->where('zone', 'content'))
            ->whereNotNull('revision_key')
            ->get()
            ->keyBy('revision_key');

        $missingBlocks = 0;

        foreach ($this->blockSnapshots($sectionSnapshots) as $blockSnapshot) {
            $revisionKey = (string) ($blockSnapshot['revision_key'] ?? '');
            $block = $revisionKey !== '' ? $currentBlocks->get($revisionKey) : null;

            if (! $block instanceof CmsBlock) {
                $missingBlocks++;

                continue;
            }

            $block->forceFill([
                'name' => $blockSnapshot['name'] ?? null,
                'content' => is_array($blockSnapshot['content'] ?? null) ? $blockSnapshot['content'] : [],
                'cache_strategy' => $blockSnapshot['cache_strategy'] ?? $block->cache_strategy,
            ])->save();
        }

        return $missingBlocks;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sectionSnapshots
     * @return array<int, array<string, mixed>>
     */
    private function blockSnapshots(array $sectionSnapshots): array
    {
        return collect($sectionSnapshots)
            ->flatMap(fn (array $section): array => $section['placements'] ?? [])
            ->map(fn (array $placement): array => $placement['block'] ?? [])
            ->filter(fn (array $block): bool => $block !== [])
            ->values()
            ->all();
    }
}
