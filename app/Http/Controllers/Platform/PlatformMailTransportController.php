<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\Mail\ConfigurePlatformMailTransportAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StorePlatformMailTransportRequest;
use App\Models\Platform\PlatformMailTransport;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PlatformMailTransportController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function edit(): Response
    {
        $transport = PlatformMailTransport::query()->latest('id')->first();

        return Inertia::render('Platform/MailTransport/Edit', [
            'transport' => $transport ? $this->transportPayload($transport) : null,
        ]);
    }

    public function store(StorePlatformMailTransportRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $transport = DB::connection('central')->transaction(function () use ($validated): PlatformMailTransport {
            $transport = PlatformMailTransport::query()->latest('id')->first() ?? new PlatformMailTransport;
            $previousProvider = $transport->exists ? (string) $transport->provider : null;
            $provider = (string) $validated['provider'];
            $isSmtp = $provider === 'smtp';

            $transport->fill([
                'name' => $validated['name'],
                'provider' => $provider,
                'is_active' => false,
                'status' => 'not_tested',
                'from_name' => $validated['from_name'],
                'from_email' => $validated['from_email'],
                'reply_to_email' => $validated['reply_to_email'] ?? null,
                'host' => $isSmtp ? ($validated['host'] ?? null) : null,
                'port' => $isSmtp ? (int) $validated['port'] : null,
                'encryption' => $isSmtp ? ($validated['encryption'] ?? null) : null,
                'username' => in_array($provider, ['smtp', 'ses'], true) ? ($validated['username'] ?? null) : null,
                'provider_config' => $this->normalizeProviderConfig($provider, $validated['provider_config'] ?? []),
                'last_test_status' => null,
                'last_test_error' => null,
            ]);

            if (filled($validated['secret'] ?? null)) {
                $transport->setSecret($validated['secret']);
            } elseif ($previousProvider !== null && $previousProvider !== $provider) {
                $transport->encrypted_secret = null;
            }

            $transport->save();

            return $transport;
        });

        $this->auditLogger->success(
            action: 'platform.mail_transport.store',
            module: 'platform',
            subjectType: 'platform_mail_transport',
            subjectKey: (string) $transport->id,
            message: __('admin_common_ui.platform.mail.flash.saved'),
            meta: $this->auditMeta($transport),
            request: $request,
        );

        return redirect()
            ->route('platform.mail-transport.edit')
            ->with('status', __('admin_common_ui.platform.mail.flash.saved'));
    }

    public function test(StorePlatformMailTransportRequest $request, ConfigurePlatformMailTransportAction $configureMail): RedirectResponse
    {
        $this->store($request);

        $transport = PlatformMailTransport::query()->latest('id')->firstOrFail();
        $recipient = (string) ($request->user()?->email ?? '');

        if ($recipient === '') {
            return back()->with('error', __('admin_common_ui.platform.mail.flash.test_missing_recipient'));
        }

        try {
            $mailerName = $configureMail->handle($transport);

            Mail::mailer($mailerName ?? config('mail.default'))->raw(
                __('admin_common_ui.platform.mail.test.body'),
                function ($message) use ($recipient): void {
                    $message
                        ->to($recipient)
                        ->subject(__('admin_common_ui.platform.mail.test.subject'));
                },
            );

            $transport->forceFill([
                'status' => 'ready',
                'last_test_status' => 'success',
                'last_test_error' => null,
                'last_tested_at' => now(),
            ])->save();

            $this->auditLogger->success(
                action: 'platform.mail_transport.test',
                module: 'platform',
                subjectType: 'platform_mail_transport',
                subjectKey: (string) $transport->id,
                message: __('admin_common_ui.platform.mail.flash.test_sent', ['recipient' => $recipient]),
                meta: $this->auditMeta($transport),
                request: $request,
            );

            return back()->with('status', __('admin_common_ui.platform.mail.flash.test_sent', ['recipient' => $recipient]));
        } catch (Throwable $exception) {
            report($exception);

            $transport->forceFill([
                'status' => 'failed',
                'last_test_status' => 'failed',
                'last_test_error' => mb_substr($exception->getMessage(), 0, 1000),
                'last_tested_at' => now(),
            ])->save();

            $this->auditLogger->failure(
                action: 'platform.mail_transport.test',
                module: 'platform',
                subjectType: 'platform_mail_transport',
                subjectKey: (string) $transport->id,
                message: __('admin_common_ui.platform.mail.flash.test_failed'),
                meta: $this->auditMeta($transport),
                request: $request,
            );

            return back()->with('error', __('admin_common_ui.platform.mail.flash.test_failed'));
        }
    }

    public function activate(): RedirectResponse
    {
        $transport = PlatformMailTransport::query()->latest('id')->firstOrFail();

        if ($transport->status !== 'ready' || $transport->last_test_status !== 'success') {
            return back()->with('warning', __('admin_common_ui.platform.mail.flash.activate_requires_test'));
        }

        DB::connection('central')->transaction(function () use ($transport): void {
            PlatformMailTransport::query()->whereKeyNot($transport->id)->update(['is_active' => false]);
            $transport->forceFill(['is_active' => true])->save();
        });

        $this->auditLogger->success(
            action: 'platform.mail_transport.activate',
            module: 'platform',
            subjectType: 'platform_mail_transport',
            subjectKey: (string) $transport->id,
            message: __('admin_common_ui.platform.mail.flash.activated'),
            meta: $this->auditMeta($transport),
        );

        return back()->with('status', __('admin_common_ui.platform.mail.flash.activated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function transportPayload(PlatformMailTransport $transport): array
    {
        return [
            'id' => $transport->id,
            'name' => $transport->name,
            'provider' => $transport->provider,
            'is_active' => (bool) $transport->is_active,
            'status' => $transport->status,
            'from_name' => $transport->from_name,
            'from_email' => $transport->from_email,
            'reply_to_email' => $transport->reply_to_email,
            'host' => $transport->host,
            'port' => $transport->port,
            'encryption' => $transport->encryption,
            'username' => $transport->username,
            'provider_config' => $transport->provider_config ?? [],
            'has_secret' => $transport->hasSecret(),
            'last_tested_at' => $transport->last_tested_at?->toIso8601String(),
            'last_test_status' => $transport->last_test_status,
            'last_test_error' => $transport->last_test_error,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditMeta(PlatformMailTransport $transport): array
    {
        return [
            'provider' => $transport->provider,
            'status' => $transport->status,
            'is_active' => (bool) $transport->is_active,
            'host' => $transport->host,
            'port' => $transport->port,
            'provider_config' => $transport->provider_config ?? [],
            'from_email' => $transport->from_email,
        ];
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     * @return array<string, mixed>|null
     */
    private function normalizeProviderConfig(string $provider, array $providerConfig): ?array
    {
        return match ($provider) {
            'mailgun' => [
                'domain' => trim((string) ($providerConfig['domain'] ?? '')),
                'endpoint' => trim((string) ($providerConfig['endpoint'] ?? 'api.mailgun.net')),
            ],
            'postmark' => [
                'message_stream_id' => trim((string) ($providerConfig['message_stream_id'] ?? '')),
            ],
            'ses' => [
                'region' => trim((string) ($providerConfig['region'] ?? 'us-east-1')),
            ],
            default => null,
        };
    }
}
