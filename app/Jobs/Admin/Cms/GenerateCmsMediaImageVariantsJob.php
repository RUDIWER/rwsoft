<?php

namespace App\Jobs\Admin\Cms;

use App\Actions\Admin\Cms\GenerateCmsMediaImageVariantsAction;
use App\Actions\Platform\ConfigureTenantDatabaseAction;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Platform\Site;
use App\Support\Tenancy\TenantDatabaseGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateCmsMediaImageVariantsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public int $siteId,
        public int $assetId,
    ) {}

    public function handle(GenerateCmsMediaImageVariantsAction $generateVariants): void
    {
        $this->configureTenantDatabase();

        $asset = CmsMediaAsset::query()->findOrFail($this->assetId);

        $generateVariants->handle($asset);
    }

    private function configureTenantDatabase(): void
    {
        $site = Site::on('central')->findOrFail($this->siteId);

        app(ConfigureTenantDatabaseAction::class)->handle($site);
        TenantDatabaseGuard::ensureTenantConnection();
    }
}
