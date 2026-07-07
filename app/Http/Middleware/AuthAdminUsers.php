<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthAdminUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        if ($user->hasRoleKey('super_admin') || $user->hasRoleKey('admin')) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('error', 'Uw account heeft geen toegang tot het admin gedeelte.');
    }
}
