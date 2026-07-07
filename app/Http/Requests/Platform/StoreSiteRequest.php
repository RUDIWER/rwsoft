<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $slug = trim((string) $this->input('slug'));

        $this->merge([
            'name' => $name,
            'slug' => $slug !== '' ? Str::slug($slug) : Str::slug($name),
            'primary_domain' => $this->normalizeHost((string) $this->input('primary_domain')),
            'first_admin_email' => strtolower(trim((string) $this->input('first_admin_email'))),
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
