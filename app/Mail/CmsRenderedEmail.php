<?php

namespace App\Mail;

use App\Actions\Admin\Cms\RenderCmsEmailAction;
use App\Actions\Platform\Mail\ConfigurePlatformMailTransportAction;
use App\Models\Cms\CmsEmail;
use App\Models\Platform\PlatformMailTransport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CmsRenderedEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly CmsEmail $email,
        private readonly array $data,
    ) {}

    public function build(): self
    {
        $transport = PlatformMailTransport::active();
        $mailerName = app(ConfigurePlatformMailTransportAction::class)->handle($transport);

        if ($mailerName !== null) {
            $this->mailer($mailerName);
        }

        $rendered = app(RenderCmsEmailAction::class)->handle($this->email->loadMissing('mailTemplate'), $this->data);
        $mail = $this
            ->subject($rendered['subject'])
            ->view('mail.cms-rendered', ['html' => $rendered['html']])
            ->text('mail.cms-rendered-text', ['text' => $rendered['text']]);
        $settings = $this->email->settings ?? [];

        $fromEmail = $this->nullableString($settings['from_email'] ?? null) ?? $transport?->from_email;
        $fromName = $this->nullableString($settings['from_name'] ?? null) ?? $transport?->from_name;

        if (filled($fromEmail)) {
            $mail->from((string) $fromEmail, $this->nullableString($fromName));
        }

        $replyToEmail = $this->nullableString($settings['reply_to_email'] ?? null) ?? $transport?->reply_to_email;
        $replyToName = $this->nullableString($settings['reply_to_name'] ?? null) ?? $fromName;

        if (filled($replyToEmail)) {
            $mail->replyTo((string) $replyToEmail, $this->nullableString($replyToName));
        }

        return $mail;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
