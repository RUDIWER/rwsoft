<?php

namespace Tests\Unit\PublicSite;

use App\Models\PublicSite\SiteUser;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicAccountConfigurationTest extends TestCase
{
    public function test_site_user_guard_is_separate_from_admin_web_guard(): void
    {
        $this->assertSame('users', config('auth.guards.web.provider'));
        $this->assertSame(User::class, config('auth.providers.users.model'));

        $this->assertSame('site_users', config('auth.guards.site_user.provider'));
        $this->assertSame(SiteUser::class, config('auth.providers.site_users.model'));
    }

    public function test_site_user_model_uses_tenant_connection(): void
    {
        $this->assertSame('tenant', (new SiteUser)->getConnectionName());
    }

    public function test_public_account_routes_are_tenant_scoped(): void
    {
        foreach ([
            'site-user.login',
            'site-user.register',
            'site-user.password.request',
            'site-user.dashboard',
            'site-user.profile',
            'site-user.security',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Route [{$routeName}] is missing.");
            $this->assertContains('tenant.resolve', $route?->gatherMiddleware() ?? []);
        }
    }

    public function test_admin_site_user_overview_route_is_acl_protected(): void
    {
        $route = Route::getRoutes()->getByName('admin.cms.site-users.index');

        $this->assertNotNull($route);
        $middleware = $route?->gatherMiddleware() ?? [];

        $this->assertContains('tenant.resolve', $middleware);
        $this->assertContains('AuthAdmin', $middleware);
        $this->assertContains('AdminAcl', $middleware);
    }
}
