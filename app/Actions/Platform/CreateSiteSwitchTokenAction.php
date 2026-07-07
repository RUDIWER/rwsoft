<?php

namespace App\Actions\Platform;

use App\Models\Platform\Site;
use App\Models\Platform\SiteSwitchToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreateSiteSwitchTokenAction
{
    public function handle(User $user, Site $site, Request $request): string
    {
        $plainToken = Str::random(64);

        SiteSwitchToken::query()->create([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addSeconds(60),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $plainToken;
    }
}
