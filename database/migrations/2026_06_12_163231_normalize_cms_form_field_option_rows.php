<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('cms_form_fields')
            ->whereNotNull('options')
            ->select(['id', 'options'])
            ->lazyById(100, 'id')
            ->each(function (object $field): void {
                $options = json_decode((string) $field->options, true);

                if (! is_array($options)) {
                    return;
                }

                $normalizedOptions = $this->normalizeOptions($options);

                if ($normalizedOptions === $options) {
                    return;
                }

                DB::table('cms_form_fields')
                    ->where('id', $field->id)
                    ->update(['options' => json_encode($normalizedOptions)]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible data cleanup: option keys are the intended storage format.
    }

    /**
     * @param  array<int, mixed>  $options
     * @return array<int, array{key: string, label: string}>
     */
    private function normalizeOptions(array $options): array
    {
        $usedKeys = [];

        return collect($options)
            ->map(function (mixed $option) use (&$usedKeys): ?array {
                $label = $this->optionLabel($option);

                if ($label === '') {
                    return null;
                }

                $key = $this->optionKey($option, $label);

                if ($key === '') {
                    return null;
                }

                return [
                    'key' => $this->uniqueOptionKey($key, $usedKeys),
                    'label' => $label,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function optionLabel(mixed $option): string
    {
        if (is_array($option)) {
            return trim((string) ($option['label'] ?? $option['key'] ?? ''));
        }

        if (is_scalar($option)) {
            return trim((string) $option);
        }

        return '';
    }

    private function optionKey(mixed $option, string $label): string
    {
        $key = is_array($option)
            ? trim((string) ($option['key'] ?? ''))
            : $label;

        return Str::slug($key) ?: Str::slug($label);
    }

    /**
     * @param  array<string, true>  $usedKeys
     */
    private function uniqueOptionKey(string $key, array &$usedKeys): string
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
};
