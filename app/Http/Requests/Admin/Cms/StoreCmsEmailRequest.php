<?php

namespace App\Http\Requests\Admin\Cms;

use App\Actions\Admin\Cms\Mail\ValidateCmsEmailContentAction;
use App\Models\Cms\CmsEmail;
use App\Models\Cms\CmsMailTemplate;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StoreCmsEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CmsLocalePermission::class)->canEditLocale($this->user(), (string) $this->input('locale'));
    }

    protected function prepareForValidation(): void
    {
        $emailId = (int) $this->route('id');

        if ($emailId <= 0) {
            return;
        }

        $email = CmsEmail::query()->find($emailId);

        if (! $email instanceof CmsEmail || $email->email_type !== 'system') {
            return;
        }

        $this->merge([
            'email_type' => 'system',
            'system_key' => $email->system_key,
            'locale' => $email->locale,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $emailId = (int) $this->route('id');

        return [
            'cms_mail_template_id' => ['required', 'integer', Rule::exists((new CmsMailTemplate)->getTable(), 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2}([_-][A-Z]{2})?$/'],
            'email_type' => ['required', 'string', Rule::in(['custom', 'system'])],
            'system_key' => [
                'required_if:email_type,system',
                'nullable',
                'string',
                'max:96',
                'regex:/^[a-z0-9_.-]+$/',
                Rule::unique('cms_emails', 'system_key')->where(fn ($query) => $query->where('locale', $this->input('locale')))->ignore($emailId),
            ],
            'subject' => ['required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'content_blocks' => ['nullable', 'array'],
            'plain_text' => ['nullable', 'string', 'max:10000'],
            'settings' => ['nullable', 'array'],
            'settings.from_name' => ['nullable', 'string', 'max:255'],
            'settings.from_email' => ['nullable', 'email', 'max:255'],
            'settings.reply_to_name' => ['nullable', 'string', 'max:255'],
            'settings.reply_to_email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $template = CmsMailTemplate::query()
                    ->with('sections.placements.block')
                    ->find((int) $this->input('cms_mail_template_id'));

                if (! $template instanceof CmsMailTemplate) {
                    return;
                }

                try {
                    app(ValidateCmsEmailContentAction::class)->handle($template, $validator->safe()->all());
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $field => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add($field, $message);
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cms_mail_template_id.required' => __('cms_admin_ui.validation.required'),
            'cms_mail_template_id.exists' => __('cms_admin_ui.validation.invalid_choice'),
            'title.required' => __('cms_admin_ui.validation.required'),
            'locale.required' => __('cms_admin_ui.validation.required'),
            'locale.regex' => __('cms_admin_ui.validation.locale_code'),
            'email_type.in' => __('cms_admin_ui.validation.invalid_choice'),
            'system_key.regex' => __('cms_admin_ui.validation.alpha_dash_ascii'),
            'system_key.unique' => __('cms_admin_ui.validation.unique'),
            'subject.required' => __('cms_admin_ui.validation.required'),
            'subject.max' => __('cms_admin_ui.validation.max_string'),
            'preheader.max' => __('cms_admin_ui.validation.max_string'),
            'plain_text.max' => __('cms_admin_ui.validation.max_string'),
            'settings.from_name.max' => __('cms_admin_ui.validation.max_string'),
            'settings.from_email.email' => __('cms_admin_ui.validation.email'),
            'settings.reply_to_name.max' => __('cms_admin_ui.validation.max_string'),
            'settings.reply_to_email.email' => __('cms_admin_ui.validation.email'),
        ];
    }
}
