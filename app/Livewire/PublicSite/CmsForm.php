<?php

namespace App\Livewire\PublicSite;

use App\Actions\Admin\Cms\SendCmsFormSubmissionEmailsAction;
use App\Models\Cms\CmsForm as CmsFormModel;
use App\Models\Cms\CmsFormField;
use App\Models\Cms\CmsFormSubmission;
use App\Support\PublicSite\CmsFormOptionNormalizer;
use App\Support\PublicSite\CmsFormRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class CmsForm extends Component
{
    public string $formTranslationKey = '';

    public string $locale = '';

    public ?int $pageId = null;

    /**
     * @var array<string, mixed>
     */
    public array $form = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $fields = [];

    /**
     * @var array<string, mixed>
     */
    public array $values = [];

    public string $company = '';

    public bool $submitted = false;

    public function mount(string $formTranslationKey, ?int $pageId = null, string $locale = ''): void
    {
        $this->formTranslationKey = $formTranslationKey;
        $this->pageId = $pageId;
        $this->locale = $locale;

        $form = $this->activeForm();

        abort_unless($form instanceof CmsFormModel, 404);

        $this->hydrateFromForm($form);
    }

    public function updated(string $propertyName): void
    {
        if (! Str::startsWith($propertyName, 'values.')) {
            return;
        }

        $this->validateOnly($propertyName);
    }

    public function save(): void
    {
        $form = $this->activeForm();

        abort_unless($form instanceof CmsFormModel, 404);

        $this->hydrateFromForm($form, resetValues: false);

        if ($form->form_kind === 'system') {
            $this->addError('form.system', public_text('form.system_form_unavailable', 'This account form is handled by the secure account module.', $this->locale));

            return;
        }

        if ($this->company !== '') {
            $this->submitted = true;
            $this->reset('values', 'company');
            $this->hydrateFromForm($form);

            return;
        }

        $validated = $this->validate();
        $submittedValues = (array) ($validated['values'] ?? []);

        $submission = DB::transaction(function () use ($form, $submittedValues): CmsFormSubmission {
            $submission = CmsFormSubmission::query()->create([
                'cms_form_id' => $form->id,
                'cms_page_id' => $this->pageId,
                'locale' => $form->locale,
                'form_translation_key' => $form->translation_key,
                'status' => 'new',
                'ip_address' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 1000, ''),
                'submitted_at' => now(),
                'metadata' => [
                    'source_url' => request()->headers->get('referer'),
                ],
            ]);

            foreach ($form->fields as $field) {
                $value = $submittedValues[$field->translation_key] ?? null;

                $submission->values()->create([
                    'cms_form_field_id' => $field->id,
                    'field_translation_key' => $field->translation_key,
                    'field_label_snapshot' => $field->label,
                    'value' => is_bool($value) ? ($value ? '1' : '0') : $value,
                ]);
            }

            return $submission;
        });

        app(SendCmsFormSubmissionEmailsAction::class)->handle($submission);

        $this->submitted = true;
        $this->reset('values', 'company');
        $this->hydrateFromForm($form);
        $this->resetValidation();
    }

    public function render(): mixed
    {
        return view('livewire.public-site.cms-form');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $form = $this->activeForm();

        if (! $form instanceof CmsFormModel) {
            return [];
        }

        return $form->fields
            ->mapWithKeys(fn (CmsFormField $field): array => [
                'values.'.$field->translation_key => app(CmsFormRules::class)->forField($field),
            ])
            ->all();
    }

    private function activeForm(): ?CmsFormModel
    {
        return CmsFormModel::query()
            ->with(['fields' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->where('translation_key', $this->formTranslationKey)
            ->where('locale', $this->locale)
            ->where('is_active', true)
            ->first();
    }

    private function hydrateFromForm(CmsFormModel $form, bool $resetValues = true): void
    {
        $this->form = [
            'title' => $form->title,
            'description' => $form->description,
            'submit_button_label' => $form->submit_button_label ?: public_text('form.submit_fallback', 'Submit', $this->locale),
            'success_message' => $form->success_message ?: public_text('form.success_fallback', 'Thank you. Your form has been submitted.', $this->locale),
            'is_system' => $form->form_kind === 'system',
        ];

        $this->fields = $form->fields
            ->map(fn (CmsFormField $field): array => [
                'translation_key' => $field->translation_key,
                'type' => $field->type,
                'label' => $field->label,
                'placeholder' => $field->placeholder,
                'help_text' => $field->help_text,
                'options' => CmsFormOptionNormalizer::normalize($field->options ?? []),
                'is_required' => (bool) $field->is_required,
                'width' => $field->width ?: 'full',
            ])
            ->values()
            ->all();

        if (! $resetValues) {
            return;
        }

        $this->values = $form->fields
            ->mapWithKeys(fn (CmsFormField $field): array => [$field->translation_key => $field->type === 'checkbox' ? false : ''])
            ->all();
    }
}
