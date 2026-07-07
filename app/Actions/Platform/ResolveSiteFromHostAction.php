<?php

namespace App\Actions\Platform;

use App\Models\Platform\Site;
use App\Models\Platform\SiteDomain;

class ResolveSiteFromHostAction
{
    public function handle(string $host): ?Site
    {
        $host = $this->normalizeHost($host);

        if ($host === '') {
            return null;
        }

        $domain = SiteDomain::query()
            ->with('site')
            ->where('host', $host)
            ->first();

        return $domain?->site;
    }

    public function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = explode('/', $host, 2)[0];
        $host = explode(':', $host, 2)[0];

        return trim($host, '.');
    }
}
