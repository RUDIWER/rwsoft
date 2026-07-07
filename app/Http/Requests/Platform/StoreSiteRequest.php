<?php

namespace App\Http\Requests\Platform;

use App\Models\Platform\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSiteRequest extends FormRequest
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
        $siteId = (int) $this->route('id');

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('central.sites', 'slug')->ignore($siteId > 0 ? $siteId : null),
            ],
            'primary_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9][a-z0-9\.\-]*[a-z0-9]$/',
                Rule::unique('central.site_domains', 'host'),
            ],
            'first_admin_email' => ['nullable', 'string', 'email', 'exists:central.users,email'],
            'tenant_storage_option' => ['nullable', 'string', Rule::in(['create_database', 'existing_database', 'shared_prefixed'])],
            'tenant_database' => ['nullable', 'string', 'max:160', 'regex:/^[A-Za-z0-9_]+$/'],
            'tenant_table_prefix' => ['nullable', 'string', 'max:48', 'regex:'.config('tenancy.table_prefix_pattern')],
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
                $siteId = (int) $this->route('id');

                if ($siteId > 0) {
                    return;
                }

                $storageOption = (string) $this->input('tenant_storage_option', 'create_database');
                $tenantDatabase = (string) $this->input('tenant_database', '');
                $tenantTablePrefix = (string) $this->input('tenant_table_prefix', '');
                $tenantDatabaseUrl = (string) $this->input('tenant_database_url', '');
                $tenantDatabaseHost = (string) $this->input('tenant_database_host', '');
                $tenantDatabaseUsername = (string) $this->input('tenant_database_username', '');

                if ($storageOption === 'existing_database' && $tenantDatabase === '') {
                    $validator->errors()->add('tenant_database', __('admin_common_ui.errors.tenant_database_required'));
                }

                if ($storageOption === 'existing_database'
                    && $tenantDatabaseUrl === ''
                    && ($tenantDatabaseHost !== '' || $tenantDatabaseUsername !== '')
                    && ($tenantDatabaseHost === '' || $tenantDatabaseUsername === '')) {
                    $validator->errors()->add('tenant_database_host', __('admin_common_ui.errors.tenant_database_connection_incomplete'));
                }

                if (in_array($storageOption, ['create_database', 'existing_database'], true)
                    && $tenantDatabase !== ''
                    && $tenantDatabase === (string) config('database.connections.central.database')) {
                    $validator->errors()->add('tenant_database', __('admin_common_ui.errors.tenant_database_must_not_be_central'));
                }

                if (in_array($storageOption, ['create_database', 'existing_database'], true)
                    && $tenantDatabase !== ''
                    && Site::query()->where('tenant_database', $tenantDatabase)->exists()) {
                    $validator->errors()->add('tenant_database', __('admin_common_ui.errors.tenant_database_already_used'));
                }

                if ($storageOption === 'shared_prefixed'
                    && $tenantTablePrefix !== ''
                    && Site::query()->where('tenant_table_prefix', $tenantTablePrefix)->exists()) {
                    $validator->errors()->add('tenant_table_prefix', __('admin_common_ui.errors.tenant_table_prefix_already_used'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $slug = trim((string) $this->input('slug'));
        $tenantDatabasePort = trim((string) $this->input('tenant_database_port'));

        $this->merge([
            'name' => $name,
            'slug' => $slug !== '' ? Str::slug($slug) : Str::slug($name),
            'primary_domain' => $this->normalizeHost((string) $this->input('primary_domain')),
            'first_admin_email' => strtolower(trim((string) $this->input('first_admin_email'))),
            'tenant_storage_option' => $this->input('tenant_storage_option') ?: 'create_database',
            'tenant_database' => trim((string) $this->input('tenant_database')),
            'tenant_table_prefix' => strtolower(trim((string) $this->input('tenant_table_prefix'))),
            'tenant_database_url' => trim((string) $this->input('tenant_database_url')),
            'tenant_database_host' => strtolower(trim((string) $this->input('tenant_database_host'))),
            'tenant_database_port' => $tenantDatabasePort !== '' ? $tenantDatabasePort : null,
            'tenant_database_username' => trim((string) $this->input('tenant_database_username')),
            'tenant_database_password' => (string) $this->input('tenant_database_password'),
        ]);
    }

    private function normalizeHost(string $host): ?string
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return null;
        }

        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = explode('/', $host, 2)[0];
        $host = explode(':', $host, 2)[0];

        return trim($host, '.');
    }
}
