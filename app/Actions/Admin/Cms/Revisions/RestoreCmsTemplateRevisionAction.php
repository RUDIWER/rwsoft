<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsRevision;
use App\Models\Cms\CmsTemplate;
use App\Support\Cms\CmsTemplateRegistry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RestoreCmsTemplateRevisionAction
{
    public function __construct(
        private readonly BuildCmsTemplateRevisionSnapshotAction $buildSnapshot,
        private readonly CreateCmsRevisionAction $createRevision,
        private readonly RestoreCmsSectionSnapshotsAction $restoreSections,
        private readonly CmsTemplateRegistry $templateRegistry,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CmsTemplate $template, CmsRevision $revision, string $mode, ?int $authorId = null): array
    {
        return DB::transaction(function () use ($template, $revision, $mode, $authorId): array {
            $lockedTemplate = CmsTemplate::query()->lockForUpdate()->findOrFail($template->id);
            $this->assertRevisionMatches($lockedTemplate, $revision);

            $this->createRevision->handle(
                $lockedTemplate,
                'restore_backup',
                $this->buildSnapshot->handle($lockedTemplate),
                $authorId,
                __('cms_admin_ui.revisions.restore_backup_title'),
                forceCreate: true,
            );

            $snapshot = $revision->snapshot ?? [];
            $templateSnapshot = is_array($snapshot['template'] ?? null) ? $snapshot['template'] : [];
            $warnings = ['blocked_relations' => 0, 'missing_blocks' => 0];

            $this->restoreTemplateFields($lockedTemplate, $templateSnapshot, $mode, $warnings);

            if ($mode === 'full') {
                $warnings = array_merge($warnings, $this->restoreSections->handle(
                    $lockedTemplate,
                    $snapshot['sections'] ?? [],
                    ['content'],
                    $authorId,
                ));
            } else {
                $warnings['missing_blocks'] = $this->restoreExistingBlockContent($lockedTemplate, $snapshot['sections']['content'] ?? []);
            }

            $this->createRevision->handle(
                $lockedTemplate->fresh() ?: $lockedTemplate,
                'restore',
                $this->buildSnapshot->handle($lockedTemplate->fresh() ?: $lockedTemplate),
                $authorId,
                __('cms_admin_ui.revisions.restored_revision_title', ['number' => $revision->revision_number]),
                (int) $revision->id,
                ['restore_mode' => $mode, 'warnings' => $warnings],
            );

            return $warnings;
        });
    }

    private function assertRevisionMatches(CmsTemplate $template, CmsRevision $revision): void
    {
        abort_unless($revision->subject_type === CmsTemplate::class && (int) $revision->subject_id === (int) $template->id, 404);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $warnings
     */
    private function restoreTemplateFields(CmsTemplate $template, array $snapshot, string $mode, array &$warnings): void
    {
        $fields = ['name', 'cache_strategy', 'settings'];

        if ($mode === 'full') {
            $fields = array_merge($fields, ['template_class', 'template_key', 'locale', 'layout_id', 'is_default', 'is_active']);
        }

        $data = Arr::only($snapshot, $fields);

        if ($mode === 'full') {
            $this->filterUnsafeFullRestoreFields($template, $data, $warnings);
        }

        $template->forceFill($data)->save();

        if ((bool) ($data['is_default'] ?? false)) {
            CmsTemplate::query()
                ->where('template_key', $template->template_key)
                ->where('locale', $template->locale)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $warnings
     */
    private function filterUnsafeFullRestoreFields(CmsTemplate $template, array &$data, array &$warnings): void
    {
        $templateClass = (string) ($data['template_class'] ?? $template->template_class);
        $templateKey = (string) ($data['template_key'] ?? $template->template_key);
        $locale = (string) ($data['locale'] ?? $template->locale);

        if (! $this->templateRegistry->isValidTemplateKey($templateKey, $templateClass)) {
            unset($data['template_class'], $data['template_key']);
            $templateClass = (string) $template->template_class;
            $warnings['blocked_relations']++;
        }

        if (array_key_exists('layout_id', $data) && ! $this->layoutIsValid($data['layout_id'], $locale)) {
            unset($data['layout_id']);
            $warnings['blocked_relations']++;
        }
    }

    private function layoutIsValid(mixed $layoutId, string $locale): bool
    {
        return CmsLayout::query()
            ->whereKey((int) $layoutId)
            ->where('locale', $locale)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @param  array<int, array<string, mixed>>  $sectionSnapshots
     */
    private function restoreExistingBlockContent(CmsTemplate $template, array $sectionSnapshots): int
    {
        $currentBlocks = CmsBlock::query()
            ->whereHas('placements.section', fn ($query) => $query
                ->where('owner_type', CmsTemplate::class)
                ->where('owner_id', $template->id)
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
