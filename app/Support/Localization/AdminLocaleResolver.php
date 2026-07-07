<?php

namespace App\Support\Localization;

use App\Models\Platform\SiteUserMembership;
use App\Models\User;
use App\Support\Settings\AppSettingStore;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class AdminLocaleResolver
{
    public const ADMIN_DEFAULT_SETTING_KEY = 'admin.default_locale';

    public const DEFAULT_ADMIN_LOCALE = 'nl';

    public function __construct(private readonly AppSettingStore $settingStore) {}

    public function resolveForRequest(Request $request): string
    {
        if ($this->isAdminRequest($request)) {
            return $this->resolveAdminLocale($request);
        }

        if ($this->isPlatformRequest($request)) {
            return $this->resolvePlatformLocale($request);
        }

        return $this->resolveSessionLocale($request);
    }

    public function resolveAdminLocale(Request $request): string
    {
        $user = $request->user();

        if ($user instanceof User && TenantContext::isResolved()) {
            $membershipLocale = SiteUserMembership::query()
                ->where('site_id', TenantContext::siteId())
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->value('admin_locale');

            if ($this->isAllowedLocale($membershipLocale)) {
                return (string) $membershipLocale;
            }
        }

        $tenantDefault = TenantContext::isResolved()
            ? $this->settingStore->get(self::ADMIN_DEFAULT_SETTING_KEY)
            : null;

        if ($this->isAllowedLocale($tenantDefault)) {
            return (string) $tenantDefault;
        }

        if ($this->isAllowedLocale(self::DEFAULT_ADMIN_LOCALE)) {
            return self::DEFAULT_ADMIN_LOCALE;
        }

        return $this->fallbackLocale();
    }

    public function resolvePlatformLocale(Request $request): string
    {
        return $this->resolveSessionLocale($request);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function localeOptions(): array
    {
        return collect($this->availableLocales())
            ->map(static fn (string $locale): array => [
                'value' => $locale,
                'label' => strtoupper($locale),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function availableLocales(): array
    {
        return collect((array) config('app.available_locales', [config('app.locale', 'en')]))
            ->map(static fn (mixed $locale): string => trim((string) $locale))
            ->filter(static fn (string $locale): bool => $locale !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function isAllowedLocale(mixed $locale): bool
    {
        return in_array((string) $locale, $this->availableLocales(), true);
    }

    private function resolveSessionLocale(Request $request): string
    {
        $sessionLocale = (string) $request->session()->get('locale', '');

        if ($this->isAllowedLocale($sessionLocale)) {
            return $sessionLocale;
        }

        return $this->fallbackLocale();
    }

    private function fallbackLocale(): string
    {
        $appLocale = (string) config('app.locale', 'en');

        if ($this->isAllowedLocale($appLocale)) {
            return $appLocale;
        }

        $fallbackLocale = (string) config('app.fallback_locale', 'en');

        if ($this->isAllowedLocale($fallbackLocale)) {
            return $fallbackLocale;
        }

        return (string) ($this->availableLocales()[0] ?? 'en');
    }

    private function isAdminRequest(Request $request): bool
    {
        return $request->is('admin') || $request->is('admin/*');
    }

    private function isPlatformRequest(Request $request): bool
    {
        return $request->is('platform') || $request->is('platform/*');
    }
}
