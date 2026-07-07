<?php

namespace App\Jobs\Admin\Cms;

use App\Actions\Platform\ConfigureTenantDatabaseAction;
use App\Mail\CmsRenderedEmail;
use App\Models\Cms\CmsEmail;
use App\Models\Cms\CmsEmailDelivery;
use App\Models\Platform\Site;
use App\Support\Tenancy\TenantDatabaseGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCmsRenderedEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     */
    public function __construct(
        public int $siteId,
        public int $deliveryId,
        public int $emailId,
        public string $recipient,
        public array $context,
        public array $cc = [],
        public array $bcc = [],
    ) {}

    public function handle(): void
    {
        $this->configureTenantDatabase();

        $delivery = CmsEmailDelivery::query()->findOrFail($this->deliveryId);
        $email = CmsEmail::query()->with('mailTemplate')->findOrFail($this->emailId);

        $delivery->forceFill(['status' => 'processing'])->save();

        Mail::to($this->recipient)
            ->cc($this->cc)
            ->bcc($this->bcc)
            ->send(new CmsRenderedEmail($email, $this->context));

        $delivery->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'error_message' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $this->configureTenantDatabase();

        CmsEmailDelivery::query()
            ->whereKey($this->deliveryId)
            ->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    private function configureTenantDatabase(): void
    {
        $site = Site::on('central')->findOrFail($this->siteId);

        app(ConfigureTenantDatabaseAction::class)->handle($site);
        TenantDatabaseGuard::ensureTenantConnection();
    }
}
