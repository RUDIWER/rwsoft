<?php

namespace App\Support\Cms;

use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CmsStyleTokenOptions
{
    /**
     * @return array<string, array<int, array{value: string, label: string, css_value?: string}>>
     */
    public function all(): array
    {
        return [
            'fontFamily' => $this->fontFamilyTokenOptions(),
            'fontSize' => $this->tokenOptions('font_size_tokens'),
            'fontWeight' => $this->tokenOptions('font_weight_tokens'),
            'typographyPreset' => $this->tokenOptions('typography_preset_tokens'),
            'color' => $this->tokenOptions('color_tokens'),
        ];
    }

    public function activeFontFaceCss(): ?string
    {
        $css = $this->activeThemeCss();

        if ($css === null || ! preg_match_all('/@font-face\s*\{[^}]*}/i', $css, $matches)) {
            return null;
        }

        $fontFaceCss = collect($matches[0])
            ->filter(fn (string $block): bool => strlen($block) <= 5000 && ! preg_match('/[<>]|javascript:/i', $block))
            ->values()
            ->implode("\n");

        return $fontFaceCss !== '' ? $fontFaceCss : null;
    }

    /**
     * @return array<int, array{value: string, label: string, css_value?: string}>
     */
    private function fontFamilyTokenOptions(): array
    {
        $resolvedFonts = $this->resolvedFontFamilies();

        return collect($this->tokenOptions('font_family_tokens'))
            ->map(function (array $option) use ($resolvedFonts): array {
                $cssValue = $resolvedFonts[$option['value']] ?? null;

                if (is_string($cssValue) && $cssValue !== '') {
                    $option['css_value'] = $cssValue;
                }

                return $option;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function tokenOptions(string $configKey): array
    {
        return collect((array) config("cms_themes.{$configKey}", []))
            ->filter(fn (mixed $label, string $value): bool => preg_match('/^[a-z0-9_-]+$/', $value) === 1)
            ->map(fn (mixed $label, string $value): array => [
                'value' => $value,
                'label' => is_string($label) && $label !== '' ? $label : Str::headline($value),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function resolvedFontFamilies(): array
    {
        $valuesByVariable = $this->fontValuesByVariable();
        $resolved = [
            'inherit' => 'inherit',
        ];

        foreach ($this->fontVariableByToken() as $token => $variable) {
            $resolved[$token] = $this->resolveCssFontValue(
                $valuesByVariable[$variable] ?? null,
                $valuesByVariable,
            ) ?? 'inherit';
        }

        return $resolved;
    }

    /**
     * @return array<string, string>
     */
    private function fontValuesByVariable(): array
    {
        $values = $this->defaultFontValuesByVariable();

        foreach ($this->activeThemeFontValuesByVariable() as $variable => $value) {
            $values[$variable] = $value;
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    private function defaultFontValuesByVariable(): array
    {
        $values = [];

        foreach ($this->settingsFields() as $field) {
            if (($field['type'] ?? null) !== 'text' || empty($field['css_variable'])) {
                continue;
            }

            $variable = (string) $field['css_variable'];

            if (! str_starts_with($variable, '--rw-public-font-')) {
                continue;
            }

            $value = $this->sanitizeFontValue($field['default'] ?? null);

            if ($value !== null) {
                $values[$variable] = $value;
            }
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    private function activeThemeFontValuesByVariable(): array
    {
        $css = $this->activeThemeCss();

        if ($css === null || ! preg_match_all('/(--rw-public-font-[a-z0-9_-]+)\s*:\s*([^;}]+)/i', $css, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $values = [];

        foreach ($matches as $match) {
            $value = $this->sanitizeFontValue($match[2] ?? null);

            if ($value !== null) {
                $values[$match[1]] = $value;
            }
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    private function fontVariableByToken(): array
    {
        return [
            'body' => '--rw-public-font-body',
            'heading' => '--rw-public-font-heading',
            'brand' => '--rw-public-font-brand',
            'accent' => '--rw-public-font-accent',
        ];
    }

    /**
     * @param  array<string, string>  $valuesByVariable
     */
    private function resolveCssFontValue(?string $value, array $valuesByVariable, int $depth = 0): ?string
    {
        $value = $this->sanitizeFontValue($value);

        if ($value === null) {
            return null;
        }

        if ($depth > 5 || ! preg_match('/^var\((--[a-z0-9_-]+)(?:,\s*(.+))?\)$/i', $value, $matches)) {
            return $value;
        }

        $variable = $matches[1];
        $fallback = isset($matches[2]) ? trim($matches[2]) : null;

        return $this->resolveCssFontValue(
            $valuesByVariable[$variable] ?? $fallback,
            $valuesByVariable,
            $depth + 1,
        );
    }

    private function sanitizeFontValue(mixed $value): ?string
    {
        if (! is_scalar($value) || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || strlen($value) > 160 || preg_match('/[{};<>]/', $value)) {
            return null;
        }

        return $value;
    }

    private function activeThemeCss(): ?string
    {
        if (! Schema::hasTable('cms_themes') || ! Schema::hasTable('cms_theme_versions')) {
            return null;
        }

        $theme = CmsTheme::query()
            ->with('activeVersion')
            ->where('is_active', true)
            ->first();

        if (! $theme instanceof CmsTheme || ! $theme->activeVersion instanceof CmsThemeVersion) {
            return null;
        }

        $path = (string) $theme->activeVersion->minified_css_path;

        if ($path === '') {
            return null;
        }

        $disk = Storage::disk((string) config('cms_themes.storage_disk', 'local'));

        return $disk->exists($path) ? (string) $disk->get($path) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function settingsFields(): array
    {
        return collect((array) config('cms_themes.settings_fields', []))
            ->filter(fn ($field): bool => is_array($field) && ! empty($field['key']))
            ->values()
            ->all();
    }
}
