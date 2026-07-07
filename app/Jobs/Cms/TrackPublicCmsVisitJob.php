<?php

namespace App\Jobs\Cms;

use App\Actions\Platform\ConfigureTenantDatabaseAction;
use App\Models\Cms\CmsVisit;
use App\Models\Cms\CmsVisitor;
use App\Models\Platform\Site;
use App\Support\PublicSite\CmsVisitorTrackingSettings;
use App\Support\Tenancy\TenantDatabaseGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class TrackPublicCmsVisitJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $siteId,
        public array $payload,
    ) {}

    public function handle(CmsVisitorTrackingSettings $settings): void
    {
        $this->configureTenantDatabase();

        if (! $settings->enabled()) {
            return;
        }

        $uuid = $this->stringValue('uuid');
        $ip = $settings->storeIp() ? $this->stringValue('ip') : null;
        $ipHash = $settings->storeIpHash() ? $settings->ipHash($this->stringValue('ip')) : null;
        $now = Carbon::now();

        $visitor = $this->visitor($uuid, $ipHash, $ip, $now);

        CmsVisit::query()->create([
            'cms_visitor_id' => $visitor?->id,
            'uuid' => $uuid,
            'ip' => $ip,
            'ip_hash' => $ipHash,
            'method' => $this->stringValue('method') ?: 'GET',
            'url' => $this->stringValue('url'),
            'path' => $this->stringValue('path'),
            'locale' => $this->stringValue('locale'),
            'ref' => $this->stringValue('ref'),
            'referer' => $this->stringValue('referer'),
            'utm_source' => $this->stringValue('utm_source'),
            'utm_medium' => $this->stringValue('utm_medium'),
            'utm_campaign' => $this->stringValue('utm_campaign'),
            'user_agent' => $this->limitedStringValue('user_agent', 512),
            'platform' => $this->limitedStringValue('platform', 255),
            'country_code_header' => $this->stringValue('country_code_header'),
            'is_crawler' => (bool) ($this->payload['is_crawler'] ?? false),
            'data' => (array) ($this->payload['data'] ?? []),
        ]);
    }

    private function visitor(?string $uuid, ?string $ipHash, ?string $ip, Carbon $now): ?CmsVisitor
    {
        if ($uuid === null && $ipHash === null) {
            return null;
        }

        $query = CmsVisitor::query();

        if ($uuid !== null) {
            $query->where('uuid', $uuid);
        } elseif ($ipHash !== null) {
            $query->where('ip_hash', $ipHash);
        }

        $visitor = $query->first();

        if (! $visitor instanceof CmsVisitor) {
            return CmsVisitor::query()->create([
                'uuid' => $uuid,
                'ip' => $ip,
                'ip_hash' => $ipHash,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);
        }

        $visitor->forceFill([
            'uuid' => $visitor->uuid ?: $uuid,
            'ip' => $ip,
            'ip_hash' => $visitor->ip_hash ?: $ipHash,
            'last_seen_at' => $now,
        ])->save();

        return $visitor;
    }

    private function configureTenantDatabase(): void
    {
        $site = Site::on('central')->findOrFail($this->siteId);

        app(ConfigureTenantDatabaseAction::class)->handle($site);
        TenantDatabaseGuard::ensureTenantConnection();
    }

    private function stringValue(string $key): ?string
    {
        $value = trim((string) ($this->payload[$key] ?? ''));

        return $value !== '' ? $value : null;
    }

    private function limitedStringValue(string $key, int $limit): ?string
    {
        $value = $this->stringValue($key);

        return $value !== null ? mb_substr($value, 0, $limit) : null;
    }
}
