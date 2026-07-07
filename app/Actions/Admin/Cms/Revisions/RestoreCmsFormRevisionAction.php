<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormField;
use App\Models\Cms\CmsFormSubmissionValue;
use App\Models\Cms\CmsRevision;
use App\Support\PublicSite\CmsFormOptionNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestoreCmsFormRevisionAction
{
    public function __construct(
        private readonly BuildCmsFormRevisionSnapshotAction $buildSnapshot,
        private readonly CreateCmsRevisionAction $createRevision,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CmsForm $form, CmsRevision $revision, string $mode, ?int $authorId = null): array
    {
        return DB::transaction(function () use ($form, $revision, $mode, $authorId): array {
            $lockedForm = CmsForm::query()->lockForUpdate()->findOrFail($form->id);
            $this->assertRevisionMatches($lockedForm, $revision);

            $snapshot = $revision->snapshot['form'] ?? [];

            if ($mode === 'full') {
                $this->assertAnswersRemainValid($lockedForm, $snapshot['fields'] ?? []);
            }

            $this->createRevision->handle(
                $lockedForm,
                'restore_backup',
                $this->buildSnapshot->handle($lockedForm),
                $authorId,
                __('cms_admin_ui.revisions.restore_backup_title'),
                forceCreate: true,
            );

            $this->restoreFormFields($lockedForm, $snapshot, $mode);

            $warnings = $mode === 'full'
                ? $this->restoreFieldRows($lockedForm, $snapshot['fields'] ?? [])
                : $this->restoreFieldContent($lockedForm, $snapshot['fields'] ?? []);

            $this->createRevision->handle(
                $lockedForm->fresh() ?: $lockedForm,
                'restore',
                $this->buildSnapshot->handle($lockedForm->fresh() ?: $lockedForm),
                $authorId,
                __('cms_admin_ui.revisions.restored_revision_title', ['number' => $revision->revision_number]),
                (int) $revision->id,
                ['restore_mode' => $mode, 'warnings' => $warnings],
            );

            return $warnings;
        });
    }

    private function assertRevisionMatches(CmsForm $form, CmsRevision $revision): void
    {
        abort_unless($revision->subject_type === CmsForm::class && (int) $revision->subject_id === (int) $form->id, 404);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function restoreFormFields(CmsForm $form, array $snapshot, string $mode): void
    {
        $fields = ['title', 'description', 'notification_email', 'submit_button_label', 'success_message'];

        if ($mode === 'full') {
            $fields = array_merge($fields, ['is_active', 'settings']);
        }

        $form->forceFill(Arr::only($snapshot, $fields))->save();
    }

    /**
     * @param  array<int, array<string, mixed>>  $fieldSnapshots
     * @return array<string, mixed>
     */
    private function restoreFieldContent(CmsForm $form, array $fieldSnapshots): array
    {
        $updatedFields = 0;

        foreach ($fieldSnapshots as $fieldSnapshot) {
            $field = $this->fieldForSnapshot($form, $fieldSnapshot);

            if (! $field instanceof CmsFormField) {
                continue;
            }

            $field->forceFill(Arr::only($fieldSnapshot, [
                'label',
                'placeholder',
                'help_text',
            ]))->save();
            $this->restoreOptionLabels($field, $fieldSnapshot['options'] ?? []);
            $updatedFields++;
        }

        return ['updated_fields' => $updatedFields];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fieldSnapshots
     * @return array<string, mixed>
     */
    private function restoreFieldRows(CmsForm $form, array $fieldSnapshots): array
    {
        $keptIds = [];

        foreach ($fieldSnapshots as $fieldSnapshot) {
            $field = $this->fieldForSnapshot($form, $fieldSnapshot) ?? new CmsFormField(['cms_form_id' => $form->id]);
            $field->forceFill(array_merge(
                Arr::only($fieldSnapshot, [
                    'type',
                    'translation_key',
                    'translated_from_form_field_id',
                    'label',
                    'placeholder',
                    'help_text',
                    'options',
                    'validation_rules',
                    'sort_order',
                    'is_required',
                    'is_active',
                    'width',
                    'settings',
                ]),
                ['cms_form_id' => $form->id]
            ))->save();

            $keptIds[] = (int) $field->id;
        }

        $deactivated = $form->fields()
            ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return ['deactivated_fields' => (int) $deactivated];
    }

    /**
     * @param  array<string, mixed>  $fieldSnapshot
     */
    private function fieldForSnapshot(CmsForm $form, array $fieldSnapshot): ?CmsFormField
    {
        $fieldId = (int) ($fieldSnapshot['id'] ?? 0);

        if ($fieldId > 0) {
            $field = $form->fields()->whereKey($fieldId)->first();

            if ($field instanceof CmsFormField) {
                return $field;
            }
        }

        $translationKey = (string) ($fieldSnapshot['translation_key'] ?? '');

        if ($translationKey === '') {
            return null;
        }

        return $form->fields()->where('translation_key', $translationKey)->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     */
    private function restoreOptionLabels(CmsFormField $field, array $options): void
    {
        $snapshotOptions = collect(CmsFormOptionNormalizer::normalize($options))->keyBy('key');
        $currentOptions = collect(CmsFormOptionNormalizer::normalize($field->options ?? []))
            ->map(function (array $option) use ($snapshotOptions): array {
                $snapshotOption = $snapshotOptions->get($option['key']);

                return $snapshotOption ? array_merge($option, ['label' => $snapshotOption['label']]) : $option;
            })
            ->values()
            ->all();

        $field->forceFill(['options' => $currentOptions])->save();
    }

    /**
     * @param  array<int, array<string, mixed>>  $fieldSnapshots
     *
     * @throws ValidationException
     */
    private function assertAnswersRemainValid(CmsForm $form, array $fieldSnapshots): void
    {
        $snapshots = collect($fieldSnapshots)
            ->keyBy(fn (array $field): int => (int) ($field['id'] ?? 0));

        foreach ($form->fields()->withCount('values')->get() as $field) {
            if ((int) $field->values_count === 0) {
                continue;
            }

            $snapshot = $snapshots->get((int) $field->id);

            if (! is_array($snapshot) || ! (bool) ($snapshot['is_active'] ?? true)) {
                throw ValidationException::withMessages(['revision' => __('cms_admin_ui.revisions.form_restore_answered_field_removed')]);
            }

            if ((string) ($snapshot['type'] ?? '') !== (string) $field->type) {
                throw ValidationException::withMessages(['revision' => __('cms_admin_ui.revisions.form_restore_answered_field_changed')]);
            }

            if ($field->type === 'select') {
                $this->assertSelectAnswersRemainValid($field, $snapshot['options'] ?? []);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     *
     * @throws ValidationException
     */
    private function assertSelectAnswersRemainValid(CmsFormField $field, array $options): void
    {
        $validOptionKeys = collect(CmsFormOptionNormalizer::normalize($options))->pluck('key')->map(fn (mixed $key): string => (string) $key);
        $submittedValues = CmsFormSubmissionValue::query()
            ->where('cms_form_field_id', $field->id)
            ->pluck('value')
            ->map(fn (mixed $value): string => (string) $value)
            ->filter(fn (string $value): bool => $value !== '')
            ->unique();

        if ($submittedValues->diff($validOptionKeys)->isNotEmpty()) {
            throw ValidationException::withMessages(['revision' => __('cms_admin_ui.revisions.form_restore_answered_option_removed')]);
        }
    }
}
