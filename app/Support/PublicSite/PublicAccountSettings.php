<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsSetting;
use Illuminate\Support\Facades\Schema;

class PublicAccountSettings
{
    public const GROUP = 'public_account';

    public const REGISTRATION_ENABLED = 'registration_enabled';

    public const EMAIL_VERIFICATION_REQUIRED = 'email_verification_required';

    public const TWO_FACTOR_MODE = 'two_factor_mode';

    public function registrationEnabled(): bool
    {
        return $this->boolean(self::REGISTRATION_ENABLED, false);
    }

    public function emailVerificationRequired(): bool
    {
        return $this->boolean(self::EMAIL_VERIFICATION_REQUIRED, false);
    }

    public function twoFactorMode(): string
    {
        $mode = $this->string(self::TWO_FACTOR_MODE, 'disabled');

        return in_array($mode, ['disabled', 'optional', 'required'], true) ? $mode : 'disabled';
    }

    public function twoFactorRequired(): bool
    {
        return $this->twoFactorMode() === 'required';
    }

    public function twoFactorEnabled(): bool
    {
        return $this->twoFactorMode() !== 'disabled';
    }

    private function boolean(string $key, bool $default): bool
    {
        $value = $this->value($key, $default);

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function string(string $key, string $default): string
    {
        $value = $this->value($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    private function value(string $key, mixed $default): mixed
    {
        if (! Schema::connection('tenant')->hasTable('cms_settings')) {
            return $default;
        }

        $payload = CmsSetting::query()
            ->where('group', self::GROUP)
            ->where('key', $key)
            ->value('value');

        if (! is_array($payload) || ! array_key_exists('value', $payload)) {
            return $default;
        }

        return $payload['value'];
    }
}
