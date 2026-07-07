<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsSetting;
use App\Support\Settings\AppSettingStore;
use Illuminate\Support\Carbon;

class CmsSearchConsoleSettings
{
    public const PROPERTY_TYPE_URL_PREFIX = 'url_prefix';

    public const PROPERTY_TYPE_DOMAIN = 'domain';

    public const OAUTH_CLIENT_ID_SETTING = 'search_console.oauth_client_id';

    public const OAUTH_CLIENT_SECRET_SETTING = 'search_console.oauth_client_secret';

    public const OAUTH_TOKEN_SETTING = 'search_console.oauth_token';

    public function __construct(private readonly AppSettingStore $settingStore) {}

    public function enabled(): bool
    {
        return (bool) $this->settingValue('search_console', 'enabled', false);
    }

    public function propertyType(): string
    {
        $type = (string) $this->settingValue('search_console', 'property_type', self::PROPERTY_TYPE_URL_PREFIX);

        return in_array($type, [self::PROPERTY_TYPE_URL_PREFIX, self::PROPERTY_TYPE_DOMAIN], true)
            ? $type
            : self::PROPERTY_TYPE_URL_PREFIX;
    }

    public function siteUrl(): string
    {
        $siteUrl = trim((string) $this->settingValue('search_console', 'site_url', ''));

        if ($siteUrl === '') {
            return '';
        }

        if ($this->propertyType() === self::PROPERTY_TYPE_DOMAIN) {
            return str_starts_with($siteUrl, 'sc-domain:')
                ? $siteUrl
                : 'sc-domain:'.preg_replace('/^https?:\/\//', '', rtrim($siteUrl, '/'));
        }

        return rtrim($siteUrl, '/').'/';
    }

    public function clientId(): ?string
    {
        return $this->settingStore->get(self::OAUTH_CLIENT_ID_SETTING);
    }

    public function clientSecret(): ?string
    {
        return $this->settingStore->get(self::OAUTH_CLIENT_SECRET_SETTING);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function oauthToken(): ?array
    {
        $token = $this->settingStore->get(self::OAUTH_TOKEN_SETTING);

        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        $decoded = json_decode($token, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $token
     */
    public function storeOauthToken(array $token): void
    {
        $encoded = json_encode($token, JSON_UNESCAPED_SLASHES);

        if (is_string($encoded)) {
            $this->settingStore->put(self::OAUTH_TOKEN_SETTING, $encoded, true);
        }
    }

    public function clearOauthToken(): void
    {
        $this->settingStore->put(self::OAUTH_TOKEN_SETTING, null, true);
        $this->settingStore->put('search_console.connected_email', null);
        $this->settingStore->put('search_console.last_success_at', null);
    }

    public function hasOAuthClient(): bool
    {
        return filled($this->clientId()) && filled($this->clientSecret());
    }

    public function hasOauthToken(): bool
    {
        return $this->oauthToken() !== null;
    }

    public function connectedEmail(): ?string
    {
        return $this->settingStore->get('search_console.connected_email');
    }

    public function lastSuccessAt(): ?string
    {
        return $this->settingStore->get('search_console.last_success_at');
    }

    public function lastError(): ?string
    {
        return $this->settingStore->get('search_console.last_error');
    }

    public function markSuccess(?string $email = null): void
    {
        $this->settingStore->put('search_console.last_success_at', Carbon::now()->toISOString());
        $this->settingStore->put('search_console.last_error', null);

        if ($email !== null) {
            $this->settingStore->put('search_console.connected_email', $email);
        }
    }

    public function markError(string $message): void
    {
        $this->settingStore->put('search_console.last_error', mb_substr($message, 0, 500));
    }

    public function analyticsCacheSeconds(): int
    {
        return max(60, (int) $this->settingValue('search_console', 'analytics_cache_seconds', 43200));
    }

    public function inspectionCacheSeconds(): int
    {
        return max(60, (int) $this->settingValue('search_console', 'inspection_cache_seconds', 86400));
    }

    public function queryLimit(): int
    {
        return max(1, min(25, (int) $this->settingValue('search_console', 'query_limit', 10)));
    }

    /**
     * @return array<string, string>
     */
    public function propertyTypeOptions(): array
    {
        return [
            self::PROPERTY_TYPE_URL_PREFIX => __('cms_admin_ui.settings.form.search_console_property_type_url_prefix'),
            self::PROPERTY_TYPE_DOMAIN => __('cms_admin_ui.settings.form.search_console_property_type_domain'),
        ];
    }

    private function settingValue(string $group, string $key, mixed $default): mixed
    {
        $setting = CmsSetting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        return $setting instanceof CmsSetting
            ? ($setting->value['value'] ?? $default)
            : $default;
    }
}
