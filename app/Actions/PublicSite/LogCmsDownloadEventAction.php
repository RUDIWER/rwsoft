<?php

namespace App\Actions\PublicSite;

use App\Models\Cms\CmsDownloadAsset;
use App\Models\Cms\CmsDownloadEvent;
use App\Models\PublicSite\SiteUser;
use Illuminate\Http\Request;

class LogCmsDownloadEventAction
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(?CmsDownloadAsset $asset, string $event, Request $request, array $metadata = []): void
    {
        CmsDownloadEvent::query()->create([
            'cms_download_asset_id' => $asset?->id,
            'site_user_id' => $request->user('site_user') instanceof SiteUser ? $request->user('site_user')->id : null,
            'event' => $event,
            'ip_hash' => $request->ip() ? hash('sha256', (string) $request->ip()) : null,
            'user_agent_hash' => $request->userAgent() ? hash('sha256', (string) $request->userAgent()) : null,
            'metadata' => $metadata,
        ]);
    }
}
