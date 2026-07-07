<?php

namespace App\Http\Middleware;

use App\Actions\PublicSite\TrackSiteUserSessionAction;
use App\Models\PublicSite\SiteUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSiteUserSession
{
    public function __construct(private readonly TrackSiteUserSessionAction $sessions) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $siteUser = $request->user('site_user');

        if (! $siteUser instanceof SiteUser) {
            return $next($request);
        }

        $session = $this->sessions->current($request);

        if ($session !== null && ! $session->isActive()) {
            auth('site_user')->logout();
            $request->session()->forget(TrackSiteUserSessionAction::SESSION_TOKEN_KEY);
            $request->session()->regenerateToken();

            return redirect()
                ->route('site-user.login')
                ->with('warning', __('public_account.sessions.revoked'));
        }

        $this->sessions->track($request, $siteUser);

        return $next($request);
    }
}
