<?php

namespace App\Support\Settings;

use App\Models\AppSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class AppSettingStore
{
    public function get(string $key, ?string $default = null): ?string
    {
        $setting = AppSetting::query()->where('key', $key)->first();

        if (! $setting instanceof AppSetting || $setting->value === null) {
            return $default;
        }

        if (! $setting->is_encrypted) {
            return (string) $setting->value;
        }

        try {
            return Crypt::decryptString((string) $setting->value);
        } catch (DecryptException) {
            return $default;
        }
    }

    public function put(string $key, ?string $value, bool $encrypted = false): void
    {
        $normalizedValue = $value !== null ? trim($value) : null;

        if ($normalizedValue === '') {
            $normalizedValue = null;
        }

        $storedValue = $normalizedValue;
        $isEncrypted = false;

        if ($normalizedValue !== null && $encrypted) {
            $storedValue = Crypt::encryptString($normalizedValue);
            $isEncrypted = true;
        }

        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'is_encrypted' => $isEncrypted,
            ],
        );
    }
}
