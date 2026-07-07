<?php

namespace App\Http\Controllers\Admin\Locale;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Locale\UpdateAdminLocaleRequest;
use App\Models\Platform\SiteUserMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;

class AdminLocaleController extends Controller
{
    public function update(UpdateAdminLocaleRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && TenantContext::isResolved(), 403);

        $validated = $request->validated();
        $locale = (string) $validated['locale'];

        SiteUserMembership::query()->updateOrCreate(
            [
                'site_id' => TenantContext::siteId(),
                'user_id' => $user->id,
            ],
            [
                'is_active' => true,
                'admin_locale' => $locale,
            ],
        );

        App::setLocale($locale);

        return back();
    }
}
