<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\CreateCmsFormTranslationAction;
use App\Actions\Admin\Cms\Health\ValidateCmsPublishReadinessAction;
use App\Actions\Admin\Cms\Revisions\BuildCmsFormRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\CmsRevisionPayloadAction;
use App\Actions\Admin\Cms\Revisions\CreateCmsRevisionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\StoreCmsFormRequest;
use App\Http\Requests\Admin\Cms\StoreCmsFormTranslationRequest;
use App\Models\Cms\CmsEmail;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormField;
use App\Support\Audit\AuditLogger;
use App\Support\PublicSite\CmsFormOptionNormalizer;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\PublicAccountSystemFormRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CmsFormController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CmsLanguageSettings $languageSettings,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Cms/Forms/Index', [
            'forms' => CmsForm::query()
                ->withCount(['fields', 'submissions'])
                ->orderByDesc('updated_at')
                ->get(['id', 'title', 'locale', 'translation_key', 'form_kind', 'system_key', 'is_active', 'updated_at']),
        ]);
    }

    public function create(): Response
    {
        return $this->edit(0);
    }

    public function edit(int $id): Response
    {
        $form = $id > 0
            ? CmsForm::query()->with('fields')->findOrFail($id)
            : null;

        return Inertia::render('Admin/Cms/Forms/Edit', [
            'formItem' => $form ? $this->formPayload($form) : null,
            'translations' => $form ? $this->translationPayload($form) : [],
            'revisions' => $form ? app(CmsRevisionPayloadAction::class)->handle($form) : [],
            'missingLanguages' => $form ? $this->missingLanguagePayload($form) : [],
            'activeLanguages' => $this->languageSettings->languages(true),
            'multilingualEnabled' => $this->languageSettings->multilingualEnabled(),
            'availableLocales' => $this->languageSettings->activeLocales(),
            'fieldTypeOptions' => $this->fieldTypeOptions(),
            'widthOptions' => $this->widthOptions(),
            'submissionEmailOptions' => $this->submissionEmailOptions(),
        ]);
    }

    public function store(
        StoreCmsFormRequest $request,
        int $id,
        BuildCmsFormRevisionSnapshotAction $buildRevisionSnapshot,
        CreateCmsRevisionAction $createRevision,
        ValidateCmsPublishReadinessAction $publishReadiness,
    ): RedirectResponse {
        $validated = $request->validated();

        $readiness = (bool) ($validated['is_active'] ?? false)
            ? $publishReadiness->form($validated)
            : ['errors' => [], 'warnings' => []];

        if ($readiness['errors'] !== []) {
            return back()->withErrors(['fields' => implode(' ', $readiness['errors'])])->withInput();
        }

        $form = $id > 0
            ? CmsForm::query()->findOrFail($id)
            : new CmsForm;
        $isCreate = ! $form->exists;

        DB::transaction(function () use ($form, $validated, $request, $buildRevisionSnapshot, $createRevision): void {
            $form->fill($this->formData($validated));

            if (blank($form->translation_key)) {
                $form->translation_key = (string) Str::ulid();
            }

            $form->save();

            foreach ($this->fieldRows($validated['fields'] ?? []) as $fieldData) {
                $field = null;

                if ((int) ($fieldData['id'] ?? 0) > 0) {
                    $field = CmsFormField::query()
                        ->where('cms_form_id', $form->id)
                        ->find($fieldData['id']);
                }

                if (! $field instanceof CmsFormField) {
                    $field = new CmsFormField(['cms_form_id' => $form->id]);
                }

                $field->fill(Arr::except($this->preserveLockedSystemFieldData($field, $fieldData), ['id']));
                $field->cms_form_id = $form->id;
                $field->save();
            }

            $createRevision->handle(
                $form,
                'full',
                $buildRevisionSnapshot->handle($form),
                $request->user()?->id,
                __('cms_admin_ui.revisions.auto_revision_title'),
                metadata: [
                    'change_type' => $form->wasRecentlyCreated ? 'create' : 'update',
                    'fields_count' => $form->fields()->count(),
                ],
            );
        });

        $this->auditLogger->success(
            action: $isCreate ? 'cms.form.create' : 'cms.form.update',
            module: 'cms',
            subjectType: 'cms_form',
            subjectKey: (string) $form->id,
            message: __('cms_admin_ui.flash.saved.form'),
            meta: [
                'title' => (string) $form->title,
                'locale' => (string) $form->locale,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.forms.edit', ['id' => $form->id])
            ->with('status', __('cms_admin_ui.flash.saved.form'));
    }

    public function storeTranslation(
        StoreCmsFormTranslationRequest $request,
        int $id,
        CreateCmsFormTranslationAction $createTranslation,
    ): RedirectResponse {
        $validated = $request->validated();
        $form = CmsForm::query()->with('fields')->findOrFail($id);
        $useAi = (bool) ($validated['use_ai'] ?? false);

        try {
            $translation = $createTranslation->handle(
                sourceForm: $form,
                targetLocale: (string) $validated['target_locale'],
                useAi: $useAi,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['target_locale' => $useAi
                ? __('cms_admin_ui.flash.translation_failed_ai')
                : __('cms_admin_ui.flash.translation_failed')]);
        }

        $this->auditLogger->success(
            action: 'cms.form.translation.create',
            module: 'cms',
            subjectType: 'cms_form',
            subjectKey: (string) $translation->id,
            message: __('cms_admin_ui.flash.inactive_form_translation_created'),
            meta: [
                'source_form_id' => $form->id,
                'target_form_id' => $translation->id,
                'target_locale' => (string) $translation->locale,
                'use_ai' => $useAi,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.forms.edit', ['id' => $translation->id])
            ->with('status', $useAi
                ? __('cms_admin_ui.flash.inactive_form_translation_created_ai')
                : __('cms_admin_ui.flash.inactive_form_translation_created'));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function fieldTypeOptions(): array
    {
        return [
            ['value' => 'text', 'label' => __('cms_admin_ui.form_field_types.text')],
            ['value' => 'email', 'label' => __('cms_admin_ui.form_field_types.email')],
            ['value' => 'number', 'label' => __('cms_admin_ui.form_field_types.number')],
            ['value' => 'date', 'label' => __('cms_admin_ui.form_field_types.date')],
            ['value' => 'time', 'label' => __('cms_admin_ui.form_field_types.time')],
            ['value' => 'textarea', 'label' => __('cms_admin_ui.form_field_types.textarea')],
            ['value' => 'select', 'label' => __('cms_admin_ui.form_field_types.select')],
            ['value' => 'combobox', 'label' => __('cms_admin_ui.form_field_types.combobox')],
            ['value' => 'checkbox', 'label' => __('cms_admin_ui.form_field_types.checkbox')],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function widthOptions(): array
    {
        return [
            ['value' => 'full', 'label' => __('cms_admin_ui.form_field_widths.full')],
            ['value' => 'half', 'label' => __('cms_admin_ui.form_field_widths.half')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(CmsForm $form): array
    {
        return [
            'id' => $form->id,
            'title' => $form->title,
            'locale' => $form->locale,
            'translation_key' => $form->translation_key,
            'translated_from_form_id' => $form->translated_from_form_id,
            'form_kind' => $form->form_kind ?? 'normal',
            'system_key' => $form->system_key,
            'description' => $form->description,
            'notification_email' => $form->notification_email,
            'submission_email_enabled' => (bool) $form->submission_email_enabled,
            'submission_cms_email_id' => $form->submission_cms_email_id,
            'submission_to_cms_form_field_id' => $form->submission_to_cms_form_field_id,
            'submission_cc_recipients' => $this->recipientRowsPayload($form->submission_cc_recipients ?? []),
            'submission_bcc_recipients' => $this->recipientRowsPayload($form->submission_bcc_recipients ?? []),
            'submit_button_label' => $form->submit_button_label,
            'success_message' => $form->success_message,
            'is_active' => (bool) $form->is_active,
            'created_at' => $form->created_at?->toIso8601String(),
            'updated_at' => $form->updated_at?->toIso8601String(),
            'ai_translation_review' => $this->aiTranslationReviewPayload($form->settings ?? []),
            'fields' => $form->fields->map(fn (CmsFormField $field): array => [
                'id' => $field->id,
                'type' => $field->type,
                'translation_key' => $field->translation_key,
                'translated_from_form_field_id' => $field->translated_from_form_field_id,
                'label' => $field->label,
                'placeholder' => $field->placeholder,
                'help_text' => $field->help_text,
                'options' => CmsFormOptionNormalizer::normalize($field->options ?? []),
                'sort_order' => $field->sort_order,
                'is_required' => (bool) $field->is_required,
                'is_active' => (bool) $field->is_active,
                'width' => $field->width ?: 'full',
                'settings' => $field->settings ?? [],
                'is_system_locked' => app(PublicAccountSystemFormRegistry::class)->isLockedField((array) ($field->settings ?? [])),
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{is_pending: bool}
     */
    private function aiTranslationReviewPayload(array $settings): array
    {
        return [
            'is_pending' => ($settings['translation_source'] ?? null) === 'ai'
                && ($settings['translation_review_status'] ?? null) === 'pending',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function translationPayload(CmsForm $form): array
    {
        if (blank($form->translation_key)) {
            return [];
        }

        return CmsForm::query()
            ->where('translation_key', $form->translation_key)
            ->orderBy('locale')
            ->orderBy('title')
            ->get(['id', 'title', 'locale', 'is_active', 'translated_from_form_id', 'updated_at'])
            ->map(fn (CmsForm $translation): array => [
                'id' => $translation->id,
                'title' => $translation->title,
                'locale' => $translation->locale,
                'is_active' => (bool) $translation->is_active,
                'translated_from_form_id' => $translation->translated_from_form_id,
                'updated_at' => $translation->updated_at?->toIso8601String(),
                'is_current' => $translation->is($form),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{locale: string, name: string, native_name: string}>
     */
    private function missingLanguagePayload(CmsForm $form): array
    {
        $existingLocales = blank($form->translation_key)
            ? collect([(string) $form->locale])
            : CmsForm::query()
                ->where('translation_key', $form->translation_key)
                ->pluck('locale');

        return collect($this->languageSettings->languages(true))
            ->reject(fn (array $language): bool => $existingLocales->contains($language['locale']))
            ->map(fn (array $language): array => [
                'locale' => (string) $language['locale'],
                'name' => (string) $language['name'],
                'native_name' => (string) $language['native_name'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function formData(array $validated): array
    {
        return array_merge(
            Arr::only($validated, [
                'title',
                'locale',
                'description',
                'notification_email',
                'submission_cms_email_id',
                'submission_to_cms_form_field_id',
                'submit_button_label',
                'success_message',
            ]),
            [
                'submission_email_enabled' => (bool) ($validated['submission_email_enabled'] ?? false),
                'submission_cc_recipients' => $this->recipientRowsData($validated['submission_cc_recipients'] ?? []),
                'submission_bcc_recipients' => $this->recipientRowsData($validated['submission_bcc_recipients'] ?? []),
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]
        );
    }

    /**
     * @return array<int, array{id: int, title: string, locale: string, label: string}>
     */
    private function submissionEmailOptions(): array
    {
        return CmsEmail::query()
            ->active()
            ->where('context_key', 'cms.form_submission.email')
            ->orderBy('locale')
            ->orderBy('title')
            ->get(['id', 'title', 'locale', 'subject'])
            ->map(fn (CmsEmail $email): array => [
                'id' => (int) $email->id,
                'title' => (string) $email->title,
                'locale' => (string) $email->locale,
                'label' => trim((string) $email->title).' ('.(string) $email->locale.')',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array{type: string, email: string, field_id: int|null}>
     */
    private function recipientRowsPayload(array $rows): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'type' => in_array($row['type'] ?? null, ['static', 'field'], true) ? (string) $row['type'] : 'static',
                'email' => (string) ($row['email'] ?? ''),
                'field_id' => isset($row['field_id']) ? (int) $row['field_id'] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function recipientRowsData(array $rows): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row): ?array {
                $type = (string) ($row['type'] ?? '');

                if ($type === 'static') {
                    $email = trim((string) ($row['email'] ?? ''));

                    return $email !== '' ? ['type' => 'static', 'email' => $email] : null;
                }

                if ($type === 'field') {
                    $fieldId = (int) ($row['field_id'] ?? 0);

                    return $fieldId > 0 ? ['type' => 'field', 'field_id' => $fieldId] : null;
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function fieldRows(array $fields): array
    {
        return collect($fields)
            ->filter(fn ($field): bool => is_array($field))
            ->map(fn (array $field, int $index): array => [
                'id' => $field['id'] ?? null,
                'type' => $field['type'],
                'translation_key' => $field['translation_key'] ?? (string) Str::ulid(),
                'translated_from_form_field_id' => $field['translated_from_form_field_id'] ?? null,
                'label' => $field['label'],
                'placeholder' => $field['placeholder'] ?? null,
                'help_text' => $field['help_text'] ?? null,
                'options' => CmsFormOptionNormalizer::normalize($field['options'] ?? []),
                'validation_rules' => $field['is_required'] ?? false ? ['required'] : ['nullable'],
                'sort_order' => (int) ($field['sort_order'] ?? (($index + 1) * 10)),
                'is_required' => (bool) ($field['is_required'] ?? false),
                'is_active' => (bool) ($field['is_active'] ?? false),
                'width' => $field['width'] ?? 'full',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $fieldData
     * @return array<string, mixed>
     */
    private function preserveLockedSystemFieldData(CmsFormField $field, array $fieldData): array
    {
        if (! $field->exists || ! app(PublicAccountSystemFormRegistry::class)->isLockedField((array) ($field->settings ?? []))) {
            return $fieldData;
        }

        return array_merge($fieldData, [
            'type' => $field->type,
            'translation_key' => $field->translation_key,
            'translated_from_form_field_id' => $field->translated_from_form_field_id,
            'options' => $field->options ?? [],
            'validation_rules' => $field->validation_rules ?? [],
            'is_required' => (bool) $field->is_required,
            'is_active' => true,
            'settings' => $field->settings ?? [],
        ]);
    }
}
