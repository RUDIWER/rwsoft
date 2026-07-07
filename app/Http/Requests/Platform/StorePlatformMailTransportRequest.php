<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlatformMailTransportRequest extends FormRequest
{
    /**
     * @var array<int, string>
     */
    private const Providers = ['smtp', 'mailgun', 'postmark', 'ses', 'resend'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_platform_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $provider = (string) $this->input('provider', 'smtp');
        $secretRequired = ! $this->hasReusableExistingSecret($provider);

        return [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', Rule::in(self::Providers)],
            'from_name' => ['required', 'string', 'max:255'],
            'from_email' => ['required', 'email', 'max:255'],
            'reply_to_email' => ['nullable', 'email', 'max:255'],
            'host' => [Rule::requiredIf($provider === 'smtp'), 'nullable', 'string', 'max:255'],
            'port' => [Rule::requiredIf($provider === 'smtp'), 'nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', 'string', Rule::in(['tls', 'ssl'])],
            'username' => ['nullable', 'string', 'max:255'],
            'secret' => [Rule::requiredIf($secretRequired && $provider !== 'smtp'), 'nullable', 'string', 'max:1000'],
            'transport_has_secret' => ['nullable', 'boolean'],
            'current_provider' => ['nullable', 'string', Rule::in(self::Providers)],
            'provider_config' => ['nullable', 'array'],
            'provider_config.domain' => [Rule::requiredIf($provider === 'mailgun'), 'nullable', 'string', 'max:255'],
            'provider_config.endpoint' => ['nullable', 'string', 'max:255'],
            'provider_config.region' => [Rule::requiredIf($provider === 'ses'), 'nullable', 'string', 'max:255'],
            'provider_config.message_stream_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('admin_common_ui.platform.mail.validation.required'),
            'from_name.required' => __('admin_common_ui.platform.mail.validation.required'),
            'from_email.required' => __('admin_common_ui.platform.mail.validation.required'),
            'from_email.email' => __('admin_common_ui.platform.mail.validation.email'),
            'reply_to_email.email' => __('admin_common_ui.platform.mail.validation.email'),
            'host.required' => __('admin_common_ui.platform.mail.validation.required'),
            'port.required' => __('admin_common_ui.platform.mail.validation.required'),
            'port.integer' => __('admin_common_ui.platform.mail.validation.port'),
            'port.max' => __('admin_common_ui.platform.mail.validation.port'),
            'port.min' => __('admin_common_ui.platform.mail.validation.port'),
            'secret.required' => __('admin_common_ui.platform.mail.validation.required'),
            'provider_config.domain.required' => __('admin_common_ui.platform.mail.validation.required'),
            'provider_config.region.required' => __('admin_common_ui.platform.mail.validation.required'),
        ];
    }

    private function hasReusableExistingSecret(string $provider): bool
    {
        return $this->boolean('transport_has_secret') && (string) $this->input('current_provider') === $provider;
    }
}
