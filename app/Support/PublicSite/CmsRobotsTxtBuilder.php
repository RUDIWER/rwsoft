<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsSetting;

class CmsRobotsTxtBuilder
{
    /**
     * @return array<int, string>
     */
    public function defaultDisallowPaths(): array
    {
        return [
            '/admin',
            '/dashboard',
            '/login',
            '/register',
            '/password',
            '/two-factor-challenge',
            '/auth',
        ];
    }

    public function build(?string $extraRules = null, ?bool $globalNoindex = null): string
    {
        $globalNoindex = $globalNoindex ?? $this->globalNoindex();
        $lines = ['User-agent: *'];

        if ($globalNoindex) {
            $lines[] = 'Disallow: /';
            $lines[] = '';
            $lines[] = 'Sitemap: '.url('/sitemap.xml');

            return $this->normalizeOutput($lines);
        }

        foreach ($this->defaultDisallowPaths() as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $extraRules = $extraRules ?? $this->extraRules();

        if (is_string($extraRules) && trim($extraRules) !== '') {
            $lines[] = '';
            $lines[] = '# Extra regels uit CMS';

            foreach (preg_split('/\R/u', $extraRules) ?: [] as $line) {
                $lines[] = rtrim($line);
            }
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.url('/sitemap.xml');

        return $this->normalizeOutput($lines);
    }

    private function globalNoindex(): bool
    {
        return (bool) $this->settingValue('seo', 'global_noindex', false);
    }

    private function extraRules(): ?string
    {
        $value = $this->settingValue('seo', 'robots_extra_rules');

        return is_string($value) ? $value : null;
    }

    private function settingValue(string $group, string $key, mixed $default = null): mixed
    {
        $setting = CmsSetting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if (! $setting instanceof CmsSetting) {
            return $default;
        }

        return $setting->value['value'] ?? $default;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function normalizeOutput(array $lines): string
    {
        $normalized = [];
        $previousBlank = false;
        $seenSitemaps = [];

        foreach ($lines as $line) {
            $line = rtrim($line);
            $isBlank = trim($line) === '';

            if ($isBlank && $previousBlank) {
                continue;
            }

            if (str_starts_with(strtolower(trim($line)), 'sitemap:')) {
                $key = strtolower(trim($line));

                if (isset($seenSitemaps[$key])) {
                    continue;
                }

                $seenSitemaps[$key] = true;
            }

            $normalized[] = $line;
            $previousBlank = $isBlank;
        }

        return rtrim(implode("\n", $normalized))."\n";
    }
}
