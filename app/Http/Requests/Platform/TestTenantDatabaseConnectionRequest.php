<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TestTenantDatabaseConnectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_storage_option' => ['required', 'string', Rule::in(['existing_database'])],
            'tenant_database' => ['required', 'string', 'max:160', 'regex:/^[A-Za-z0-9_]+$/'],
            'tenant_database_url' => ['nullable', 'string', 'max:2048'],
            'tenant_database_host' => ['nullable', 'string', 'max:255'],
            'tenant_database_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'tenant_database_username' => ['nullable', 'string', 'max:160'],
            'tenant_database_password' => ['nullable', 'string', 'max:1024'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $tenantDatabaseUrl = (string) $this->input('tenant_database_url', '');
                $tenantDatabaseHost = (string) $this->input('tenant_database_host', '');
                $tenantDatabaseUsername = (string) $this->input('tenant_database_username', '');

                if ($tenantDatabaseUrl === ''
                    && ($tenantDatabaseHost !== '' || $tenantDatabaseUsername !== '')
                    && ($tenantDatabaseHost === '' || $tenantDatabaseUsername === '')) {
                    $validator->errors()->add('tenant_database_host', __('admin_common_ui.errors.tenant_database_connection_incomplete'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $tenantDatabasePort = trim((string) $this->input('tenant_database_port'));

        $this->merge([
            'tenant_storage_option' => $this->input('tenant_storage_option') ?: 'existing_database',
            'tenant_database' => trim((string) $this->input('tenant_database')),
            'tenant_database_url' => trim((string) $this->input('tenant_database_url')),
            'tenant_database_host' => strtolower(trim((string) $this->input('tenant_database_host'))),
            'tenant_database_port' => $tenantDatabasePort !== '' ? $tenantDatabasePort : null,
            'tenant_database_username' => trim((string) $this->input('tenant_database_username')),
            'tenant_database_password' => (string) $this->input('tenant_database_password'),
        ]);
    }
}
