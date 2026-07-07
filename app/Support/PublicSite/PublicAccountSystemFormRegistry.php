<?php

namespace App\Support\PublicSite;

use Illuminate\Support\Arr;

class PublicAccountSystemFormRegistry
{
    public const LOGIN = 'site_user_login';

    public const REGISTER = 'site_user_register';

    public const FORGOT_PASSWORD = 'site_user_forgot_password';

    public const RESET_PASSWORD = 'site_user_reset_password';

    public const PROFILE = 'site_user_profile';

    public const SECURITY = 'site_user_security';

    public const TWO_FACTOR_CHALLENGE = 'site_user_two_factor_challenge';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function forms(): array
    {
        return [
            self::LOGIN => [
                'title_key' => 'public_account.system_forms.login.title',
                'submit_key' => 'public_account.system_forms.login.submit',
                'success_key' => 'public_account.system_forms.login.success',
                'fields' => [
                    $this->lockedField('email', 'email', 'public_account.fields.email', true, 'credential'),
                    $this->lockedField('password', 'text', 'public_account.fields.password', true, 'credential', ['input_type' => 'password']),
                    $this->lockedField('remember', 'checkbox', 'public_account.fields.remember', false, 'credential'),
                ],
            ],
            self::REGISTER => [
                'title_key' => 'public_account.system_forms.register.title',
                'submit_key' => 'public_account.system_forms.register.submit',
                'success_key' => 'public_account.system_forms.register.success',
                'profile_fields' => true,
                'fields' => [
                    $this->lockedField('name', 'text', 'public_account.fields.name', true, 'account'),
                    $this->lockedField('email', 'email', 'public_account.fields.email', true, 'credential'),
                    $this->lockedField('password', 'text', 'public_account.fields.password', true, 'credential', ['input_type' => 'password']),
                    $this->lockedField('password_confirmation', 'text', 'public_account.fields.password_confirmation', true, 'credential', ['input_type' => 'password']),
                ],
            ],
            self::FORGOT_PASSWORD => [
                'title_key' => 'public_account.system_forms.forgot_password.title',
                'submit_key' => 'public_account.system_forms.forgot_password.submit',
                'success_key' => 'public_account.system_forms.forgot_password.success',
                'fields' => [
                    $this->lockedField('email', 'email', 'public_account.fields.email', true, 'credential'),
                ],
            ],
            self::RESET_PASSWORD => [
                'title_key' => 'public_account.system_forms.reset_password.title',
                'submit_key' => 'public_account.system_forms.reset_password.submit',
                'success_key' => 'public_account.system_forms.reset_password.success',
                'fields' => [
                    $this->lockedField('email', 'email', 'public_account.fields.email', true, 'credential'),
                    $this->lockedField('password', 'text', 'public_account.fields.password', true, 'credential', ['input_type' => 'password']),
                    $this->lockedField('password_confirmation', 'text', 'public_account.fields.password_confirmation', true, 'credential', ['input_type' => 'password']),
                ],
            ],
            self::PROFILE => [
                'title_key' => 'public_account.system_forms.profile.title',
                'submit_key' => 'public_account.system_forms.profile.submit',
                'success_key' => 'public_account.system_forms.profile.success',
                'profile_fields' => true,
                'fields' => [
                    $this->lockedField('name', 'text', 'public_account.fields.name', true, 'account'),
                    $this->lockedField('first_name', 'text', 'public_account.fields.first_name', false, 'profile'),
                    $this->lockedField('last_name', 'text', 'public_account.fields.last_name', false, 'profile'),
                    $this->lockedField('phone', 'text', 'public_account.fields.phone', false, 'profile'),
                    $this->lockedField('marketing_opt_in', 'checkbox', 'public_account.fields.marketing_opt_in', false, 'profile'),
                ],
            ],
            self::SECURITY => [
                'title_key' => 'public_account.system_forms.security.title',
                'submit_key' => 'public_account.system_forms.security.submit',
                'success_key' => 'public_account.system_forms.security.success',
                'fields' => [
                    $this->lockedField('current_password', 'text', 'public_account.fields.current_password', true, 'credential', ['input_type' => 'password']),
                    $this->lockedField('password', 'text', 'public_account.fields.password', true, 'credential', ['input_type' => 'password']),
                    $this->lockedField('password_confirmation', 'text', 'public_account.fields.password_confirmation', true, 'credential', ['input_type' => 'password']),
                ],
            ],
            self::TWO_FACTOR_CHALLENGE => [
                'title_key' => 'public_account.system_forms.two_factor_challenge.title',
                'submit_key' => 'public_account.system_forms.two_factor_challenge.submit',
                'success_key' => 'public_account.system_forms.two_factor_challenge.success',
                'fields' => [
                    $this->lockedField('code', 'text', 'public_account.fields.two_factor_code', false, 'credential'),
                    $this->lockedField('recovery_code', 'text', 'public_account.fields.recovery_code', false, 'credential'),
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function profileFieldDefinitions(): array
    {
        return [
            [
                'key' => 'company_name',
                'label_key' => 'public_account.profile_fields.company_name',
                'type' => 'text',
                'validation_rules' => ['nullable', 'string', 'max:255'],
                'is_required' => false,
                'show_on_register' => true,
                'show_on_profile' => true,
                'sort_order' => 100,
                'options' => [],
            ],
            [
                'key' => 'vat_number',
                'label_key' => 'public_account.profile_fields.vat_number',
                'type' => 'text',
                'validation_rules' => ['nullable', 'string', 'max:64'],
                'is_required' => false,
                'show_on_register' => true,
                'show_on_profile' => true,
                'sort_order' => 110,
                'options' => [],
            ],
            [
                'key' => 'customer_type',
                'label_key' => 'public_account.profile_fields.customer_type',
                'type' => 'select',
                'validation_rules' => ['nullable', 'string', 'in:private,business'],
                'is_required' => false,
                'show_on_register' => true,
                'show_on_profile' => true,
                'sort_order' => 120,
                'options' => [
                    ['key' => 'private', 'label_key' => 'public_account.profile_field_options.customer_type.private'],
                    ['key' => 'business', 'label_key' => 'public_account.profile_field_options.customer_type.business'],
                ],
            ],
        ];
    }

    public function hasForm(string $systemKey): bool
    {
        return array_key_exists($systemKey, $this->forms());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function form(string $systemKey): ?array
    {
        return $this->forms()[$systemKey] ?? null;
    }

    /**
     * @return list<string>
     */
    public function lockedFieldKeys(string $systemKey): array
    {
        $form = $this->form($systemKey);

        if (! is_array($form)) {
            return [];
        }

        $profileFieldKeys = (bool) ($form['profile_fields'] ?? false)
            ? collect($this->profileFieldDefinitions())->pluck('key')->map(fn (mixed $key): string => 'profile_'.$key)->all()
            : [];

        return collect((array) ($form['fields'] ?? []))
            ->pluck('key')
            ->merge($profileFieldKeys)
            ->filter()
            ->map(fn (mixed $key): string => (string) $key)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function lockedField(string $key, string $type, string $labelKey, bool $required, string $source, array $settings = []): array
    {
        return [
            'key' => $key,
            'type' => $type,
            'label_key' => $labelKey,
            'is_required' => $required,
            'settings' => array_merge($settings, [
                'system' => [
                    'locked' => true,
                    'source' => $source,
                ],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $fieldSettings
     */
    public function isLockedField(array $fieldSettings): bool
    {
        return (bool) Arr::get($fieldSettings, 'system.locked', false);
    }
}
