<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Platform\CreateSiteSwitchTokenAction;
use App\Http\Controllers\Controller;
use App\Models\Platform\Site;
use App\Models\Platform\SiteUserMembership;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SiteSwitcherController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return Inertia::render('Auth/SiteSwitcher', [
            'memberships' => $this->membershipsFor($user, $request),
            'isPlatformAdmin' => (bool) $user->is_platform_admin,
            'labels' => [
                'open_admin' => __('auth_ui.site_switcher.open_admin'),
                'open_public' => __('auth_ui.site_switcher.open_public'),
            ],
        ]);
    }

    public function switch(Request $request, Site $site, CreateSiteSwitchTokenAction $createSiteSwitchToken): SymfonyResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $membership = SiteUserMembership::query()
            ->where('site_id', $site->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        abort_unless($membership instanceof SiteUserMembership && $site->status === 'active', 403);

        $primaryDomain = $site->primaryDomain()->first();

        if (! $primaryDomain) {
            return back()->with('error', 'Deze site heeft nog geen primair domein.');
        }

        $token = $createSiteSwitchToken->handle($user, $site, $request);
        $scheme = $this->schemeForDomain((bool) $primaryDomain->force_https, $request);

        return Inertia::location($scheme.'://'.$primaryDomain->host.'/auth/site-switch?token='.$token);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function membershipsFor(User $user, Request $request): array
    {
        return SiteUserMembership::query()
            ->with(['site.primaryDomain'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereHas('site', fn ($query) => $query->where('status', 'active'))
            ->get()
            ->map(function (SiteUserMembership $membership) use ($request): array {
                $primaryDomain = $membership->site?->primaryDomain;
                $publicUrl = $primaryDomain
                    ? $this->schemeForDomain((bool) $primaryDomain->force_https, $request).'://'.$primaryDomain->host
                    : null;

                return [
                    'id' => $membership->id,
                    'site' => [
                        'id' => $membership->site?->id,
                        'name' => $membership->site?->name,
                        'slug' => $membership->site?->slug,
                        'status' => $membership->site?->status,
                        'primary_domain' => $primaryDomain?->host,
                        'public_url' => $publicUrl,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    private function schemeForDomain(bool $forceHttps, Request $request): string
    {
        return $forceHttps && ! app()->isLocal()
            ? 'https'
            : $request->getScheme();
    }
}
