<?php

namespace App\Console\Commands;

use App\Support\Translations\DynamicPromptFileManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('translations:dynamic-prompts-sync {--source= : Bronlocale (default: dynamic_prompts.default_locale)} {--targets=* : Doellocales, default alle beschikbare behalve source}')]
#[Description('Vul ontbrekende dynamic prompt keys aan in doellocales')]
class SyncDynamicPromptTranslationsCommand extends Command
{
    public function handle(DynamicPromptFileManager $fileManager): int
    {
        $sourceLocale = (string) ($this->option('source') ?: config('dynamic_prompts.default_locale', 'en'));
        $targets = $this->resolveTargets($sourceLocale);

        if ($targets === []) {
            $this->warn('Geen doellocales om te synchroniseren.');

            return self::SUCCESS;
        }

        $source = $fileManager->flatten($fileManager->read($sourceLocale));

        if ($source === []) {
            $this->warn(sprintf('Bronbestand voor locale %s bevat geen keys.', $sourceLocale));

            return self::SUCCESS;
        }

        foreach ($targets as $targetLocale) {
            $target = $fileManager->flatten($fileManager->read($targetLocale));
            $missingCount = 0;

            foreach ($source as $key => $value) {
                if (! array_key_exists($key, $target)) {
                    $target[$key] = (string) $value;
                    $missingCount++;
                }
            }

            $fileManager->write($targetLocale, $fileManager->expand($target));

            $this->info(sprintf(
                '[%s] %d keys toegevoegd, totaal %d keys.',
                $targetLocale,
                $missingCount,
                count($target)
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function resolveTargets(string $sourceLocale): array
    {
        $targets = collect((array) $this->option('targets'))
            ->map(static fn (mixed $locale): string => trim((string) $locale))
            ->filter(static fn (string $locale): bool => $locale !== '')
            ->values();

        if ($targets->isEmpty()) {
            $targets = collect((array) config('app.available_locales', [config('app.locale', 'en')]))
                ->map(static fn (mixed $locale): string => trim((string) $locale))
                ->filter(static fn (string $locale): bool => $locale !== '' && $locale !== $sourceLocale)
                ->values();
        }

        return $targets->all();
    }
}
