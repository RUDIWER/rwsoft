<?php

namespace App\Http\Middleware;

use App\Actions\Platform\ConfigureTenantDatabaseAction;
use App\Actions\Platform\ResolveSiteFromHostAction;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantSite
{
    public function __construct(
        private readonly ResolveSiteFromHostAction $resolveSiteFromHost,
        private readonly ConfigureTenantDatabaseAction $configureTenantDatabase,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = $this->resolveSiteFromHost->normalizeHost($request->getHost());
        $site = $this->resolveSiteFromHost->handle($host);

        if (! $site) {
            return $this->redirectWithTenantProblem(
                $request,
                sprintf('Domein "%s" is nog niet gekoppeld aan een site. Maak een site aan in platformbeheer en voeg dit domein toe als primair domein.', $host),
                404,
            );
        }

        if ($site->status !== 'active') {
            return $this->redirectWithTenantProblem(
                $request,
                sprintf('Site "%s" is gevonden, maar staat nog op status "%s". Provisioneer en activeer de site eerst in platformbeheer.', $site->name, $site->status),
                503,
            );
        }

        $this->configureTenantDatabase->handle($site);

        return $next($request);
    }

    private function redirectWithTenantProblem(Request $request, string $message, int $status): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        $user = $request->user();

        if ($user instanceof User && (bool) $user->is_platform_admin) {
            return redirect()
                ->route('platform.sites.index')
                ->with('warning', $message);
        }

        if ($user instanceof User) {
            return redirect()
                ->route('site-switcher.index')
                ->with('warning', $message);
        }

        return redirect()
            ->route('login')
            ->with('warning', $message);
    }
}
