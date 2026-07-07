<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Platform\Site;
use App\Models\Platform\SiteDomain;
use App\Models\Platform\SiteUserMembership;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class PlatformDashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Platform/Dashboard', [
            'stats' => [
                'sites' => Site::query()->count(),
                'active_sites' => Site::query()->where('status', 'active')->count(),
                'domains' => SiteDomain::query()->count(),
                'memberships' => SiteUserMembership::query()->where('is_active', true)->count(),
                'platform_admins' => User::query()->where('is_platform_admin', true)->count(),
            ],
            'recentSites' => Site::query()
                ->with('primaryDomain:id,site_id,host')
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'name', 'slug', 'tenant_database', 'status', 'created_at']),
        ]);
    }
}
