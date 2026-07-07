<?php

namespace App\Notifications\PublicSite;

use App\Actions\Admin\Cms\BuildCmsEmailContextAction;
use App\Actions\Admin\Cms\RenderCmsEmailAction;
use App\Actions\Admin\Cms\ResolveCmsSystemEmailAction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class SiteUserVerifyEmailNotification extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiresAt = now()->addMinutes(60);
        $url = URL::temporarySignedRoute(
            'site-user.verification.verify',
            $expiresAt,
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        $email = app(ResolveCmsSystemEmailAction::class)->handle('site_user.verify_email', (string) app()->getLocale());

        if ($email !== null) {
            $context = app(BuildCmsEmailContextAction::class)->auth($notifiable, $url, $expiresAt);
            $rendered = app(RenderCmsEmailAction::class)->handle($email, $context);

            return (new MailMessage)
                ->subject($rendered['subject'])
                ->view('mail.cms-rendered', ['html' => $rendered['html']]);
        }

        return (new MailMessage)
            ->subject(__('public_account.mail.verify_email.subject'))
            ->line(__('public_account.mail.verify_email.intro'))
            ->action(__('public_account.mail.verify_email.action'), $url)
            ->line(__('public_account.mail.verify_email.outro'));
    }
}
