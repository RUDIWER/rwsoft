<?php

namespace App\Support\Translations;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class TranslationManager
{
    /**
     * @return array<int, string>
     */
    public function locales(): array
    {
        return collect((array) config('app.available_locales', [config('app.locale', 'en')]))
            ->map(static fn (mixed $locale): string => trim((string) $locale))
            ->filter(static fn (string $locale): bool => $locale !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    public function sources(): array
    {
        return collect($this->sourceConfig())
            ->map(fn (array $config, string $key): array => [
                'value' => $key,
                'label' => (string) ($config['label'] ?? $key),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(string $source = 'all'): array
    {
        $locales = $this->locales();
        $sources = $this->resolveSources($source);

        return collect($sources)
            ->flatMap(function (string $sourceKey) use ($locales): Collection {
                $rows = $this->rowsForSource($sourceKey, $locales);

                return collect($rows);
            })
            ->sortBy([
                ['source', 'asc'],
                ['key', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function updateByRowId(string $rowId, string $locale, string $value): array
    {
        $this->updateManyByRowIds($locale, [$rowId => $value]);

        $parsed = $this->parseRowId($rowId);
        $source = $parsed['source'];
        $key = $parsed['key'];

        return $this->singleRow($source, $key, $this->locales());
    }

    /**
     * @param  array<string, string>  $rowValues
     * @return array{requested:int,updated:int,sources:int}
     */
    public function updateManyByRowIds(string $locale, array $rowValues): array
    {
        $this->assertLocaleExists($locale);

        if ($rowValues === []) {
            return [
                'requested' => 0,
                'updated' => 0,
                'sources' => 0,
            ];
        }

        $updatesBySource = [];

        foreach ($rowValues as $rowId => $value) {
            $parsed = $this->parseRowId((string) $rowId);
            $source = $parsed['source'];
            $key = $parsed['key'];

            $this->assertSourceExists($source);
            $updatesBySource[$source][$key] = (string) $value;
        }

        $updated = 0;
        $updatedSources = 0;

        foreach ($updatesBySource as $source => $updatesByKey) {
            if (! is_array($updatesByKey) || $updatesByKey === []) {
                continue;
            }

            $flattenedTranslations = $this->flattenTranslations($this->read($source, $locale));
            $changed = false;

            foreach ($updatesByKey as $translationKey => $translationValue) {
                $currentValue = (string) ($flattenedTranslations[$translationKey] ?? '');

                if ($currentValue === $translationValue) {
                    continue;
                }

                $flattenedTranslations[$translationKey] = $translationValue;
                $changed = true;
                $updated++;
            }

            if (! $changed) {
                continue;
            }

            $this->write($source, $locale, $this->expand($flattenedTranslations));
            $updatedSources++;
        }

        return [
            'requested' => count($rowValues),
            'updated' => $updated,
            'sources' => $updatedSources,
        ];
    }

    /**
     * @param  array<int, string>  $targetLocales
     * @param  array<string, array<int, string>>  $skipKeysBySource
     * @return array{sources:int,updated_keys:int,updated_locales:int}
     */
    public function syncMissing(
        string $source = 'all',
        ?string $sourceLocale = null,
        array $targetLocales = [],
        array $skipKeysBySource = []
    ): array {
        $sourceLocale = trim((string) ($sourceLocale ?: config('translation_editor.source_locale', 'en')));
        $this->assertLocaleExists($sourceLocale);

        $allLocales = $this->locales();
        $targets = collect($targetLocales)
            ->map(static fn (mixed $locale): string => trim((string) $locale))
            ->filter(static fn (string $locale): bool => $locale !== '' && $locale !== $sourceLocale)
            ->unique()
            ->values();

        if ($targets->isEmpty()) {
            $targets = collect($allLocales)
                ->filter(static fn (string $locale): bool => $locale !== $sourceLocale)
                ->values();
        }

        foreach ($targets as $targetLocale) {
            $this->assertLocaleExists($targetLocale);
        }

        $updatedKeys = 0;
        $updatedLocales = 0;
        $sourceKeys = $this->resolveSources($source);

        foreach ($sourceKeys as $sourceKey) {
            $base = $this->flattenTranslations($this->read($sourceKey, $sourceLocale));
            $skipKeys = collect($skipKeysBySource[$sourceKey] ?? [])
                ->map(static fn (mixed $key): string => trim((string) $key))
                ->filter(static fn (string $key): bool => $key !== '')
                ->unique()
                ->flip();

            foreach ($targets as $targetLocale) {
                $target = $this->flattenTranslations($this->read($sourceKey, $targetLocale));
                $changed = false;

                foreach ($base as $translationKey => $translationValue) {
                    if ($skipKeys->has($translationKey)) {
                        continue;
                    }

                    $current = array_key_exists($translationKey, $target)
                        ? trim((string) $target[$translationKey])
                        : '';

                    if ($current !== '') {
                        continue;
                    }

                    $target[$translationKey] = (string) $translationValue;
                    $changed = true;
                    $updatedKeys++;
                }

                if (! $changed) {
                    continue;
                }

                $updatedLocales++;
                $this->write($sourceKey, $targetLocale, $this->expand($target));
            }
        }

        return [
            'sources' => count($sourceKeys),
            'updated_keys' => $updatedKeys,
            'updated_locales' => $updatedLocales,
        ];
    }

    /**
     * @param  array<string, string>  $keys
     * @return array{requested:int,created:int,created_keys:array<int, string>,source:string,locale:string}
     */
    public function mergeMissingSourceKeys(string $source, string $locale, array $keys): array
    {
        $locale = trim($locale);
        $this->assertLocaleExists($locale);
        $this->assertSourceExists($source);

        if ($keys === []) {
            return [
                'requested' => 0,
                'created' => 0,
                'created_keys' => [],
                'source' => $source,
                'locale' => $locale,
            ];
        }

        $flattenedTranslations = $this->flattenTranslations($this->read($source, $locale));
        $created = 0;
        $createdKeys = [];

        foreach ($keys as $translationKey => $translationValue) {
            $normalizedKey = trim((string) $translationKey);

            if ($normalizedKey === '' || array_key_exists($normalizedKey, $flattenedTranslations)) {
                continue;
            }

            $flattenedTranslations[$normalizedKey] = (string) $translationValue;
            $created++;
            $createdKeys[] = $normalizedKey;
        }

        if ($created > 0) {
            $this->write($source, $locale, $this->expand($flattenedTranslations));
        }

        return [
            'requested' => count($keys),
            'created' => $created,
            'created_keys' => $createdKeys,
            'source' => $source,
            'locale' => $locale,
        ];
    }

    /**
     * @return array{locale:string,registered:bool,sources:int}
     */
    public function addLocale(string $locale, ?string $sourceLocale = null): array
    {
        $locale = $this->normalizeLocaleCode($locale);

        if (! preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $locale)) {
            throw ValidationException::withMessages([
                'locale' => __('translation_editor_ui.errors.invalid_locale_format'),
            ]);
        }

        $resolvedSourceLocale = $sourceLocale === null
            ? null
            : $this->normalizeLocaleCode($sourceLocale);

        if ($resolvedSourceLocale !== null && $resolvedSourceLocale !== '') {
            $this->assertLocaleExists($resolvedSourceLocale);
        }

        $registered = $this->registerLocaleInConfig($locale);

        if (! $registered) {
            throw ValidationException::withMessages([
                'locale' => __('translation_editor_ui.errors.register_locale_failed'),
            ]);
        }

        $createdSources = 0;

        foreach (array_keys($this->sourceConfig()) as $sourceKey) {
            $targetPath = $this->pathFor($sourceKey, $locale);

            if (File::exists($targetPath)) {
                continue;
            }

            $base = $resolvedSourceLocale !== null && $resolvedSourceLocale !== ''
                ? $this->read($sourceKey, $resolvedSourceLocale)
                : [];
            $this->write($sourceKey, $locale, $base);
            $createdSources++;
        }

        return [
            'locale' => $locale,
            'registered' => $registered,
            'sources' => $createdSources,
        ];
    }

    /**
     * @return array{source:string,key:string}
     */
    public function parseRowId(string $rowId): array
    {
        $parts = explode('::', trim($rowId), 2);

        if (count($parts) !== 2) {
            throw ValidationException::withMessages([
                'row' => __('translation_editor_ui.errors.invalid_row'),
            ]);
        }

        $source = trim($parts[0]);
        $key = trim($parts[1]);

        if ($source === '' || $key === '') {
            throw ValidationException::withMessages([
                'row' => __('translation_editor_ui.errors.invalid_row'),
            ]);
        }

        return [
            'source' => $source,
            'key' => $key,
        ];
    }

    /**
     * @param  array<int, string>  $locales
     * @return array<int, array<string, mixed>>
     */
    private function rowsForSource(string $source, array $locales): array
    {
        $flattenedByLocale = [];
        $allKeys = collect();

        foreach ($locales as $locale) {
            $flat = $this->flattenTranslations($this->read($source, $locale));
            $flattenedByLocale[$locale] = $flat;
            $allKeys = $allKeys->merge(array_keys($flat));
        }

        return $allKeys
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $translationKey) use ($source, $locales, $flattenedByLocale): array {
                return $this->buildRow($source, $translationKey, $locales, $flattenedByLocale);
            })
            ->all();
    }

    /**
     * @param  array<int, string>  $locales
     * @return array<string, mixed>
     */
    private function singleRow(string $source, string $translationKey, array $locales): array
    {
        $flattenedByLocale = [];

        foreach ($locales as $locale) {
            $flattenedByLocale[$locale] = $this->flattenTranslations($this->read($source, $locale));
        }

        return $this->buildRow($source, $translationKey, $locales, $flattenedByLocale);
    }

    /**
     * @param  array<int, string>  $locales
     * @param  array<string, array<string, mixed>>  $flattenedByLocale
     * @return array<string, mixed>
     */
    private function buildRow(
        string $source,
        string $translationKey,
        array $locales,
        array $flattenedByLocale
    ): array {
        $missingLocales = [];
        $row = [
            'id' => $source.'::'.$translationKey,
            'source' => $source,
            'source_label' => (string) ($this->sourceConfig()[$source]['label'] ?? $source),
            'source_color' => $source === 'rwtable' ? 'blue' : 'teal',
            'key' => $translationKey,
        ];

        foreach ($locales as $locale) {
            $value = (string) ($flattenedByLocale[$locale][$translationKey] ?? '');
            $row['value_'.$locale] = $value;

            if (trim($value) === '') {
                $missingLocales[] = $locale;
            }
        }

        $row['missing_locales'] = $missingLocales;
        $row['missing_locales_display'] = $missingLocales === []
            ? __('translation_editor_ui.status.none')
            : implode(', ', $missingLocales);
        $row['missing_count'] = count($missingLocales);
        $row['status'] = $missingLocales === [] ? 'complete' : 'missing';
        $row['status_label'] = $missingLocales === []
            ? __('translation_editor_ui.status.complete')
            : __('translation_editor_ui.status.missing');
        $row['status_color'] = $missingLocales === [] ? 'green' : 'orange';

        return $row;
    }

    /**
     * @return array<int, string>
     */
    private function resolveSources(string $source): array
    {
        $normalizedSource = trim($source);
        $availableSources = array_keys($this->sourceConfig());

        if ($normalizedSource === '' || $normalizedSource === 'all') {
            return $availableSources;
        }

        if (! in_array($normalizedSource, $availableSources, true)) {
            throw ValidationException::withMessages([
                'source' => __('translation_editor_ui.errors.unknown_source'),
            ]);
        }

        return [$normalizedSource];
    }

    /**
     * @return array<string, array{label:string,path_template:string}>
     */
    private function sourceConfig(): array
    {
        $config = config('translation_editor.sources', []);

        if (! is_array($config) || $config === []) {
            return [
                'dynamic_prompts' => [
                    'label' => 'Dynamic prompts',
                    'path_template' => 'lang/{locale}/dynamic_prompts.php',
                ],
                'rwtable' => [
                    'label' => 'RWTable',
                    'path_template' => 'lang/vendor/rwtable/{locale}/rwtable.php',
                ],
            ];
        }

        return $config;
    }

    private function assertSourceExists(string $source): void
    {
        if (! array_key_exists($source, $this->sourceConfig())) {
            throw ValidationException::withMessages([
                'source' => __('translation_editor_ui.errors.unknown_source'),
            ]);
        }
    }

    private function assertLocaleExists(string $locale): void
    {
        if (! in_array($locale, $this->locales(), true)) {
            throw ValidationException::withMessages([
                'locale' => __('translation_editor_ui.errors.unknown_locale'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $source, string $locale): array
    {
        $this->assertSourceExists($source);
        $path = $this->pathFor($source, $locale);

        if (! File::exists($path)) {
            return [];
        }

        $content = include $path;

        return is_array($content) ? $content : [];
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, string>
     */
    private function flattenTranslations(array $translations): array
    {
        $flattened = [];

        foreach (Arr::dot($translations) as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $flattened[(string) $key] = $value === null ? '' : (string) $value;
        }

        return $flattened;
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    private function write(string $source, string $locale, array $translations): void
    {
        $path = $this->pathFor($source, $locale);
        File::ensureDirectoryExists(dirname($path));
        $this->backupFile($path);

        $export = var_export($this->sortRecursive($translations), true);
        $php = "<?php\n\nreturn {$export};\n";

        File::put($path, $php);

        clearstatcache(true, $path);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    private function pathFor(string $source, string $locale): string
    {
        $template = (string) ($this->sourceConfig()[$source]['path_template'] ?? '');

        if ($template === '') {
            throw ValidationException::withMessages([
                'source' => __('translation_editor_ui.errors.unconfigured_source'),
            ]);
        }

        $resolvedPath = str_replace('{locale}', $locale, $template);

        if (str_starts_with($resolvedPath, '/')) {
            return $resolvedPath;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $resolvedPath) === 1) {
            return $resolvedPath;
        }

        return base_path(ltrim($resolvedPath, '/'));
    }

    private function backupFile(string $path): void
    {
        if (! File::exists($path)) {
            return;
        }

        $backupDirectory = (string) config('translation_editor.backup_directory', storage_path('app/private/translation-backups'));
        File::ensureDirectoryExists($backupDirectory);

        $backupName = str_replace(['/', '\\'], '_', trim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR));
        $backupPath = $backupDirectory.'/'.now()->format('Ymd_His_u').'_'.$backupName;

        File::copy($path, $backupPath);
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed>
     */
    private function sortRecursive(array $translations): array
    {
        ksort($translations);

        foreach ($translations as $key => $value) {
            if (is_array($value)) {
                $translations[$key] = $this->sortRecursive($value);
            }
        }

        return $translations;
    }

    /**
     * @param  array<string, string>  $translations
     * @return array<string, mixed>
     */
    private function expand(array $translations): array
    {
        $expanded = [];

        foreach ($translations as $key => $value) {
            Arr::set($expanded, $key, $value);
        }

        return $expanded;
    }

    private function registerLocaleInConfig(string $locale): bool
    {
        $configuredPath = trim((string) config('translation_editor.app_config_path', ''));
        $configPath = $configuredPath !== '' ? $configuredPath : config_path('app.php');

        if (! File::exists($configPath)) {
            return false;
        }

        $content = File::get($configPath);

        if (! is_string($content) || $content === '') {
            return false;
        }

        if (! preg_match('/\'available_locales\'\s*=>\s*\[(?<locales>[^\]]*)\]/m', $content, $matches)) {
            return false;
        }

        $rawLocales = $matches['locales'] ?? '';
        preg_match_all('/\'([^\']+)\'/', $rawLocales, $localeMatches);
        $locales = collect($localeMatches[1] ?? [])
            ->map(static fn (string $entry): string => trim($entry))
            ->filter(static fn (string $entry): bool => $entry !== '')
            ->values();

        if ($locales->contains($locale)) {
            config(['app.available_locales' => $locales->all()]);

            return true;
        }

        $locales->push($locale);

        $replacement = "'available_locales' => [".$locales->map(static fn (string $entry): string => "'{$entry}'")->implode(', ').']';
        $updated = preg_replace('/\'available_locales\'\s*=>\s*\[[^\]]*\]/m', $replacement, $content, 1);

        if (! is_string($updated) || $updated === $content) {
            return false;
        }

        File::put($configPath, $updated);
        config(['app.available_locales' => $locales->all()]);

        return true;
    }

    private function normalizeLocaleCode(string $value): string
    {
        $normalized = trim(str_replace('-', '_', $value));

        if ($normalized === '') {
            return '';
        }

        $parts = explode('_', $normalized, 2);
        $languagePart = strtolower(trim((string) ($parts[0] ?? '')));
        $countryPart = strtoupper(trim((string) ($parts[1] ?? '')));

        if ($languagePart === '') {
            return '';
        }

        if ($countryPart === '') {
            return $languagePart;
        }

        return $languagePart.'_'.$countryPart;
    }
}
