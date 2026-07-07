<?php

namespace App\Console\Commands;

use App\Support\ApplicationVersion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:version')]
#[Description('Show the application version.')]
class AppVersionCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ApplicationVersion $applicationVersion): int
    {
        $this->line('Application: '.config('app.display_name', config('app.name', 'Application')));
        $this->line('Version: '.$applicationVersion->label());

        $commit = $applicationVersion->commit();

        if ($commit !== null) {
            $this->line('Commit: '.$commit);
        }

        return self::SUCCESS;
    }
}
