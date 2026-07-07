<?php

namespace App\Console\Commands;

use App\Actions\Admin\Cms\SyncPublicTextKeysAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cms:sync-public-texts {--dry-run : Alleen rapporteren, niets wegschrijven}')]
#[Description('Scan publieke templates op public_text keys en synchroniseer ontbrekende vaste teksten')]
class CmsSyncPublicTextsCommand extends Command
{
    public function handle(SyncPublicTextKeysAction $syncPublicTextKeys): int
    {
        $result = $syncPublicTextKeys->handle((bool) $this->option('dry-run'));

        $this->info(sprintf('Keys gevonden: %d', (int) $result['keys_found']));
        $this->info(sprintf('Nieuwe teksten: %d', (int) $result['texts_created']));
        $this->info(sprintf('Nieuwe vertaalrijen: %d', (int) $result['translations_created']));

        foreach ($result['hardcoded_warnings'] as $warning) {
            $this->warn(sprintf('Hardcoded tekst: %s:%d %s', $warning['file'], $warning['line'], $warning['text']));
        }

        foreach ($result['changed_default_warnings'] as $warning) {
            $this->warn(sprintf('Default verschilt: %s', $warning['key']));
        }

        foreach ($result['unused_warnings'] as $key) {
            $this->warn(sprintf('Ongebruikte key: %s', $key));
        }

        return self::SUCCESS;
    }
}
