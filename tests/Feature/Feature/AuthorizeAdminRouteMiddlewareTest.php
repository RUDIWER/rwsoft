<?php

namespace Tests\Feature\Feature;

use App\Http\Middleware\AuthorizeAdminRoute;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

class AuthorizeAdminRouteMiddlewareTest extends TestCase
{
    public function test_unauthorized_post_redirects_back_with_error_flash(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user
            ->shouldReceive('canAccessRoute')
            ->once()
            ->with('admin.cms.languages.reorder')
            ->andReturn(false);

        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = Request::create('/admin/cms/languages/reorder', 'POST');
        $request->headers->set('referer', 'http://localhost/admin/cms/languages');
        $request->setLaravelSession(new Store('array', new ArraySessionHandler(60)));
        $request->setRouteResolver(fn (): object => new class
        {
            public function getName(): string
            {
                return 'admin.cms.languages.reorder';
            }
        });

        $response = app(AuthorizeAdminRoute::class)->handle(
            $request,
            fn (): never => throw new \RuntimeException('Request should not continue.')
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://localhost/admin/cms/languages', $response->headers->get('Location'));
        $this->assertSame(
            __('admin_common_ui.errors.unauthorized_route'),
            $response->getSession()->get('error')
        );
    }
}
