<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsPublicText;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsPublicTextCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncPublicTextKeysAction
{
    public function __construct(
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsPublicTextCache $publicTextCache,
    ) {}

    /**
     * @return array{keys_found:int,texts_created:int,translations_created:int,hardcoded_warnings:array<int, array<string, mixed>>,unused_warnings:array<int, string>,changed_default_warnings:array<int, array<string, string>>}
     */
    public function handle(bool $dryRun = false): array
    {
        if (! Schema::hasTable('cms_public_texts') || ! Schema::hasTable('cms_public_text_translations')) {
            return $this->emptyResult();
        }

        $definitions = $this->definitionsFromCode();
        $hardcodedWarnings = $this->hardcodedWarnings();
        $existingKeys = CmsPublicText::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get(['group', 'key', 'default_value'])
            ->mapWithKeys(fn (CmsPublicText $text): array => [
                $text->group.'.'.$text->key => (string) ($text->default_value ?? ''),
            ]);
        $usedKeys = collect($definitions)->keys();
        $unusedWarnings = $existingKeys
            ->keys()
            ->diff($usedKeys)
            ->values()
            ->all();
        $changedDefaultWarnings = collect($definitions)
            ->filter(fn (array $definition, string $path): bool => $existingKeys->has($path)
                && trim((string) $existingKeys->get($path)) !== trim((string) $definition['default_value']))
            ->map(fn (array $definition, string $path): array => [
                'key' => $path,
                'database_default' => (string) $existingKeys->get($path),
                'code_default' => (string) $definition['default_value'],
            ])
            ->values()
            ->all();
        $createdTexts = 0;
        $createdTranslations = 0;

        if ($dryRun) {
            [$createdTexts, $createdTranslations] = $this->missingCounts($definitions);
        }

        if (! $dryRun && $definitions !== []) {
            DB::transaction(function () use ($definitions, &$createdTexts, &$createdTranslations): void {
                foreach ($definitions as $path => $definition) {
                    $publicText = CmsPublicText::query()->firstOrNew([
                        'group' => (string) $definition['group'],
                        'key' => (string) $definition['key'],
                    ]);

                    if (! $publicText->exists) {
                        $createdTexts++;
                    }

                    $publicText->fill([
                        'label' => $publicText->label ?: $this->labelForKey($path),
                        'description' => $publicText->description ?: 'Public text: '.$path,
                        'default_value' => $publicText->exists && trim((string) $publicText->default_value) !== ''
                            ? $publicText->default_value
                            : (string) $definition['default_value'],
                        'type' => $publicText->type ?: $this->typeForValue((string) $definition['default_value']),
                        'is_system' => true,
                        'sort_order' => $publicText->sort_order ?: (int) $definition['sort_order'],
                    ]);
                    $publicText->save();

                    $createdTranslations += $this->ensureTranslations($publicText, (string) $definition['default_value']);
                }
            });

            $this->publicTextCache->flush();
        }

        return [
            'keys_found' => count($definitions),
            'texts_created' => $createdTexts,
            'translations_created' => $createdTranslations,
            'hardcoded_warnings' => $hardcodedWarnings,
            'unused_warnings' => $unusedWarnings,
            'changed_default_warnings' => $changedDefaultWarnings,
        ];
    }

    /**
     * @return array<string, array{group:string,key:string,default_value:string,sort_order:int}>
     */
    private function definitionsFromCode(): array
    {
        $definitions = [];
        $index = 0;

        foreach ($this->scanFiles() as $file) {
            $contents = File::get($file);
            preg_match_all(
                '/public_text\(\s*([\'\"])(?<key>[a-z0-9_.-]+)\1\s*,\s*([\'\"])(?<default>(?:\\\\.|(?!\3).)*)\3/s',
                $contents,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as $match) {
                $path = (string) ($match['key'] ?? '');

                if ($path === '' || isset($definitions[$path])) {
                    continue;
                }

                $segments = explode('.', $path, 2);
                $definitions[$path] = [
                    'group' => $segments[0],
                    'key' => $segments[1] ?? 'value',
                    'default_value' => stripcslashes((string) ($match['default'] ?? '')),
                    'sort_order' => (++$index) * 10,
                ];
            }
        }

        ksort($definitions);

        return $definitions;
    }

    /**
     * @return array<int, array{file:string,line:int,text:string}>
     */
    private function hardcodedWarnings(): array
    {
        $warnings = [];

        foreach ($this->scanFiles() as $file) {
            foreach (explode("\n", File::get($file)) as $index => $line) {
                $trimmed = trim($line);

                if ($trimmed === '' || str_contains($trimmed, 'public_text(')) {
                    continue;
                }

                if ($this->isLikelyHardcodedPublicText($file, $trimmed)) {
                    $warnings[] = [
                        'file' => str_replace(base_path().'/', '', $file),
                        'line' => $index + 1,
                        'text' => Str::limit($trimmed, 180, ''),
                    ];
                }
            }
        }

        return $warnings;
    }

    /**
     * @return array<int, string>
     */
    private function scanFiles(): array
    {
        return collect([
            resource_path('views/public/system'),
            resource_path('views/livewire/public-site'),
            app_path('Livewire/PublicSite'),
            app_path('Http/Controllers/PublicSite'),
            app_path('Support/PublicSite'),
        ])
            ->filter(fn (string $path): bool => File::exists($path))
            ->flatMap(fn (string $path) => File::allFiles($path))
            ->filter(fn ($file): bool => in_array($file->getExtension(), ['php'], true))
            ->map(fn ($file): string => $file->getPathname())
            ->values()
            ->all();
    }

    private function ensureTranslations(CmsPublicText $publicText, string $sourceValue): int
    {
        $created = 0;
        $sourceLocale = $this->sourceLocale();

        foreach ($this->languageSettings->activeLocales() as $locale) {
            $translation = $publicText->translations()->firstOrNew(['locale' => $locale]);

            if ($translation->exists) {
                continue;
            }

            $translation->fill([
                'value' => $locale === $sourceLocale ? $sourceValue : '',
            ]);
            $translation->save();
            $created++;
        }

        return $created;
    }

    /**
     * @param  array<string, array{group:string,key:string,default_value:string,sort_order:int}>  $definitions
     * @return array{0:int, 1:int}
     */
    private function missingCounts(array $definitions): array
    {
        $createdTexts = 0;
        $createdTranslations = 0;
        $activeLocales = $this->languageSettings->activeLocales();

        foreach ($definitions as $definition) {
            $publicText = CmsPublicText::query()
                ->with('translations:cms_public_text_id,locale')
                ->where('group', (string) $definition['group'])
                ->where('key', (string) $definition['key'])
                ->first();

            if (! $publicText instanceof CmsPublicText) {
                $createdTexts++;
                $createdTranslations += count($activeLocales);

                continue;
            }

            $existingLocales = $publicText->translations->pluck('locale')->all();
            $createdTranslations += count(array_diff($activeLocales, $existingLocales));
        }

        return [$createdTexts, $createdTranslations];
    }

    private function sourceLocale(): string
    {
        $configured = trim((string) config('translation_editor.source_locale', 'en'));

        if (in_array($configured, $this->languageSettings->activeLocales(), true)) {
            return $configured;
        }

        return $this->languageSettings->defaultLocale();
    }

    private function isLikelyHardcodedPublicText(string $file, string $line): bool
    {
        if (! str_ends_with($file, '.blade.php')) {
            return false;
        }

        if (str_starts_with($line, '@') || str_contains($line, '{{') || str_contains($line, '$')) {
            return false;
        }

        return preg_match('/>\s*[^<{@$][^<{]*[A-Za-zÀ-ÿ][^<{]*\s*</u', $line) === 1
            || preg_match('/\b(?:aria-label|placeholder)="(?![^\"]*{{)[^"]*[A-Za-zÀ-ÿ][^"]*"/u', $line) === 1;
    }

    private function labelForKey(string $path): string
    {
        return Str::headline(str_replace('.', ' ', $path));
    }

    private function typeForValue(string $value): string
    {
        return str_contains($value, "\n") || mb_strlen($value) > 160 ? 'textarea' : 'text';
    }

    /**
     * @return array{keys_found:int,texts_created:int,translations_created:int,hardcoded_warnings:array<int, array<string, mixed>>,unused_warnings:array<int, string>,changed_default_warnings:array<int, array<string, string>>}
     */
    private function emptyResult(): array
    {
        return [
            'keys_found' => 0,
            'texts_created' => 0,
            'translations_created' => 0,
            'hardcoded_warnings' => [],
            'unused_warnings' => [],
            'changed_default_warnings' => [],
        ];
    }
}
