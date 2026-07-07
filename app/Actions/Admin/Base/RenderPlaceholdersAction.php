<?php

namespace App\Actions\Admin\Base;

class RenderPlaceholdersAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function handle(string $template, string $context, array $data): string
    {
        return app(self::class)->render($template, $context, $data);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public static function placeholders(string $context): array
    {
        $config = app(self::class)->contextConfig($context);
        $allowed = $config['allowed'] ?? [];
        $labels = $config['labels'] ?? [];

        return collect($allowed)
            ->map(fn (string $key): array => [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function unknownPlaceholders(string $template, string $context): array
    {
        preg_match_all('/{{\s*([A-Za-z0-9_.-]+)\s*}}/', $template, $matches);

        $allowed = app(self::class)->contextConfig($context)['allowed'] ?? [];

        return collect($matches[1] ?? [])
            ->unique()
            ->reject(fn (string $placeholder): bool => in_array($placeholder, $allowed, true))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $template, string $context, array $data): string
    {
        $allowed = $this->contextConfig($context)['allowed'] ?? [];
        $preserveUnknown = (bool) config('text_placeholders.preserve_unknown_placeholders', true);
        $preserveUnauthorized = (bool) config('text_placeholders.preserve_unauthorized_placeholders', true);

        return preg_replace_callback(
            '/{{\s*([A-Za-z0-9_.-]+)\s*}}/',
            function (array $matches) use ($allowed, $data, $preserveUnknown, $preserveUnauthorized): string {
                $path = $matches[1];

                if (! in_array($path, $allowed, true)) {
                    return $preserveUnauthorized ? $matches[0] : '';
                }

                $value = $this->valueForPath($data, $path);

                if ($value === null) {
                    return $preserveUnknown ? $matches[0] : '';
                }

                if (is_array($value)) {
                    return implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value));
                }

                return (string) $value;
            },
            $template
        ) ?? $template;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function valueForPath(array $data, string $path): mixed
    {
        $current = $data;

        foreach (explode('.', $path) as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];

                continue;
            }

            return null;
        }

        return $current;
    }

    /**
     * @return array<string, mixed>
     */
    private function contextConfig(string $context): array
    {
        $config = config("text_placeholders.contexts.{$context}");

        if (is_array($config)) {
            return $config;
        }

        $path = config_path('text_placeholders.php');

        if (! is_file($path)) {
            return [];
        }

        $fallback = require $path;

        return is_array($fallback) ? ($fallback['contexts'][$context] ?? []) : [];
    }
}
