<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsMailTemplate;
use App\Models\Cms\CmsRevision;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RestoreCmsMailTemplateRevisionAction
{
    public function __construct(
        private readonly BuildCmsMailTemplateRevisionSnapshotAction $buildSnapshot,
        private readonly CreateCmsRevisionAction $createRevision,
        private readonly RestoreCmsSectionSnapshotsAction $restoreSections,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CmsMailTemplate $template, CmsRevision $revision, string $mode, ?int $authorId = null): array
    {
        return DB::transaction(function () use ($template, $revision, $mode, $authorId): array {
            $lockedTemplate = CmsMailTemplate::query()->lockForUpdate()->findOrFail($template->id);
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
            $templateSnapshot = is_array($snapshot['mail_template'] ?? null) ? $snapshot['mail_template'] : [];
            $warnings = ['deactivated_sections' => 0, 'deactivated_placements' => 0];

            $this->restoreTemplateFields($lockedTemplate, $templateSnapshot, $mode);

            if ($mode === 'full') {
                $warnings = array_merge($warnings, $this->restoreSections->handle(
                    $lockedTemplate,
                    $snapshot['sections'] ?? [],
                    ['content'],
                    $authorId,
                ));
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

    private function assertRevisionMatches(CmsMailTemplate $template, CmsRevision $revision): void
    {
        abort_unless($revision->subject_type === CmsMailTemplate::class && (int) $revision->subject_id === (int) $template->id, 404);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function restoreTemplateFields(CmsMailTemplate $template, array $snapshot, string $mode): void
    {
        $fields = ['name', 'description', 'body_blocks', 'settings'];

        if ($mode === 'full') {
            $fields = array_merge($fields, ['key', 'context_key', 'is_active']);
        }

        $template->forceFill(Arr::only($snapshot, $fields))->save();
    }
}
