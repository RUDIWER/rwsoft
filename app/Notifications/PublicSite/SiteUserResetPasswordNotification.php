<?php

namespace App\Notifications\PublicSite;

use App\Actions\Admin\Cms\BuildCmsEmailContextAction;
use App\Actions\Admin\Cms\RenderCmsEmailAction;
use App\Actions\Admin\Cms\ResolveCmsSystemEmailAction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteUserResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('site-user.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $email = app(ResolveCmsSystemEmailAction::class)->handle('site_user.reset_password', (string) app()->getLocale());

        if ($email !== null) {
            $context = app(BuildCmsEmailContextAction::class)->auth($notifiable, $url);
            $rendered = app(RenderCmsEmailAction::class)->handle($email, $context);

            return (new MailMessage)
                ->subject($rendered['subject'])
                ->view('mail.cms-rendered', ['html' => $rendered['html']]);
        }

        return (new MailMessage)
            ->subject(__('public_account.mail.reset_password.subject'))
            ->line(__('public_account.mail.reset_password.intro'))
            ->action(__('public_account.mail.reset_password.action'), $url)
            ->line(__('public_account.mail.reset_password.outro'));
    }
}
