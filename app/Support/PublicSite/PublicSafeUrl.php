<?php

namespace App\Support\PublicSite;

class PublicSafeUrl
{
    public function handle(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        if (preg_match('/^https?:\/\//i', $url) === 1) {
            return $url;
        }

        return null;
    }
}
