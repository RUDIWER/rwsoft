<?php

namespace App\Support\PublicSite;

use Illuminate\Support\Facades\Storage;

class PublicStorageUrl
{
    public function url(mixed $path, string $disk = 'public'): ?string
    {
        $path = $this->normalizePath($path);

        if ($path === null) {
            return null;
        }

        $disk = trim($disk) ?: 'public';

        if ($this->usesCurrentHost($disk)) {
            return url('/storage/'.$path);
        }

        return Storage::disk($disk)->url($path);
    }

    public function versionedUrl(mixed $path, mixed $version, string $disk = 'public'): ?string
    {
        $url = $this->url($path, $disk);

        if ($url === null) {
            return null;
        }

        return is_numeric($version)
            ? $url.(str_contains($url, '?') ? '&' : '?').'v='.(int) $version
            : $url;
    }

    private function normalizePath(mixed $path): ?string
    {
        $path = trim(str_replace('\\', '/', (string) $path));
        $path = ltrim($path, '/');

        if ($path === '') {
            return null;
        }

        if (in_array('..', explode('/', $path), true)) {
            return null;
        }

        return $path;
    }

    private function usesCurrentHost(string $disk): bool
    {
        return $disk === 'public'
            && (string) config('filesystems.disks.public.driver') === 'local';
    }
}
