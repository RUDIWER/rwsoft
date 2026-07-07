<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Services\Admin\Cms\GoogleSearchConsoleService;
use App\Support\PublicSite\CmsSearchConsoleSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CmsSearchConsoleController extends Controller
{
    public function connect(
        Request $request,
        CmsSearchConsoleSettings $settings,
        GoogleSearchConsoleService $searchConsole,
    ): RedirectResponse {
        if (! $settings->enabled() || ! $settings->hasOAuthClient() || $settings->siteUrl() === '') {
            return redirect()
                ->route('admin.cms.settings.edit', ['tab' => 'search_console'])
                ->with('error', __('cms_admin_ui.search_console.flash.not_configured'));
        }

        $nonce = (string) Str::uuid();
        $state = base64_encode(json_encode([
            'site_id' => TenantContext::siteId(),
            'user_id' => $request->user()?->id,
            'nonce' => $nonce,
        ], JSON_THROW_ON_ERROR));

        Cache::put($this->stateCacheKey($state), true, now()->addMinutes(10));

        $client = $searchConsole->oauthClient(route('admin.cms.search-console.callback'));
        $client->setState($state);

        return redirect()->away($client->createAuthUrl());
    }

    public function callback(
        Request $request,
        CmsSearchConsoleSettings $settings,
        GoogleSearchConsoleService $searchConsole,
    ): RedirectResponse {
        $state = (string) $request->query('state', '');

        if (! $this->validState($state, $request)) {
            return redirect()
                ->route('admin.cms.settings.edit', ['tab' => 'search_console'])
                ->with('error', __('cms_admin_ui.search_console.flash.invalid_state'));
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return redirect()
                ->route('admin.cms.settings.edit', ['tab' => 'search_console'])
                ->with('error', __('cms_admin_ui.search_console.flash.missing_code'));
        }

        $client = $searchConsole->oauthClient(route('admin.cms.search-console.callback'));
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            $settings->markError((string) $token['error']);

            return redirect()
                ->route('admin.cms.settings.edit', ['tab' => 'search_console'])
                ->with('error', __('cms_admin_ui.search_console.flash.connect_failed'));
        }

        if (! isset($token['refresh_token'])) {
            return redirect()
                ->route('admin.cms.settings.edit', ['tab' => 'search_console'])
                ->with('warning', __('cms_admin_ui.search_console.flash.missing_refresh_token'));
        }

        $settings->storeOauthToken($token);
        $settings->markSuccess();

        return redirect()
            ->route('admin.cms.settings.edit', ['tab' => 'search_console'])
            ->with('status', __('cms_admin_ui.search_console.flash.connected'));
    }

    public function disconnect(CmsSearchConsoleSettings $settings): RedirectResponse
    {
        $settings->clearOauthToken();

        return redirect()
            ->route('admin.cms.settings.edit', ['tab' => 'search_console'])
            ->with('status', __('cms_admin_ui.search_console.flash.disconnected'));
    }

    public function test(GoogleSearchConsoleService $searchConsole): RedirectResponse
    {
        $result = $searchConsole->testConnection();

        return redirect()
            ->route('admin.cms.settings.edit', ['tab' => 'search_console'])
            ->with($result['ok'] ? 'status' : 'error', $result['ok']
                ? __('cms_admin_ui.search_console.flash.test_success')
                : ($result['message'] ?: __('cms_admin_ui.search_console.flash.test_failed')));
    }

    private function validState(string $state, Request $request): bool
    {
        if ($state === '' || ! Cache::pull($this->stateCacheKey($state))) {
            return false;
        }

        $decoded = json_decode((string) base64_decode($state, true), true);

        if (! is_array($decoded)) {
            return false;
        }

        return (int) ($decoded['site_id'] ?? 0) === (int) TenantContext::siteId()
            && (int) ($decoded['user_id'] ?? 0) === (int) $request->user()?->id
            && is_string($decoded['nonce'] ?? null);
    }

    private function stateCacheKey(string $state): string
    {
        return 'cms:gsc:oauth-state:'.sha1($state);
    }
}
