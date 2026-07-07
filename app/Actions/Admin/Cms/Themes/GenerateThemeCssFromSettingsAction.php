<?php

namespace App\Actions\Admin\Cms\Themes;

use Illuminate\Support\Arr;

class GenerateThemeCssFromSettingsAction
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function handle(array $settings = []): string
    {
        $fields = collect((array) config('cms_themes.settings_fields', []));
        $rootLines = [];
        $selectorRules = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = (string) ($field['key'] ?? '');
            $value = $this->sanitizeValue($settings[$key] ?? null, $field);

            if ($key === '' || $value === null) {
                continue;
            }

            if (! empty($field['css_variable'])) {
                $rootLines[] = '    '.$field['css_variable'].': '.$value.';';
            }

            if (! empty($field['selector']) && ! empty($field['property'])) {
                $selector = (string) $field['selector'];
                $property = (string) $field['property'];
                $selectorRules[$selector][] = '    '.$property.': '.$value.';';
            }
        }

        if ($rootLines === [] && $selectorRules === []) {
            return "/* Admin basisinstellingen zijn nog niet geconfigureerd. */\n";
        }

        $lines = [];

        if ($rootLines !== []) {
            $lines[] = ':root {';
            array_push($lines, ...$rootLines);
            $lines[] = '}';
        }

        foreach ($selectorRules as $selector => $rules) {
            $lines[] = $selector.' {';
            array_push($lines, ...$rules);
            $lines[] = '}';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function sanitizeValue(mixed $value, array $field): ?string
    {
        if (! is_scalar($value) || $value === '') {
            return null;
        }

        $value = trim((string) $value);
        $type = (string) Arr::get($field, 'type', 'text');

        if ($type === 'color') {
            return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : null;
        }

        if (strlen($value) > 160) {
            return null;
        }

        if (preg_match('/[{};<>]/', $value)) {
            return null;
        }

        return $value;
    }
}
