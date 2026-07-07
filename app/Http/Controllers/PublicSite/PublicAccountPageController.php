<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Support\PublicSite\CmsLanguageSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicAccountPageController extends Controller
{
    public function __construct(
        private readonly CmsPublicPageController $pages,
        private readonly CmsLanguageSettings $languageSettings,
    ) {}

    public function login(Request $request): View|RedirectResponse
    {
        return $this->pages->showPath($request, 'account/login');
    }

    public function register(Request $request): View|RedirectResponse
    {
        return $this->pages->showPath($request, 'account/register');
    }

    public function forgotPassword(Request $request): View|RedirectResponse
    {
        return $this->pages->showPath($request, 'account/forgot-password');
    }

    public function resetPassword(Request $request): View|RedirectResponse
    {
        return $this->pages->showPath($request, 'account/reset-password');
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        return $this->pages->showPath($request, 'account/dashboard');
    }

    public function profile(Request $request): View|RedirectResponse
    {
        return $this->pages->showPath($request, 'account/profile');
    }

    public function security(Request $request): View|RedirectResponse
    {
        return $this->pages->showPath($request, 'account/security');
    }

    public function twoFactorChallenge(Request $request): View|RedirectResponse
    {
        return $this->pages->showPath($request, 'account/two-factor-challenge');
    }

    public function localizedLogin(Request $request, string $locale): View|RedirectResponse
    {
        return $this->pages->localizedShowPath($request, $this->resolveLocale($locale), 'account/login');
    }

    public function localizedRegister(Request $request, string $locale): View|RedirectResponse
    {
        return $this->pages->localizedShowPath($request, $this->resolveLocale($locale), 'account/register');
    }

    public function localizedForgotPassword(Request $request, string $locale): View|RedirectResponse
    {
        return $this->pages->localizedShowPath($request, $this->resolveLocale($locale), 'account/forgot-password');
    }

    public function localizedResetPassword(Request $request, string $locale): View|RedirectResponse
    {
        return $this->pages->localizedShowPath($request, $this->resolveLocale($locale), 'account/reset-password');
    }

    public function localizedDashboard(Request $request, string $locale): View|RedirectResponse
    {
        return $this->pages->localizedShowPath($request, $this->resolveLocale($locale), 'account/dashboard');
    }

    public function localizedProfile(Request $request, string $locale): View|RedirectResponse
    {
        return $this->pages->localizedShowPath($request, $this->resolveLocale($locale), 'account/profile');
    }

    public function localizedSecurity(Request $request, string $locale): View|RedirectResponse
    {
        return $this->pages->localizedShowPath($request, $this->resolveLocale($locale), 'account/security');
    }

    public function localizedTwoFactorChallenge(Request $request, string $locale): View|RedirectResponse
    {
        return $this->pages->localizedShowPath($request, $this->resolveLocale($locale), 'account/two-factor-challenge');
    }

    private function resolveLocale(string $locale): string
    {
        return in_array($locale, $this->languageSettings->activeLocales(), true)
            ? $locale
            : $this->languageSettings->defaultLocale();
    }
}
