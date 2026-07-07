<?php

namespace App\Jobs\Cms;

use App\Actions\Cms\PruneCmsVisitorTrackingAction;
use App\Actions\Platform\ConfigureTenantDatabaseAction;
use App\Models\Cms\CmsVisitor;
use App\Models\Platform\Site;
use App\Support\PublicSite\CmsVisitorTrackingSettings;
use App\Support\PublicSite\VisitorGeoResolver;
use App\Support\Tenancy\TenantDatabaseGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCmsVisitorTrackingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public function __construct(public int $siteId) {}

    public function handle(
        CmsVisitorTrackingSettings $settings,
        VisitorGeoResolver $geoResolver,
        PruneCmsVisitorTrackingAction $pruneVisitorTracking,
    ): void {
        $this->configureTenantDatabase();

        if ($settings->shouldPrune()) {
            $pruneVisitorTracking->handle($settings->retentionDays());
        }

        if (! $settings->geoEnabled()) {
            return;
        }

        CmsVisitor::query()
            ->where('geo_checked', 0)
            ->orderBy('id')
            ->limit(250)
            ->get()
            ->each(function (CmsVisitor $visitor) use ($settings, $geoResolver): void {
                $visit = $visitor->visits()->latest('id')->first();
                $geoData = $geoResolver->resolve($visitor->ip, $visit?->country_code_header);
                $allowedCountries = $settings->allowedCountries();
                $countryCode = is_array($geoData) ? (string) ($geoData['country_code'] ?? '') : '';

                if ($countryCode !== '' && $allowedCountries !== [] && ! in_array($countryCode, $allowedCountries, true)) {
                    if ($settings->deleteDisallowedCountries()) {
                        $visitor->visits()->delete();
                        $visitor->delete();

                        return;
                    }
                }

                $visitor->forceFill([
                    ...(is_array($geoData) ? $geoData : []),
                    'geo_checked' => 1,
                ])->save();
            });
    }

    private function configureTenantDatabase(): void
    {
        $site = Site::on('central')->findOrFail($this->siteId);

        app(ConfigureTenantDatabaseAction::class)->handle($site);
        TenantDatabaseGuard::ensureTenantConnection();
    }
}
