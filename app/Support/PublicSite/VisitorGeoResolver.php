<?php

namespace App\Support\PublicSite;

use Illuminate\Support\Facades\Config;
use Stevebauman\Location\Drivers\Cloudflare;
use Stevebauman\Location\Drivers\IpApi;
use Stevebauman\Location\Drivers\IpData;
use Stevebauman\Location\Drivers\IpInfo;
use Stevebauman\Location\Facades\Location;

class VisitorGeoResolver
{
    public function __construct(private readonly CmsVisitorTrackingSettings $settings) {}

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(?string $ip, ?string $countryCodeHeader = null): ?array
    {
        if (! $this->settings->geoEnabled()) {
            return null;
        }

        if ($this->settings->geoProvider() === CmsVisitorTrackingSettings::GEO_PROVIDER_CLOUDFLARE) {
            return $countryCodeHeader !== null ? ['country_code' => $countryCodeHeader] : null;
        }

        $ip = trim((string) $ip);

        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $this->configureProvider();

        try {
            $position = Location::get($ip);
        } catch (\Throwable) {
            return null;
        }

        if ($position === false) {
            return null;
        }

        return [
            'country_code' => $this->normalizedCountryCode($position->countryCode ?? null),
            'country_name' => $this->stringOrNull($position->countryName ?? null),
            'region_code' => $this->stringOrNull($position->regionCode ?? null),
            'region_name' => $this->stringOrNull($position->regionName ?? null),
            'city_name' => $this->stringOrNull($position->cityName ?? null),
            'zip_code' => $this->stringOrNull($position->zipCode ?? null),
            'latitude' => is_numeric($position->latitude ?? null) ? (float) $position->latitude : null,
            'longitude' => is_numeric($position->longitude ?? null) ? (float) $position->longitude : null,
            'timezone' => $this->stringOrNull($position->timezone ?? null),
        ];
    }

    private function configureProvider(): void
    {
        Config::set('location.driver', $this->driverClass());
        Config::set('location.fallbacks', []);
        Config::set('location.http.timeout', 3);
        Config::set('location.http.connect_timeout', 3);

        $apiKey = $this->settings->geoApiKey();

        if ($apiKey === null) {
            return;
        }

        match ($this->settings->geoProvider()) {
            CmsVisitorTrackingSettings::GEO_PROVIDER_IP_INFO => Config::set('location.ipinfo.token', $apiKey),
            CmsVisitorTrackingSettings::GEO_PROVIDER_IP_DATA => Config::set('location.ipdata.token', $apiKey),
            default => Config::set('location.ip_api.token', $apiKey),
        };
    }

    private function driverClass(): string
    {
        return match ($this->settings->geoProvider()) {
            CmsVisitorTrackingSettings::GEO_PROVIDER_IP_INFO => IpInfo::class,
            CmsVisitorTrackingSettings::GEO_PROVIDER_IP_DATA => IpData::class,
            CmsVisitorTrackingSettings::GEO_PROVIDER_CLOUDFLARE => Cloudflare::class,
            default => IpApi::class,
        };
    }

    private function normalizedCountryCode(mixed $countryCode): ?string
    {
        $countryCode = strtoupper(trim((string) $countryCode));

        return preg_match('/^[A-Z]{2}$/', $countryCode) === 1 ? $countryCode : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
