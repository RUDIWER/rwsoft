<?php

namespace App\Console\Commands;

use App\Actions\Admin\Cms\SyncSystemCountryFlagsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('cms:sync-country-flags {--archive= : Pad naar een lokaal flag-icons ZIP bestand}')]
#[Description('Download en synchroniseer de systeembrede country flag catalogus')]
class CmsSyncCountryFlagsCommand extends Command
{
    public function handle(SyncSystemCountryFlagsAction $syncCountryFlags): int
    {
        try {
            $result = $syncCountryFlags->handle(
                $this->option('archive') ? (string) $this->option('archive') : null,
            );
        } catch (Throwable $exception) {
            $this->error('Country flags synchroniseren mislukt: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Country flags gesynchroniseerd: %d (%s %s)',
            (int) $result['countries'],
            (string) $result['source']['name'],
            (string) $result['source']['version'],
        ));

        return self::SUCCESS;
    }
}
