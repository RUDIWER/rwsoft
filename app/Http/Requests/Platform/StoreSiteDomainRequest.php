<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteDomainRequest extends FormRequest
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
        return [
            'host' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9][a-z0-9\.\-]*[a-z0-9]$/',
                Rule::unique('central.site_domains', 'host'),
            ],
            'is_primary' => ['nullable', 'boolean'],
            'force_https' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'host' => $this->normalizeHost((string) $this->input('host')),
            'is_primary' => $this->boolean('is_primary'),
            'force_https' => $this->boolean('force_https', true),
        ]);
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = explode('/', $host, 2)[0];
        $host = explode(':', $host, 2)[0];

        return trim($host, '.');
    }
}
