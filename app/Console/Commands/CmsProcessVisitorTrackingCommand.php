<?php

namespace App\Console\Commands;

use App\Jobs\Cms\ProcessCmsVisitorTrackingJob;
use App\Models\Platform\Site;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cms:process-visitor-tracking {--site= : Process one central site ID only}')]
#[Description('Process CMS visitor tracking geo enrichment and retention')]
class CmsProcessVisitorTrackingCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $siteId = $this->option('site');

        $query = Site::on('central')
            ->where('status', 'active')
            ->whereNotNull('tenant_database');

        if (is_numeric($siteId)) {
            $query->whereKey((int) $siteId);
        }

        $query->orderBy('id')->pluck('id')->each(function (int $siteId): void {
            ProcessCmsVisitorTrackingJob::dispatch($siteId);
        });

        return self::SUCCESS;
    }
}
