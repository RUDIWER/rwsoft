<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\StorePublicAccountSettingsRequest;
use App\Models\Cms\CmsSetting;
use App\Models\PublicSite\SiteUser;
use App\Support\PublicSite\PublicAccountSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SiteUserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Cms/SiteUsers/Index', [
            'siteUsers' => SiteUser::query()
                ->orderByDesc('id')
                ->get()
                ->map(fn (SiteUser $siteUser): array => [
                    'id' => $siteUser->id,
                    'name' => $siteUser->name,
                    'email' => $siteUser->email,
                    'status' => $siteUser->status,
                    'email_verified' => $siteUser->hasVerifiedEmail(),
                    'two_factor_enabled' => $siteUser->hasEnabledTwoFactorAuthentication(),
                    'last_login_at' => $siteUser->last_login_at?->format('d/m/Y H:i'),
                    'created_at' => $siteUser->created_at?->format('d/m/Y H:i'),
                ])
                ->values()
                ->all(),
            'settings' => $this->settingsPayload(),
        ]);
    }

    public function storeSettings(StorePublicAccountSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->upsertSetting(PublicAccountSettings::REGISTRATION_ENABLED, (bool) ($validated['registration_enabled'] ?? false));
        $this->upsertSetting(PublicAccountSettings::EMAIL_VERIFICATION_REQUIRED, (bool) ($validated['email_verification_required'] ?? false));
        $this->upsertSetting(PublicAccountSettings::TWO_FACTOR_MODE, (string) $validated['two_factor_mode']);

        return redirect()
            ->route('admin.cms.site-users.index')
            ->with('status', __('cms_admin_ui.public_account.feedback_settings_saved'));
    }

    public function activate(SiteUser $siteUser): RedirectResponse
    {
        $siteUser->forceFill(['status' => 'active'])->save();

        return redirect()
            ->route('admin.cms.site-users.index')
            ->with('status', __('cms_admin_ui.public_account.feedback_account_activated'));
    }

    public function deactivate(SiteUser $siteUser): RedirectResponse
    {
        $siteUser->forceFill(['status' => 'inactive'])->save();

        return redirect()
            ->route('admin.cms.site-users.index')
            ->with('status', __('cms_admin_ui.public_account.feedback_account_deactivated'));
    }

    public function resetTwoFactor(SiteUser $siteUser): RedirectResponse
    {
        $siteUser->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()
            ->route('admin.cms.site-users.index')
            ->with('status', __('cms_admin_ui.public_account.feedback_two_factor_reset'));
    }

    /**
     * @return array{registration_enabled: bool, email_verification_required: bool, two_factor_mode: string}
     */
    private function settingsPayload(): array
    {
        return [
            'registration_enabled' => (bool) $this->settingValue(PublicAccountSettings::REGISTRATION_ENABLED, false),
            'email_verification_required' => (bool) $this->settingValue(PublicAccountSettings::EMAIL_VERIFICATION_REQUIRED, false),
            'two_factor_mode' => (string) $this->settingValue(PublicAccountSettings::TWO_FACTOR_MODE, 'disabled'),
        ];
    }

    private function settingValue(string $key, mixed $default): mixed
    {
        $payload = CmsSetting::query()
            ->where('group', PublicAccountSettings::GROUP)
            ->where('key', $key)
            ->value('value');

        return is_array($payload) && array_key_exists('value', $payload) ? $payload['value'] : $default;
    }

    private function upsertSetting(string $key, mixed $value): void
    {
        $labels = [
            PublicAccountSettings::REGISTRATION_ENABLED => 'Registration enabled',
            PublicAccountSettings::EMAIL_VERIFICATION_REQUIRED => 'Email verification required',
            PublicAccountSettings::TWO_FACTOR_MODE => 'Two-factor authentication mode',
        ];
        $types = [
            PublicAccountSettings::REGISTRATION_ENABLED => 'boolean',
            PublicAccountSettings::EMAIL_VERIFICATION_REQUIRED => 'boolean',
            PublicAccountSettings::TWO_FACTOR_MODE => 'select',
        ];

        CmsSetting::query()->updateOrCreate(
            ['group' => PublicAccountSettings::GROUP, 'key' => $key],
            [
                'label' => $labels[$key] ?? $key,
                'type' => $types[$key] ?? 'text',
                'value' => ['value' => $value],
                'is_public' => false,
                'sort_order' => array_search($key, array_keys($labels), true) * 10 + 10,
            ],
        );
    }
}
