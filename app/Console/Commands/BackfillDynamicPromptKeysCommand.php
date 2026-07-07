<?php

namespace App\Console\Commands;

use App\Models\Query\Query;
use App\Support\Translations\DynamicPromptFileManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('translations:dynamic-prompts-backfill {--source= : Bronlocale (default: dynamic_prompts.default_locale)} {--dry-run : Toon wijzigingen zonder op te slaan} {--chunk=200 : Chunk grootte voor DB verwerking}')]
#[Description('Genereer ontbrekende dynamic prompt keys voor query data en sync naar bronvertaling')]
class BackfillDynamicPromptKeysCommand extends Command
{
    public function handle(DynamicPromptFileManager $fileManager): int
    {
        $sourceLocale = (string) ($this->option('source') ?: config('dynamic_prompts.default_locale', 'en'));
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        $flatTranslations = $fileManager->flatten($fileManager->read($sourceLocale));
        $queriesChanged = 0;
        $keysAdded = 0;

        Query::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($queries) use (
                $dryRun,
                &$queriesChanged,
                &$keysAdded,
                &$flatTranslations
            ): void {
                foreach ($queries as $query) {
                    $bindingRows = is_array($query->binding_rows) ? $query->binding_rows : [];

                    [$normalizedRows, $changed, $added] = $this->backfillQueryBindings(
                        $bindingRows,
                        (string) $query->id,
                        $flatTranslations,
                    );

                    if (! $changed) {
                        continue;
                    }

                    $queriesChanged++;
                    $keysAdded += $added;

                    if (! $dryRun) {
                        $query->forceFill(['binding_rows' => $normalizedRows])->save();
                    }
                }
            });

        if (! $dryRun) {
            $fileManager->write($sourceLocale, $fileManager->expand($flatTranslations));
        }

        $this->line('Resultaat dynamic prompt backfill:');
        $this->line(sprintf('- Queries aangepast: %d', $queriesChanged));
        $this->line(sprintf('- Nieuwe vertaalkeys toegevoegd: %d', $keysAdded));

        if ($dryRun) {
            $this->warn('Dry-run actief: geen DB of vertaalfile wijzigingen opgeslagen.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, mixed>  $bindingRows
     * @param  array<string, string>  $flatTranslations
     * @return array{0: array<int, mixed>, 1: bool, 2: int}
     */
    private function backfillQueryBindings(array $bindingRows, string $queryKey, array &$flatTranslations): array
    {
        $changed = false;
        $added = 0;

        foreach ($bindingRows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $parameter = trim((string) ($row['parameter'] ?? 'binding_'.$index));

            foreach (['title', 'prompt'] as $type) {
                $fallback = trim((string) ($row[$type] ?? ''));
                $keyName = $type.'_key';
                $existingKey = trim((string) ($row[$keyName] ?? $row[$type.'Key'] ?? ''));

                if ($existingKey === '' && $fallback !== '') {
                    $existingKey = sprintf(
                        'queries.%s.bindings.%s.%s',
                        $queryKey,
                        $parameter,
                        $type,
                    );
                    $row[$keyName] = $existingKey;
                    unset($row[$type.'Key']);
                    $changed = true;
                }

                if ($existingKey !== '' && $fallback !== '' && ! array_key_exists($existingKey, $flatTranslations)) {
                    $flatTranslations[$existingKey] = $fallback;
                    $added++;
                }
            }

            $bindingRows[$index] = $row;
        }

        return [$bindingRows, $changed, $added];
    }
}
