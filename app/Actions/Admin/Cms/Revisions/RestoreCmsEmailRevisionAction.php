<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Actions\Admin\Cms\Mail\ValidateCmsEmailContentAction;
use App\Models\Cms\CmsEmail;
use App\Models\Cms\CmsMailTemplate;
use App\Models\Cms\CmsRevision;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RestoreCmsEmailRevisionAction
{
    public function __construct(
        private readonly BuildCmsEmailRevisionSnapshotAction $buildSnapshot,
        private readonly CreateCmsRevisionAction $createRevision,
        private readonly ValidateCmsEmailContentAction $validateEmail,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CmsEmail $email, CmsRevision $revision, string $mode, ?int $authorId = null): array
    {
        return DB::transaction(function () use ($email, $revision, $mode, $authorId): array {
            $lockedEmail = CmsEmail::query()->lockForUpdate()->findOrFail($email->id);
            $this->assertRevisionMatches($lockedEmail, $revision);

            $this->createRevision->handle(
                $lockedEmail,
                'restore_backup',
                $this->buildSnapshot->handle($lockedEmail),
                $authorId,
                __('cms_admin_ui.revisions.restore_backup_title'),
                forceCreate: true,
            );

            $snapshotRoot = $revision->snapshot ?? [];
            $snapshot = is_array($snapshotRoot['email'] ?? null) ? $snapshotRoot['email'] : [];
            $fields = $mode === 'full'
                ? ['cms_mail_template_id', 'title', 'locale', 'translation_key', 'email_type', 'system_key', 'context_key', 'subject', 'preheader', 'content_blocks', 'plain_text', 'settings', 'is_active']
                : ['subject', 'preheader', 'content_blocks', 'plain_text', 'settings'];
            $data = Arr::only($snapshot, $fields);

            if ($mode === 'full') {
                $this->filterUnsafeFullRestoreFields($lockedEmail, $data);
            }

            $template = CmsMailTemplate::query()->with('sections.placements.block')->find((int) ($data['cms_mail_template_id'] ?? $lockedEmail->cms_mail_template_id));

            if ($template instanceof CmsMailTemplate) {
                $validationData = array_merge($lockedEmail->toArray(), $data);
                $this->validateEmail->handle($template, $validationData);
            }

            $lockedEmail->forceFill($data)->save();

            $this->createRevision->handle(
                $lockedEmail->fresh() ?: $lockedEmail,
                'restore',
                $this->buildSnapshot->handle($lockedEmail->fresh() ?: $lockedEmail),
                $authorId,
                __('cms_admin_ui.revisions.restored_revision_title', ['number' => $revision->revision_number]),
                (int) $revision->id,
                ['restore_mode' => $mode],
            );

            return ['blocked_relations' => 0];
        });
    }

    private function assertRevisionMatches(CmsEmail $email, CmsRevision $revision): void
    {
        abort_unless($revision->subject_type === CmsEmail::class && (int) $revision->subject_id === (int) $email->id, 404);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function filterUnsafeFullRestoreFields(CmsEmail $email, array &$data): void
    {
        if ($email->email_type === 'system') {
            unset($data['email_type'], $data['system_key'], $data['locale'], $data['translation_key']);
        }

        if (isset($data['cms_mail_template_id']) && ! CmsMailTemplate::query()->whereKey((int) $data['cms_mail_template_id'])->where('is_active', true)->exists()) {
            unset($data['cms_mail_template_id']);
        }
    }
}
