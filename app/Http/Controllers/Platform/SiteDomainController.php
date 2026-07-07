<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreSiteDomainRequest;
use App\Models\Platform\Site;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SiteDomainController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(StoreSiteDomainRequest $request, Site $site): RedirectResponse
    {
        $validated = $request->validated();

        DB::connection('central')->transaction(function () use ($site, $validated): void {
            $makePrimary = (bool) ($validated['is_primary'] ?? false) || ! $site->domains()->exists();

            if ($makePrimary) {
                $site->domains()->update(['is_primary' => false]);
            }

            $site->domains()->create([
                'host' => $validated['host'],
                'is_primary' => $makePrimary,
                'force_https' => (bool) ($validated['force_https'] ?? true),
            ]);
        });

        $this->auditLogger->success(
            action: 'platform.site-domain.create',
            module: 'platform',
            subjectType: 'site',
            subjectKey: (string) $site->id,
            message: 'Domein succesvol toegevoegd.',
            meta: ['host' => $validated['host']],
            request: $request,
        );

        return redirect()
            ->route('platform.sites.edit', ['id' => $site->id])
            ->with('status', __('admin_common_ui.platform.sites.flash.domain_added'));
    }
}
