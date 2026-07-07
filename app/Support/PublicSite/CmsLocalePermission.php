<?php

namespace App\Support\PublicSite;

use App\Models\Platform\SiteUserMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class CmsLocalePermission
{
    public function __construct(private readonly CmsLanguageSettings $languageSettings) {}

    public function canEditLocale(?User $user, string $locale): bool
    {
        return in_array(trim($locale), $this->editableLocales($user), true);
    }

    /**
     * @return array<int, string>
     */
    public function editableLocales(?User $user): array
    {
        $activeLocales = $this->languageSettings->activeLocales();

        if (! $user instanceof User) {
            return [];
        }

        if ((bool) $user->is_platform_admin) {
            return $activeLocales;
        }

        if (! TenantContext::isResolved()) {
            return [];
        }

        $membership = SiteUserMembership::query()
            ->where('site_id', TenantContext::siteId())
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $membership instanceof SiteUserMembership) {
            return [];
        }

        $allowedLocales = $membership->allowed_content_locales;

        if ($allowedLocales === null) {
            return $activeLocales;
        }

        return collect((array) $allowedLocales)
            ->map(static fn (mixed $locale): string => trim((string) $locale))
            ->filter(static fn (string $locale): bool => in_array($locale, $activeLocales, true))
            ->unique()
            ->values()
            ->all();
    }
}
