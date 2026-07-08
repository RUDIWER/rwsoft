<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSitePublicationRequest extends FormRequest
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
        $publicationId = (int) $this->route('id');
        $hostingEnvironmentId = (int) $this->input('hosting_environment_id');

        return [
            'site_id' => ['required', 'integer', 'exists:central.sites,id'],
            'hosting_environment_id' => ['required', 'integer', 'exists:central.platform_hosting_environments,id'],
            'remote_site_slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('central.platform_site_publications', 'remote_site_slug')
                    ->where('hosting_environment_id', $hostingEnvironmentId)
                    ->ignore($publicationId > 0 ? $publicationId : null),
            ],
            'remote_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9][a-z0-9\.\-]*[a-z0-9]$/',
            ],
            'remote_tenant_database_mode' => ['required', 'string', Rule::in(['shared_prefixed', 'separate', 'existing_database'])],
            'remote_tenant_database' => ['nullable', 'string', 'max:160', 'regex:/^[A-Za-z0-9_]+$/'],
            'remote_tenant_table_prefix' => ['nullable', 'string', 'max:48', 'regex:/^[a-z][a-z0-9_]*_$/'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $mode = (string) $this->input('remote_tenant_database_mode');
                $database = (string) $this->input('remote_tenant_database');
                $prefix = (string) $this->input('remote_tenant_table_prefix');

                if (in_array($mode, ['separate', 'existing_database'], true) && $database === '') {
                    $validator->errors()->add('remote_tenant_database', __('admin_common_ui.platform.publications.validation.remote_database_required'));
                }

                if ($mode === 'shared_prefixed' && $prefix === '') {
                    $validator->errors()->add('remote_tenant_table_prefix', __('admin_common_ui.platform.publications.validation.remote_prefix_required'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'site_id' => (int) $this->input('site_id'),
            'hosting_environment_id' => (int) $this->input('hosting_environment_id'),
            'remote_site_slug' => str($this->input('remote_site_slug'))->trim()->slug()->toString(),
            'remote_domain' => $this->normalizeHost((string) $this->input('remote_domain')),
            'remote_tenant_database_mode' => (string) ($this->input('remote_tenant_database_mode') ?: 'shared_prefixed'),
            'remote_tenant_database' => trim((string) $this->input('remote_tenant_database')),
            'remote_tenant_table_prefix' => strtolower(trim((string) $this->input('remote_tenant_table_prefix'))),
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
