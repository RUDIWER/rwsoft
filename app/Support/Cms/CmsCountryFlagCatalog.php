<?php

namespace App\Support\Cms;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CmsCountryFlagCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $catalog = $this->catalog();

        return collect((array) ($catalog['countries'] ?? []))
            ->filter(fn (mixed $country): bool => is_array($country) && $this->isValidCode($country['code'] ?? null))
            ->sortBy([
                fn (array $country): string => (string) ($country['continent'] ?? 'Other'),
                fn (array $country): string => (string) ($country['name'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $code): ?array
    {
        $code = $this->normalizeCode($code);

        if ($code === '') {
            return null;
        }

        return collect($this->all())->firstWhere('code', $code);
    }

    /**
     * @return array<string, mixed>
     */
    public function findOrFail(string $code): array
    {
        $country = $this->find($code);

        if (! is_array($country)) {
            throw new RuntimeException(__('cms_admin_ui.validation.country_flag_not_found'));
        }

        return $country;
    }

    public function exists(string $code): bool
    {
        return is_array($this->find($code));
    }

    public function svg(string $code): string
    {
        $country = $this->findOrFail($code);
        $path = (string) ($country['storage_path'] ?? '');

        if ($path === '' || ! $this->disk()->exists($path)) {
            throw new RuntimeException(__('cms_admin_ui.validation.country_flag_not_found'));
        }

        return (string) $this->disk()->get($path);
    }

    public function rootPath(): string
    {
        return trim((string) config('cms_country_flags.storage.root', 'system/countries'), '/');
    }

    public function catalogPath(): string
    {
        return $this->rootPath().'/'.trim((string) config('cms_country_flags.storage.catalog', 'catalog.json'), '/');
    }

    public function licensePath(): string
    {
        return $this->rootPath().'/'.trim((string) config('cms_country_flags.storage.license', 'LICENSE.flag-icons.txt'), '/');
    }

    public function countryStoragePath(string $continent, string $name, string $code): string
    {
        return $this->rootPath().'/'.$this->safeSegment($continent).'/'.$this->safeSegment($name).'/'.$this->normalizeCode($code).'.svg';
    }

    public function safeSegment(string $value): string
    {
        $value = trim($value);

        return $value !== '' ? str_replace(['/', '\\'], '-', $value) : 'Other';
    }

    public function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));

        return $this->isValidCode($code) ? $code : '';
    }

    public function isValidCode(mixed $code): bool
    {
        return is_string($code) && preg_match('/^[a-z0-9-]{2,16}$/', $code) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        if (! $this->disk()->exists($this->catalogPath())) {
            return [
                'countries' => [],
                'source' => Arr::only((array) config('cms_country_flags.source', []), ['name', 'version']),
            ];
        }

        $data = json_decode((string) $this->disk()->get($this->catalogPath()), true);

        return is_array($data) ? $data : ['countries' => []];
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('cms_country_flags.storage.disk', 'local'));
    }
}
