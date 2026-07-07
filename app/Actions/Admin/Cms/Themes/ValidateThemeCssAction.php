<?php

namespace App\Actions\Admin\Cms\Themes;

use Illuminate\Support\Str;

class ValidateThemeCssAction
{
    /**
     * @return array{valid: bool, errors: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>, external_assets: array<int, string>}
     */
    public function handle(string $css): array
    {
        $errors = [];
        $warnings = [];
        $externalAssets = [];

        foreach (preg_split('/\R/', $css) ?: [] as $index => $line) {
            $lineNumber = $index + 1;

            if (! (bool) config('cms_themes.css.allow_imports', false) && preg_match('/@import\b/i', $line)) {
                $errors[] = $this->issue($lineNumber, 'css_import_not_allowed', '@import is niet toegestaan. Gebruik alleen url(...) voor fonts of afbeeldingen.', trim($line));
            }

            foreach ($this->urlsFromLine($line) as $url) {
                $this->validateUrl($url, $lineNumber, $errors, $warnings, $externalAssets);
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'external_assets' => array_values(array_unique($externalAssets)),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function urlsFromLine(string $line): array
    {
        if (! preg_match_all('/url\(\s*([\'\"]?)(.*?)\1\s*\)/i', $line, $matches)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $value): string => trim($value),
            $matches[2] ?? []
        )));
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<int, array<string, mixed>>  $warnings
     * @param  array<int, string>  $externalAssets
     */
    private function validateUrl(string $url, int $lineNumber, array &$errors, array &$warnings, array &$externalAssets): void
    {
        $lowerUrl = Str::lower($url);

        foreach ((array) config('cms_themes.css.blocked_schemes', []) as $scheme) {
            if (Str::startsWith($lowerUrl, Str::lower((string) $scheme).':')) {
                $errors[] = $this->issue($lineNumber, 'blocked_url_scheme', 'URL scheme "'.$scheme.':" is niet toegestaan in theme CSS.', $url);

                return;
            }
        }

        if (Str::startsWith($lowerUrl, 'http://')) {
            $errors[] = $this->issue($lineNumber, 'insecure_external_asset', 'Externe assets moeten via https:// geladen worden.', $url);

            return;
        }

        if (! Str::startsWith($lowerUrl, 'https://')) {
            return;
        }

        if (! (bool) config('cms_themes.css.allow_external_assets', true)) {
            $errors[] = $this->issue($lineNumber, 'external_assets_not_allowed', 'Externe assets zijn niet toegestaan voor themes.', $url);

            return;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        if (! is_string($host) || $host === '' || ! $this->hostIsAllowed($host)) {
            $errors[] = $this->issue($lineNumber, 'external_host_not_allowed', 'Extern domein is niet toegestaan voor theme assets: '.($host ?: $url), $url);

            return;
        }

        if ($extension === '' || ! in_array($extension, (array) config('cms_themes.css.allowed_asset_extensions', []), true)) {
            $errors[] = $this->issue($lineNumber, 'asset_extension_not_allowed', 'Dit asset type is niet toegestaan. Toegelaten extensies: '.implode(', ', (array) config('cms_themes.css.allowed_asset_extensions', [])).'.', $url);

            return;
        }

        $externalAssets[] = $url;
        $warnings[] = $this->issue($lineNumber, 'external_asset_used', 'Dit theme gebruikt een externe asset. Controleer privacy en beschikbaarheid.', $url);
    }

    private function hostIsAllowed(string $host): bool
    {
        foreach ((array) config('cms_themes.css.allowed_external_hosts', []) as $allowedHost) {
            $allowedHost = Str::lower(trim((string) $allowedHost));
            $host = Str::lower($host);

            if ($allowedHost !== '' && ($host === $allowedHost || Str::endsWith($host, '.'.$allowedHost))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{line: int, rule: string, message: string, value: string}
     */
    private function issue(int $line, string $rule, string $message, string $value): array
    {
        return [
            'line' => $line,
            'rule' => $rule,
            'message' => $message,
            'value' => $value,
        ];
    }
}
