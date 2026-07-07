<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\Health\BuildCmsHealthReportAction;
use App\Actions\Admin\Cms\InstallPublicAccountModuleAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CmsHealthController extends Controller
{
    public function index(BuildCmsHealthReportAction $healthReport): Response
    {
        return Inertia::render('Admin/Cms/Health/Index', [
            'report' => $healthReport->handle(),
        ]);
    }

    public function repairPublicAccount(InstallPublicAccountModuleAction $installPublicAccountModule): RedirectResponse
    {
        $installPublicAccountModule->handle();

        return redirect()
            ->route('admin.cms.health.index')
            ->with('status', __('cms_admin_ui.health.public_account.repaired'));
    }
}
