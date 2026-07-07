<?php

namespace App\Actions\Admin\Cms;

use App\Jobs\Admin\Cms\SendCmsRenderedEmailJob;
use App\Models\Cms\CmsEmail;
use App\Models\Cms\CmsEmailDelivery;
use App\Models\Cms\CmsFormSubmission;
use App\Support\Tenancy\TenantContext;

class SendCmsFormSubmissionEmailsAction
{
    public function __construct(
        private readonly ResolveCmsSystemEmailAction $resolveSystemEmail,
        private readonly BuildCmsEmailContextAction $buildContext,
        private readonly RenderCmsEmailAction $renderEmail,
    ) {}

    public function handle(CmsFormSubmission $submission): void
    {
        $submission->loadMissing(['form.submissionEmail', 'values']);

        $this->sendAdminNotification($submission);
        $this->sendSubmissionEmail($submission);
    }

    private function sendAdminNotification(CmsFormSubmission $submission): void
    {
        $recipient = trim((string) $submission->form?->notification_email);

        if ($recipient === '') {
            return;
        }

        $email = $this->resolveSystemEmail->handle('cms_form.admin_notification', (string) $submission->locale);

        if ($email === null) {
            return;
        }

        $context = $this->buildContext->formSubmission($submission);

        $this->queueEmail($submission, $email, $recipient, $context, ['delivery_type' => 'admin_notification']);
    }

    private function sendSubmissionEmail(CmsFormSubmission $submission): void
    {
        $form = $submission->form;

        if (! $form?->submission_email_enabled) {
            return;
        }

        $email = $form->submissionEmail;

        if (! $email instanceof CmsEmail || ! $email->is_active || $email->context_key !== 'cms.form_submission.email') {
            return;
        }

        $recipient = $this->recipientFromField($submission, (int) $form->submission_to_cms_form_field_id);
        $context = $this->buildContext->formSubmission($submission);

        if (! $this->validEmail($recipient)) {
            $this->failedDelivery($submission, $email, $recipient, $context, 'Submission recipient is missing or invalid.');

            return;
        }

        $cc = $this->recipientRows($submission, $form->submission_cc_recipients ?? []);
        $bcc = $this->recipientRows($submission, $form->submission_bcc_recipients ?? []);

        $this->queueEmail($submission, $email, $recipient, $context, [
            'delivery_type' => 'form_submission_recipient',
            'cc' => $cc,
            'bcc' => $bcc,
        ], $cc, $bcc);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $metadata
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     */
    private function queueEmail(CmsFormSubmission $submission, CmsEmail $email, string $recipient, array $context, array $metadata = [], array $cc = [], array $bcc = []): void
    {
        $subject = $this->renderEmail->handle($email, $context)['subject'];

        $delivery = CmsEmailDelivery::query()->create([
            'cms_email_id' => $email->id,
            'context_type' => 'cms_form_submission',
            'context_id' => $submission->id,
            'recipient_email' => $recipient,
            'status' => 'pending',
            'subject_snapshot' => $subject,
            'metadata' => array_filter([
                'form_id' => $submission->cms_form_id,
                ...$metadata,
            ], fn (mixed $value): bool => $value !== null && $value !== []),
        ]);
        $siteId = TenantContext::siteId();

        if ($siteId === null) {
            $delivery->forceFill([
                'status' => 'failed',
                'error_message' => 'Tenant site context is missing for queued CMS email delivery.',
            ])->save();

            return;
        }

        SendCmsRenderedEmailJob::dispatch(
            $siteId,
            (int) $delivery->id,
            (int) $email->id,
            $recipient,
            $context,
            $cc,
            $bcc,
        )->afterCommit();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function failedDelivery(CmsFormSubmission $submission, CmsEmail $email, string $recipient, array $context, string $message): void
    {
        CmsEmailDelivery::query()->create([
            'cms_email_id' => $email->id,
            'context_type' => 'cms_form_submission',
            'context_id' => $submission->id,
            'recipient_email' => $recipient,
            'status' => 'failed',
            'subject_snapshot' => $this->renderEmail->handle($email, $context)['subject'],
            'error_message' => $message,
            'metadata' => [
                'form_id' => $submission->cms_form_id,
                'delivery_type' => 'form_submission_recipient',
            ],
        ]);
    }

    private function recipientFromField(CmsFormSubmission $submission, int $fieldId): string
    {
        if ($fieldId <= 0) {
            return '';
        }

        return trim((string) $submission->values->firstWhere('cms_form_field_id', $fieldId)?->value);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, string>
     */
    private function recipientRows(CmsFormSubmission $submission, array $rows): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row) use ($submission): string {
                if (($row['type'] ?? null) === 'field') {
                    return $this->recipientFromField($submission, (int) ($row['field_id'] ?? 0));
                }

                return trim((string) ($row['email'] ?? ''));
            })
            ->filter(fn (string $email): bool => $this->validEmail($email))
            ->unique()
            ->values()
            ->all();
    }

    private function validEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
