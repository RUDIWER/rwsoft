<?php

namespace App\Actions\Platform\Mail;

use App\Models\Platform\PlatformMailTransport;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ConfigurePlatformMailTransportAction
{
    public const MailerName = 'platform_smtp';

    public function handle(?PlatformMailTransport $transport = null): ?string
    {
        $transport ??= PlatformMailTransport::active();

        if (! $transport instanceof PlatformMailTransport || ! in_array($transport->provider, ['smtp', 'mailgun', 'postmark', 'ses', 'resend'], true)) {
            return null;
        }

        Config::set('mail.mailers.'.self::MailerName, $this->mailerConfig($transport));

        if (filled($transport->from_email)) {
            Config::set('mail.from.address', $transport->from_email);
            Config::set('mail.from.name', $transport->from_name ?: config('app.name'));
        }

        $this->forgetResolvedMailers();

        return self::MailerName;
    }

    /**
     * @return array<string, mixed>
     */
    private function mailerConfig(PlatformMailTransport $transport): array
    {
        $providerConfig = $transport->provider_config ?? [];

        return match ($transport->provider) {
            'mailgun' => [
                'transport' => 'mailgun',
                'domain' => $providerConfig['domain'] ?? null,
                'secret' => $transport->secret(),
                'endpoint' => $providerConfig['endpoint'] ?? 'api.mailgun.net',
                'scheme' => 'https',
                'client' => ['timeout' => 10],
            ],
            'postmark' => [
                'transport' => 'postmark',
                'token' => $transport->secret(),
                'message_stream_id' => $providerConfig['message_stream_id'] ?? null,
                'client' => ['timeout' => 10],
            ],
            'ses' => [
                'transport' => 'ses',
                'key' => $transport->username ?: null,
                'secret' => $transport->secret(),
                'region' => $providerConfig['region'] ?? 'us-east-1',
            ],
            'resend' => [
                'transport' => 'resend',
                'key' => $transport->secret(),
            ],
            default => [
                'transport' => 'smtp',
                'scheme' => $transport->encryption === 'ssl' ? 'smtps' : null,
                'url' => null,
                'host' => $transport->host,
                'port' => $transport->port,
                'encryption' => $transport->encryption ?: null,
                'username' => $transport->username ?: null,
                'password' => $transport->secret(),
                'timeout' => 10,
                'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
            ],
        };
    }

    private function forgetResolvedMailers(): void
    {
        try {
            $manager = Mail::getFacadeRoot();

            if (is_object($manager) && method_exists($manager, 'forgetMailers')) {
                $manager->forgetMailers();
            }
        } catch (Throwable) {
        }
    }
}
