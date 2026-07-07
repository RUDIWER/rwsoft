<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsEmail;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormField;
use App\Support\PublicSite\CmsLocalePermission;
use App\Support\PublicSite\PublicAccountSystemFormRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return app(CmsLocalePermission::class)->canEditLocale($this->user(), (string) $this->input('locale'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2}([_-][A-Z]{2})?$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notification_email' => ['nullable', 'email', 'max:255'],
            'submission_email_enabled' => ['nullable', 'boolean'],
            'submission_cms_email_id' => ['nullable', 'integer', Rule::exists((new CmsEmail)->getTable(), 'id')->where('is_active', true)->where('context_key', 'cms.form_submission.email')],
            'submission_to_cms_form_field_id' => ['nullable', 'integer', 'exists:cms_form_fields,id'],
            'submission_cc_recipients' => ['nullable', 'array', 'max:10'],
            'submission_cc_recipients.*.type' => ['required_with:submission_cc_recipients', 'string', Rule::in(['static', 'field'])],
            'submission_cc_recipients.*.email' => ['nullable', 'email', 'max:255'],
            'submission_cc_recipients.*.field_id' => ['nullable', 'integer', 'exists:cms_form_fields,id'],
            'submission_bcc_recipients' => ['nullable', 'array', 'max:10'],
            'submission_bcc_recipients.*.type' => ['required_with:submission_bcc_recipients', 'string', Rule::in(['static', 'field'])],
            'submission_bcc_recipients.*.email' => ['nullable', 'email', 'max:255'],
            'submission_bcc_recipients.*.field_id' => ['nullable', 'integer', 'exists:cms_form_fields,id'],
            'submit_button_label' => ['nullable', 'string', 'max:120'],
            'success_message' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'fields' => ['nullable', 'array'],
            'fields.*.id' => ['nullable', 'integer', 'exists:cms_form_fields,id'],
            'fields.*.type' => ['required', 'string', Rule::in(['text', 'email', 'number', 'date', 'time', 'textarea', 'select', 'combobox', 'checkbox'])],
            'fields.*.translation_key' => ['nullable', 'string', 'max:32', 'alpha_dash:ascii'],
            'fields.*.translated_from_form_field_id' => ['nullable', 'integer', 'exists:cms_form_fields,id'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.help_text' => ['nullable', 'string', 'max:1000'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*.key' => ['required_with:fields.*.options', 'string', 'max:80', 'alpha_dash:ascii'],
            'fields.*.options.*.label' => ['required_with:fields.*.options', 'string', 'max:255'],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'fields.*.is_required' => ['nullable', 'boolean'],
            'fields.*.is_active' => ['nullable', 'boolean'],
            'fields.*.width' => ['nullable', 'string', Rule::in(['full', 'half'])],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateSubmissionEmailSettings($validator);

                $systemForm = $this->currentSystemForm();
                $lockedFields = $systemForm instanceof CmsForm
                    ? $systemForm->fields->filter(fn (CmsFormField $field): bool => app(PublicAccountSystemFormRegistry::class)->isLockedField((array) ($field->settings ?? [])))
                    : collect();
                $lockedFieldIds = $lockedFields->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
                $postedFieldIds = collect((array) $this->input('fields', []))
                    ->filter(fn (mixed $field): bool => is_array($field))
                    ->pluck('id')
                    ->filter()
                    ->map(fn (mixed $id): int => (int) $id)
                    ->all();

                foreach ($lockedFields as $lockedField) {
                    if (! in_array((int) $lockedField->id, $postedFieldIds, true)) {
                        $validator->errors()->add('fields', __('cms_admin_ui.validation.system_form_locked_field_missing'));
                    }
                }

                foreach ((array) $this->input('fields', []) as $index => $field) {
                    if (! is_array($field)) {
                        continue;
                    }

                    if ($systemForm instanceof CmsForm && (int) ($field['id'] ?? 0) <= 0) {
                        $validator->errors()->add("fields.{$index}.label", __('cms_admin_ui.validation.system_form_new_fields_blocked'));
                    }

                    $lockedField = $systemForm instanceof CmsForm && (int) ($field['id'] ?? 0) > 0
                        ? $lockedFields->firstWhere('id', (int) $field['id'])
                        : null;

                    if ($lockedField instanceof CmsFormField) {
                        if (($field['translation_key'] ?? null) !== $lockedField->translation_key) {
                            $validator->errors()->add("fields.{$index}.label", __('cms_admin_ui.validation.system_form_locked_field_key'));
                        }

                        if (($field['type'] ?? null) !== $lockedField->type) {
                            $validator->errors()->add("fields.{$index}.type", __('cms_admin_ui.validation.system_form_locked_field_type'));
                        }

                        if (! (bool) ($field['is_active'] ?? false)) {
                            $validator->errors()->add("fields.{$index}.label", __('cms_admin_ui.validation.system_form_locked_field_active'));
                        }

                        if ($lockedField->is_required && ! (bool) ($field['is_required'] ?? false)) {
                            $validator->errors()->add("fields.{$index}.label", __('cms_admin_ui.validation.system_form_locked_field_required'));
                        }
                    } elseif ($systemForm instanceof CmsForm && (int) ($field['id'] ?? 0) > 0 && ! in_array((int) $field['id'], $lockedFieldIds, true)) {
                        $validator->errors()->add("fields.{$index}.label", __('cms_admin_ui.validation.system_form_extra_fields_blocked'));
                    }

                    if (($field['type'] ?? null) === 'select' && count((array) ($field['options'] ?? [])) === 0) {
                        $validator->errors()->add("fields.{$index}.options", __('cms_admin_ui.validation.form_select_options_required'));
                    }

                    $optionKeys = collect((array) ($field['options'] ?? []))
                        ->filter(fn (mixed $option): bool => is_array($option))
                        ->pluck('key')
                        ->filter()
                        ->map(fn ($key): string => (string) $key);

                    if ($optionKeys->duplicates()->isNotEmpty()) {
                        $validator->errors()->add("fields.{$index}.options", __('cms_admin_ui.validation.form_option_keys_unique'));
                    }
                }
            },
        ];
    }

    private function validateSubmissionEmailSettings(Validator $validator): void
    {
        if (! (bool) $this->boolean('submission_email_enabled')) {
            return;
        }

        $formId = (int) $this->route('id');

        if ($formId <= 0) {
            $validator->errors()->add('submission_email_enabled', __('cms_admin_ui.validation.form_submission_email_requires_saved_form'));

            return;
        }

        $email = CmsEmail::query()
            ->active()
            ->where('context_key', 'cms.form_submission.email')
            ->find((int) $this->input('submission_cms_email_id'));

        if (! $email instanceof CmsEmail) {
            $validator->errors()->add('submission_cms_email_id', __('cms_admin_ui.validation.invalid_choice'));
        } elseif ((string) $email->locale !== (string) $this->input('locale')) {
            $validator->errors()->add('submission_cms_email_id', __('cms_admin_ui.validation.form_submission_email_locale_mismatch'));
        }

        $this->validateEmailField(
            $validator,
            'submission_to_cms_form_field_id',
            $this->input('submission_to_cms_form_field_id'),
            $formId,
            required: true,
        );

        $this->validateRecipientRows($validator, 'submission_cc_recipients', $formId);
        $this->validateRecipientRows($validator, 'submission_bcc_recipients', $formId);
    }

    private function validateRecipientRows(Validator $validator, string $field, int $formId): void
    {
        foreach ((array) $this->input($field, []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = (string) ($row['type'] ?? '');

            if ($type === 'static') {
                if (blank($row['email'] ?? null)) {
                    $validator->errors()->add("{$field}.{$index}.email", __('cms_admin_ui.validation.required'));
                }

                continue;
            }

            if ($type === 'field') {
                $this->validateEmailField(
                    $validator,
                    "{$field}.{$index}.field_id",
                    $row['field_id'] ?? null,
                    $formId,
                    required: true,
                );
            }
        }
    }

    private function validateEmailField(Validator $validator, string $field, mixed $value, int $formId, bool $required = false): void
    {
        $fieldId = (int) $value;

        if ($fieldId <= 0) {
            if ($required) {
                $validator->errors()->add($field, __('cms_admin_ui.validation.required'));
            }

            return;
        }

        $cmsField = CmsFormField::query()
            ->where('cms_form_id', $formId)
            ->where('type', 'email')
            ->where('is_active', true)
            ->find($fieldId);

        if (! $cmsField instanceof CmsFormField) {
            $validator->errors()->add($field, __('cms_admin_ui.validation.form_submission_email_field_invalid'));
        }
    }

    private function currentSystemForm(): ?CmsForm
    {
        $formId = (int) $this->route('id');

        if ($formId <= 0) {
            return null;
        }

        $form = CmsForm::query()->with('fields')->find($formId);

        if (! $form instanceof CmsForm || $form->form_kind !== 'system') {
            return null;
        }

        return $form;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => __('cms_admin_ui.validation.required'),
            'title.max' => __('cms_admin_ui.validation.max_string'),
            'locale.required' => __('cms_admin_ui.validation.required'),
            'locale.regex' => __('cms_admin_ui.validation.locale_code'),
            'notification_email.email' => __('cms_admin_ui.validation.email'),
            'notification_email.max' => __('cms_admin_ui.validation.max_string'),
            'submission_cms_email_id.exists' => __('cms_admin_ui.validation.invalid_choice'),
            'submission_to_cms_form_field_id.exists' => __('cms_admin_ui.validation.invalid_choice'),
            'submission_cc_recipients.max' => __('cms_admin_ui.validation.form_submission_email_recipients_max'),
            'submission_cc_recipients.*.type.in' => __('cms_admin_ui.validation.invalid_choice'),
            'submission_cc_recipients.*.email.email' => __('cms_admin_ui.validation.email'),
            'submission_cc_recipients.*.email.max' => __('cms_admin_ui.validation.max_string'),
            'submission_cc_recipients.*.field_id.exists' => __('cms_admin_ui.validation.invalid_choice'),
            'submission_bcc_recipients.max' => __('cms_admin_ui.validation.form_submission_email_recipients_max'),
            'submission_bcc_recipients.*.type.in' => __('cms_admin_ui.validation.invalid_choice'),
            'submission_bcc_recipients.*.email.email' => __('cms_admin_ui.validation.email'),
            'submission_bcc_recipients.*.email.max' => __('cms_admin_ui.validation.max_string'),
            'submission_bcc_recipients.*.field_id.exists' => __('cms_admin_ui.validation.invalid_choice'),
            'description.max' => __('cms_admin_ui.validation.max_string'),
            'submit_button_label.max' => __('cms_admin_ui.validation.max_string'),
            'success_message.max' => __('cms_admin_ui.validation.max_string'),
            'fields.*.type.required' => __('cms_admin_ui.validation.required'),
            'fields.*.type.in' => __('cms_admin_ui.validation.form_field_type_invalid'),
            'fields.*.label.required' => __('cms_admin_ui.validation.required'),
            'fields.*.label.max' => __('cms_admin_ui.validation.max_string'),
            'fields.*.placeholder.max' => __('cms_admin_ui.validation.max_string'),
            'fields.*.help_text.max' => __('cms_admin_ui.validation.max_string'),
            'fields.*.options.*.key.required_with' => __('cms_admin_ui.validation.form_option_format'),
            'fields.*.options.*.key.alpha_dash' => __('cms_admin_ui.validation.alpha_dash_ascii'),
            'fields.*.options.*.key.max' => __('cms_admin_ui.validation.max_string'),
            'fields.*.options.*.label.required_with' => __('cms_admin_ui.validation.form_option_format'),
            'fields.*.options.*.label.max' => __('cms_admin_ui.validation.max_string'),
            'fields.*.width.in' => __('cms_admin_ui.validation.form_field_width_invalid'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('cms_admin_ui.content_form.title'),
            'locale' => __('cms_admin_ui.common.columns.locale'),
            'description' => __('cms_admin_ui.common.columns.description'),
            'notification_email' => __('cms_admin_ui.forms.form.notification_email'),
            'submission_cms_email_id' => __('cms_admin_ui.forms.form.submission_email'),
            'submission_to_cms_form_field_id' => __('cms_admin_ui.forms.form.submission_to_field'),
            'submission_cc_recipients' => __('cms_admin_ui.forms.form.submission_cc_recipients'),
            'submission_bcc_recipients' => __('cms_admin_ui.forms.form.submission_bcc_recipients'),
            'submit_button_label' => __('cms_admin_ui.forms.form.submit_button_label'),
            'success_message' => __('cms_admin_ui.forms.form.success_message'),
            'fields.*.type' => __('cms_admin_ui.common.columns.type'),
            'fields.*.label' => __('cms_admin_ui.forms.form.label'),
            'fields.*.placeholder' => __('cms_admin_ui.forms.form.placeholder'),
            'fields.*.help_text' => __('cms_admin_ui.forms.form.help_text'),
            'fields.*.options' => __('cms_admin_ui.forms.form.options'),
            'fields.*.options.*.key' => __('cms_admin_ui.common.columns.key'),
            'fields.*.options.*.label' => __('cms_admin_ui.forms.form.label'),
            'fields.*.width' => __('cms_admin_ui.forms.form.width'),
        ];
    }
}
