<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsSetting;
use App\Support\Settings\AppSettingStore;
use Illuminate\Http\Request;

class CmsVisitorTrackingSettings
{
    public const COOKIE_NAME = 'rw_visitor_uuid';

    public const RETENTION_DAYS = 'days';

    public const RETENTION_ALWAYS = 'always';

    public const GEO_PROVIDER_IP_API = 'ip_api';

    public const GEO_PROVIDER_IP_INFO = 'ipinfo';

    public const GEO_PROVIDER_IP_DATA = 'ipdata';

    public const GEO_PROVIDER_CLOUDFLARE = 'cloudflare';

    public const GEO_API_KEY_SETTING = 'visitor_tracking.geo_api_key';

    public function __construct(private readonly AppSettingStore $settingStore) {}

    public function enabled(): bool
    {
        return (bool) $this->settingValue('visitor_tracking', 'enabled', false);
    }

    public function retentionMode(): string
    {
        $mode = (string) $this->settingValue('visitor_tracking', 'retention_mode', self::RETENTION_DAYS);

        return in_array($mode, [self::RETENTION_DAYS, self::RETENTION_ALWAYS], true) ? $mode : self::RETENTION_DAYS;
    }

    public function retentionDays(): int
    {
        return max(1, min(3650, (int) $this->settingValue('visitor_tracking', 'retention_days', 90)));
    }

    public function cookieDays(): int
    {
        return max(1, min(730, (int) $this->settingValue('visitor_tracking', 'cookie_days', 90)));
    }

    public function storeIp(): bool
    {
        return (bool) $this->settingValue('visitor_tracking', 'store_ip', true);
    }

    public function storeIpHash(): bool
    {
        return (bool) $this->settingValue('visitor_tracking', 'store_ip_hash', true);
    }

    public function ignoreBots(): bool
    {
        return (bool) $this->settingValue('visitor_tracking', 'ignore_bots', true);
    }

    public function geoEnabled(): bool
    {
        return (bool) $this->settingValue('visitor_tracking', 'geo_enabled', false);
    }

    public function geoProvider(): string
    {
        $provider = (string) $this->settingValue('visitor_tracking', 'geo_provider', self::GEO_PROVIDER_IP_API);

        return array_key_exists($provider, $this->geoProviderOptions()) ? $provider : self::GEO_PROVIDER_IP_API;
    }

    public function geoApiKey(): ?string
    {
        $apiKey = trim((string) $this->settingStore->get(self::GEO_API_KEY_SETTING, ''));

        return $apiKey !== '' ? $apiKey : null;
    }

    public function hasGeoApiKey(): bool
    {
        return $this->geoApiKey() !== null;
    }

    /**
     * @return array<int, string>
     */
    public function allowedCountries(): array
    {
        return collect(preg_split('/[\s,;]+/', (string) $this->settingValue('visitor_tracking', 'geo_allowed_countries', '')) ?: [])
            ->map(static fn (mixed $country): string => strtoupper(trim((string) $country)))
            ->filter(static fn (string $country): bool => preg_match('/^[A-Z]{2}$/', $country) === 1)
            ->unique()
            ->values()
            ->all();
    }

    public function deleteDisallowedCountries(): bool
    {
        return (bool) $this->settingValue('visitor_tracking', 'geo_delete_disallowed_countries', false);
    }

    public function shouldPrune(): bool
    {
        return $this->retentionMode() === self::RETENTION_DAYS;
    }

    /**
     * @return array<int, string>
     */
    public function excludedPaths(): array
    {
        return collect(preg_split('/\R/u', (string) $this->settingValue('visitor_tracking', 'excluded_paths', '')) ?: [])
            ->map(static fn (mixed $path): string => trim((string) $path))
            ->filter(static fn (string $path): bool => $path !== '' && str_starts_with($path, '/'))
            ->unique()
            ->values()
            ->all();
    }

    public function pathIsExcluded(string $path): bool
    {
        $path = '/'.ltrim($path, '/');

        foreach ($this->defaultExcludedPaths() as $excludedPath) {
            if ($path === $excludedPath || str_starts_with($path, rtrim($excludedPath, '/').'/')) {
                return true;
            }
        }

        foreach ($this->excludedPaths() as $excludedPath) {
            if ($path === $excludedPath || str_starts_with($path, rtrim($excludedPath, '/').'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public function geoProviderOptions(): array
    {
        return [
            self::GEO_PROVIDER_IP_API => 'IP-API',
            self::GEO_PROVIDER_IP_INFO => 'IPinfo',
            self::GEO_PROVIDER_IP_DATA => 'IPData',
            self::GEO_PROVIDER_CLOUDFLARE => 'Cloudflare header',
        ];
    }

    public function countryCodeHeader(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'X-Vercel-IP-Country', 'CloudFront-Viewer-Country', 'X-Country-Code'] as $header) {
            $value = strtoupper(trim((string) $request->headers->get($header, '')));

            if (preg_match('/^[A-Z]{2}$/', $value) === 1 && $value !== 'XX') {
                return $value;
            }
        }

        return null;
    }

    public function ipHash(?string $ip): ?string
    {
        $ip = trim((string) $ip);

        if ($ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    /**
     * @return array<int, string>
     */
    private function defaultExcludedPaths(): array
    {
        return ['/admin', '/platform', '/account', '/search/results', '/themes', '/sitemap.xml', '/robots.txt', '/llms.txt'];
    }

    private function settingValue(string $group, string $key, mixed $default = null): mixed
    {
        $setting = CmsSetting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if (! $setting instanceof CmsSetting) {
            return $default;
        }

        return $setting->value['value'] ?? $default;
    }
}
