<?php

namespace App\Http\Middleware;

use App\Support\Localization\AdminLocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private readonly AdminLocaleResolver $localeResolver) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->localeResolver->resolveForRequest($request));

        return $next($request);
    }
}
