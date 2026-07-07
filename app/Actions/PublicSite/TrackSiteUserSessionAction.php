<?php

namespace App\Actions\PublicSite;

use App\Models\PublicSite\SiteUser;
use App\Models\PublicSite\SiteUserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TrackSiteUserSessionAction
{
    public const SESSION_TOKEN_KEY = 'site_user.session_token';

    public function track(Request $request, SiteUser $siteUser): SiteUserSession
    {
        $token = $this->currentToken($request);
        $hash = $this->hashToken($token);

        return SiteUserSession::query()->updateOrCreate(
            ['session_token_hash' => $hash],
            [
                'site_user_id' => $siteUser->id,
                'ip_hash' => $this->ipHash($request),
                'user_agent' => $this->userAgent($request),
                'last_activity_at' => now(),
                'revoked_at' => null,
            ],
        );
    }

    public function current(Request $request): ?SiteUserSession
    {
        $hash = $this->currentTokenHash($request);

        if ($hash === null) {
            return null;
        }

        return SiteUserSession::query()
            ->where('session_token_hash', $hash)
            ->first();
    }

    public function revokeCurrent(Request $request): void
    {
        $hash = $this->currentTokenHash($request);

        if ($hash === null) {
            return;
        }

        SiteUserSession::query()
            ->where('session_token_hash', $hash)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeOtherSessions(Request $request, SiteUser $siteUser): int
    {
        $currentHash = $this->currentTokenHash($request);

        if ($currentHash === null) {
            $this->track($request, $siteUser);
            $currentHash = $this->currentTokenHash($request);
        }

        return SiteUserSession::query()
            ->where('site_user_id', $siteUser->id)
            ->whereNull('revoked_at')
            ->when($currentHash !== null, fn ($query) => $query->where('session_token_hash', '!=', $currentHash))
            ->update(['revoked_at' => now()]);
    }

    /**
     * @return Collection<int, SiteUserSession>
     */
    public function activeSessionsFor(SiteUser $siteUser): Collection
    {
        return $siteUser->sessions()
            ->active()
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->get();
    }

    public function currentTokenHash(Request $request): ?string
    {
        $token = $request->session()->get(self::SESSION_TOKEN_KEY);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return $this->hashToken($token);
    }

    private function currentToken(Request $request): string
    {
        $token = $request->session()->get(self::SESSION_TOKEN_KEY);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = Str::random(64);
        $request->session()->put(self::SESSION_TOKEN_KEY, $token);

        return $token;
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function ipHash(Request $request): ?string
    {
        $ip = $request->ip();

        return $ip !== null ? hash('sha256', $ip) : null;
    }

    private function userAgent(Request $request): ?string
    {
        $userAgent = $request->userAgent();

        return $userAgent !== null ? Str::limit($userAgent, 1000, '') : null;
    }
}
