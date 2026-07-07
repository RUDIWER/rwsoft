<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\SiteUserForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;

class SiteUserPasswordResetLinkController extends Controller
{
    public function store(SiteUserForgotPasswordRequest $request): RedirectResponse
    {
        Password::broker('site_users')->sendResetLink($request->only('email'));

        return back()->with('status', __('public_account.auth.password_reset_sent'));
    }
}
