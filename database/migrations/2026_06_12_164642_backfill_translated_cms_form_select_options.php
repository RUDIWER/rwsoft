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
            ->where('type', 'select')
            ->orderBy('id')
            ->select(['id', 'translation_key', 'translated_from_form_field_id', 'options'])
            ->get()
            ->each(function (object $field): void {
                if (! $this->optionsAreEmpty($field->options)) {
                    return;
                }

                $sourceOptions = $this->sourceOptions($field);

                if ($sourceOptions === []) {
                    return;
                }

                DB::table('cms_form_fields')
                    ->where('id', $field->id)
                    ->update(['options' => json_encode($sourceOptions)]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible data backfill: translated select options should keep stable source keys.
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function sourceOptions(object $field): array
    {
        $sourceOptions = null;

        if ($field->translated_from_form_field_id !== null) {
            $sourceOptions = DB::table('cms_form_fields')
                ->where('id', $field->translated_from_form_field_id)
                ->value('options');
        }

        if ($sourceOptions === null && filled($field->translation_key)) {
            $sourceOptions = DB::table('cms_form_fields')
                ->where('translation_key', $field->translation_key)
                ->where('id', '!=', $field->id)
                ->whereNotNull('options')
                ->orderBy('id')
                ->pluck('options')
                ->first(fn (mixed $options): bool => ! $this->optionsAreEmpty($options));
        }

        $decodedOptions = json_decode((string) $sourceOptions, true);

        if (! is_array($decodedOptions)) {
            return [];
        }

        return $this->normalizeOptions($decodedOptions);
    }

    private function optionsAreEmpty(mixed $options): bool
    {
        if ($options === null) {
            return true;
        }

        $decodedOptions = json_decode((string) $options, true);

        return ! is_array($decodedOptions) || $decodedOptions === [];
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
