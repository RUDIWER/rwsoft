<?php

namespace App\Http\Requests\Platform;

use App\Models\Platform\HostingConnection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreHostingConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $connectionId = (int) $this->route('id');

        return [
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('central.platform_hosting_connections', 'name')->ignore($connectionId > 0 ? $connectionId : null),
            ],
            'provider' => ['required', 'string', Rule::in(['laravel_cloud'])],
            'api_base_url' => ['nullable', 'url', 'max:255'],
            'api_token' => ['nullable', 'string', 'max:4096'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $connectionId = (int) $this->route('id');
                $apiToken = trim((string) $this->input('api_token'));

                if ($connectionId > 0) {
                    $connection = HostingConnection::query()->find($connectionId);

                    if ($connection instanceof HostingConnection && ($connection->hasApiToken() || $apiToken !== '')) {
                        return;
                    }
                }

                if ($apiToken === '') {
                    $validator->errors()->add('api_token', __('admin_common_ui.platform.hosting.validation.api_token_required'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $apiBaseUrl = trim((string) $this->input('api_base_url'));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'provider' => trim((string) ($this->input('provider') ?: 'laravel_cloud')),
            'api_base_url' => $apiBaseUrl !== '' ? rtrim($apiBaseUrl, '/') : null,
            'api_token' => trim((string) $this->input('api_token')),
        ]);
    }
}
