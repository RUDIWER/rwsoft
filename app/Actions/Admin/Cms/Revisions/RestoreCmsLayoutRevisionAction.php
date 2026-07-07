<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsRevision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RestoreCmsLayoutRevisionAction
{
    public function __construct(
        private readonly BuildCmsLayoutRevisionSnapshotAction $buildSnapshot,
        private readonly CreateCmsRevisionAction $createRevision,
        private readonly RestoreCmsSectionSnapshotsAction $restoreSections,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws AuthorizationException
     */
    public function handle(CmsLayout $layout, CmsRevision $revision, string $mode, bool $canManageCodeBlocks, ?int $authorId = null): array
    {
        return DB::transaction(function () use ($layout, $revision, $mode, $canManageCodeBlocks, $authorId): array {
            $lockedLayout = CmsLayout::query()->lockForUpdate()->findOrFail($layout->id);
            $this->assertRevisionMatches($lockedLayout, $revision);

            $snapshot = $revision->snapshot ?? [];

            if (! $canManageCodeBlocks && $this->containsCodeBlock($snapshot['sections'] ?? [])) {
                throw new AuthorizationException(__('cms_admin_ui.revisions.code_block_restore_forbidden'));
            }

            $this->createRevision->handle(
                $lockedLayout,
                'restore_backup',
                $this->buildSnapshot->handle($lockedLayout),
                $authorId,
                __('cms_admin_ui.revisions.restore_backup_title'),
                forceCreate: true,
            );

            $this->restoreLayoutFields($lockedLayout, $snapshot['layout'] ?? []);

            $warnings = $mode === 'full'
                ? $this->restoreSections->handle($lockedLayout, $snapshot['sections'] ?? [], ['head', 'header', 'footer', 'body_end'], $authorId)
                : ['missing_blocks' => $this->restoreExistingBlockContent($lockedLayout, $snapshot['sections'] ?? [])];

            $this->createRevision->handle(
                $lockedLayout->fresh() ?: $lockedLayout,
                'restore',
                $this->buildSnapshot->handle($lockedLayout->fresh() ?: $lockedLayout),
                $authorId,
                __('cms_admin_ui.revisions.restored_revision_title', ['number' => $revision->revision_number]),
                (int) $revision->id,
                ['restore_mode' => $mode, 'warnings' => $warnings],
            );

            return $warnings;
        });
    }

    private function assertRevisionMatches(CmsLayout $layout, CmsRevision $revision): void
    {
        abort_unless($revision->subject_type === CmsLayout::class && (int) $revision->subject_id === (int) $layout->id, 404);
    }

    /**
     * @param  array<string, mixed>  $layoutSnapshot
     */
    private function restoreLayoutFields(CmsLayout $layout, array $layoutSnapshot): void
    {
        $layout->forceFill(Arr::only($layoutSnapshot, [
            'name',
            'cache_strategy',
            'settings',
        ]))->save();
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $sectionsByZone
     */
    private function restoreExistingBlockContent(CmsLayout $layout, array $sectionsByZone): int
    {
        $currentBlocks = CmsBlock::query()
            ->whereHas('placements.section', fn ($query) => $query
                ->where('owner_type', CmsLayout::class)
                ->where('owner_id', $layout->id))
            ->whereNotNull('revision_key')
            ->get()
            ->keyBy('revision_key');

        $missingBlocks = 0;

        foreach ($this->blockSnapshots($sectionsByZone) as $blockSnapshot) {
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
     * @param  array<string, array<int, array<string, mixed>>>  $sectionsByZone
     * @return array<int, array<string, mixed>>
     */
    private function blockSnapshots(array $sectionsByZone): array
    {
        return collect($sectionsByZone)
            ->flatten(1)
            ->flatMap(fn (array $section): array => $section['placements'] ?? [])
            ->map(fn (array $placement): array => $placement['block'] ?? [])
            ->filter(fn (array $block): bool => $block !== [])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $sectionsByZone
     */
    private function containsCodeBlock(array $sectionsByZone): bool
    {
        return collect($this->blockSnapshots($sectionsByZone))
            ->contains(fn (array $block): bool => in_array($block['type'] ?? null, ['custom_head_code', 'custom_body_end_code'], true));
    }
}
