<?php

namespace App\Support\Tenancy;

use App\Models\Platform\Site;
use Illuminate\Support\Facades\DB;

class TenantContext
{
    private static ?Site $site = null;

    public static function setSite(Site $site): void
    {
        self::$site = $site;
    }

    public static function site(): ?Site
    {
        return self::$site;
    }

    public static function siteId(): ?int
    {
        return self::$site?->id;
    }

    public static function database(): ?string
    {
        return self::$site?->tenant_database;
    }

    public static function isResolved(): bool
    {
        return self::$site instanceof Site;
    }

    public static function clear(): void
    {
        self::$site = null;
        DB::setDefaultConnection((string) config('database.default', 'central'));
    }
}
