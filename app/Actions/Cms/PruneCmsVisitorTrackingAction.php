<?php

namespace App\Actions\Cms;

use App\Models\Cms\CmsVisit;
use App\Models\Cms\CmsVisitor;
use Illuminate\Support\Carbon;

class PruneCmsVisitorTrackingAction
{
    public function handle(int $retentionDays): void
    {
        $cutoff = Carbon::now()->subDays(max(1, $retentionDays));

        CmsVisit::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        CmsVisitor::query()
            ->whereDoesntHave('visits')
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $cutoff);
            })
            ->delete();
    }
}
