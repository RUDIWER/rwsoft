<?php

namespace App\Support\PublicSite;

use Illuminate\Support\Str;

class CmsFormOptionNormalizer
{
    /**
     * @return array<int, array{key: string, label: string}>
     */
    public static function normalize(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        $usedKeys = [];

        return collect($options)
            ->map(function (mixed $option) use (&$usedKeys): ?array {
                $label = self::label($option);

                if ($label === '') {
                    return null;
                }

                $key = self::key($option, $label);

                if ($key === '') {
                    return null;
                }

                return [
                    'key' => self::uniqueKey($key, $usedKeys),
                    'label' => $label,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private static function label(mixed $option): string
    {
        if (is_array($option)) {
            return trim((string) ($option['label'] ?? $option['key'] ?? ''));
        }

        if (is_scalar($option)) {
            return trim((string) $option);
        }

        return '';
    }

    private static function key(mixed $option, string $label): string
    {
        $key = is_array($option)
            ? trim((string) ($option['key'] ?? ''))
            : $label;

        return Str::slug($key) ?: Str::slug($label);
    }

    /**
     * @param  array<string, true>  $usedKeys
     */
    private static function uniqueKey(string $key, array &$usedKeys): string
    {
        $baseKey = $key;
        $counter = 2;

        while (isset($usedKeys[$key])) {
            $key = $baseKey.'-'.$counter;
            $counter++;
        }

        $usedKeys[$key] = true;

        return $key;
    }
}
