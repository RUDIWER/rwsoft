<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\ProvisionSiteDatabaseAction;
use App\Http\Controllers\Controller;
use App\Models\Platform\Site;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;

class SiteProvisioningController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(Site $site, ProvisionSiteDatabaseAction $provisionSiteDatabase): RedirectResponse
    {
        if ($site->status === 'active' && blank($site->provisioning_error)) {
            $this->auditLogger->log(
                action: 'platform.site.provision.skip',
                module: 'platform',
                subjectType: 'site',
                subjectKey: (string) $site->id,
                success: true,
                severity: 'info',
                message: 'Site database provisioning overgeslagen omdat de site al actief is.',
                meta: [
                    'tenant_database' => $site->tenant_database,
                    'status' => $site->status,
                ],
                request: request(),
            );

            return redirect()
                ->route('platform.sites.edit', ['id' => $site->id])
                ->with('warning', __('admin_common_ui.platform.sites.flash.already_provisioned'));
        }

        $success = $provisionSiteDatabase->handle($site);

        $this->auditLogger->log(
            action: 'platform.site.provision',
            module: 'platform',
            subjectType: 'site',
            subjectKey: (string) $site->id,
            success: $success,
            severity: $success ? 'info' : 'error',
            message: $success ? 'Site database succesvol geprovisioned.' : 'Site database provisioning mislukt.',
            meta: [
                'tenant_database' => $site->tenant_database,
                'error' => $site->fresh()?->provisioning_error,
            ],
            request: request(),
        );

        return redirect()
            ->route('platform.sites.edit', ['id' => $site->id])
            ->with($success ? 'status' : 'error', $success ? __('admin_common_ui.platform.sites.flash.provisioned') : __('admin_common_ui.platform.sites.flash.provisioning_failed'));
    }
}
