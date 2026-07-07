<?php

namespace App\Console\Commands;

use App\Support\Translations\DynamicPromptFileManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('translations:add-locale {locale : Nieuwe locale code, bv. es} {--source= : Bronlocale (default: dynamic_prompts.default_locale)} {--register : Voeg locale toe aan config/app.php available_locales}')]
#[Description('Maak dynamic prompt vertaalfile aan voor een nieuwe locale')]
class AddLocaleCommand extends Command
{
    public function handle(DynamicPromptFileManager $fileManager): int
    {
        $locale = trim((string) $this->argument('locale'));

        if (! preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $locale)) {
            $this->error('Locale formaat ongeldig. Gebruik bijvoorbeeld "es" of "pt_BR".');

            return self::FAILURE;
        }

        $sourceLocale = (string) ($this->option('source') ?: config('dynamic_prompts.default_locale', 'en'));
        $sourceTranslations = $fileManager->read($sourceLocale);
        $targetPath = $fileManager->filePath($locale);

        if (File::exists($targetPath)) {
            $this->warn(sprintf('Locale %s bestaat al op %s', $locale, $targetPath));
        } else {
            $fileManager->write($locale, $sourceTranslations);
            $this->info(sprintf('Locale %s aangemaakt op %s', $locale, $targetPath));
        }

        if ((bool) $this->option('register')) {
            $registered = $this->registerLocaleInConfig($locale);

            if ($registered) {
                $this->info(sprintf('Locale %s toegevoegd aan config/app.php available_locales.', $locale));
            } else {
                $this->warn('Locale niet automatisch geregistreerd. Pas config/app.php manueel aan.');
            }
        } else {
            $this->line('Tip: voeg locale toe aan config/app.php -> available_locales.');
        }

        return self::SUCCESS;
    }

    private function registerLocaleInConfig(string $locale): bool
    {
        $configPath = config_path('app.php');

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

        if (! $locales->contains($locale)) {
            $locales->push($locale);
        }

        $replacement = "'available_locales' => [".$locales->map(static fn (string $entry): string => "'{$entry}'")->implode(', ').']';
        $updated = preg_replace('/\'available_locales\'\s*=>\s*\[[^\]]*\]/m', $replacement, $content, 1);

        if (! is_string($updated) || $updated === $content) {
            return false;
        }

        File::put($configPath, $updated);

        return true;
    }
}
