<?php

namespace App\Http\Middleware;

use App\Models\Platform\SiteUserMembership;
use App\Models\User;
use App\Support\ApplicationVersion;
use App\Support\Localization\AdminLocaleResolver;
use App\Support\Security\TenantAcl;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'platform' => function () use ($request): array {
                $user = $request->user();
                $site = TenantContext::site();

                return [
                    'is_platform_admin' => $user instanceof User && (bool) $user->is_platform_admin,
                    'current_site' => $site ? [
                        'id' => $site->id,
                        'name' => $site->name,
                        'slug' => $site->slug,
                        'status' => $site->status,
                    ] : null,
                ];
            },
            'siteMemberships' => function () use ($request): array {
                $user = $request->user();

                if (! $user instanceof User) {
                    return [];
                }

                return SiteUserMembership::query()
                    ->with(['site.primaryDomain'])
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->whereHas('site', fn ($query) => $query->where('status', 'active'))
                    ->get()
                    ->map(fn (SiteUserMembership $membership): array => [
                        'site_id' => $membership->site?->id,
                        'name' => $membership->site?->name,
                        'slug' => $membership->site?->slug,
                        'primary_domain' => $membership->site?->primaryDomain?->host,
                    ])
                    ->values()
                    ->all();
            },
            'flash' => [
                '_id' => fn (): ?string => $this->flashId($request),
                'status' => fn (): ?string => $request->session()->get('status'),
                'error' => fn (): ?string => $request->session()->get('error'),
                'warning' => fn (): ?string => $request->session()->get('warning'),
                'details' => fn (): array => (array) $request->session()->get('flash_details', []),
            ],
            'cms_modules' => function (): array {
                if (! Schema::hasTable('cms_modules')) {
                    return ['installed' => []];
                }

                return [
                    'installed' => DB::table('cms_modules')
                        ->where('status', 'active')
                        ->pluck('key')
                        ->values()
                        ->all(),
                ];
            },
            'app' => function (): array {
                $applicationVersion = app(ApplicationVersion::class)->payload();
                $adminCommonUi = trans('admin_common_ui');
                $adminSecurityUi = trans('admin_security_ui');
                $dynamicPrompts = trans('dynamic_prompts');
                $translationEditorUi = trans('translation_editor_ui');
                $dbDiagramUi = trans('db_diagram_ui');
                $queryBuilderUi = trans('query_builder_ui');
                $cmsAdminUi = trans('cms_admin_ui');

                return [
                    'name' => config('app.display_name', config('app.name', 'Application')),
                    'locale' => app()->getLocale(),
                    ...$applicationVersion,
                    'available_locales' => config('app.available_locales', [config('app.locale', 'en')]),
                    'locale_options' => app(AdminLocaleResolver::class)->localeOptions(),
                    'translations' => [
                        'admin_common_ui' => is_array($adminCommonUi) ? $adminCommonUi : [],
                        'admin_security_ui' => is_array($adminSecurityUi) ? $adminSecurityUi : [],
                        'dynamic_prompts' => is_array($dynamicPrompts) ? $dynamicPrompts : [],
                        'translation_editor_ui' => is_array($translationEditorUi) ? $translationEditorUi : [],
                        'db_diagram_ui' => is_array($dbDiagramUi) ? $dbDiagramUi : [],
                        'query_builder_ui' => is_array($queryBuilderUi) ? $queryBuilderUi : [],
                        'cms_admin_ui' => is_array($cmsAdminUi) ? $cmsAdminUi : [],
                    ],
                ];
            },
            'acl' => function (): array {
                if (! Auth::check()) {
                    return [
                        'is_super_admin' => false,
                        'allowed_routes' => [],
                    ];
                }

                $user = Auth::user();

                if (! $user instanceof User) {
                    return [
                        'is_super_admin' => false,
                        'allowed_routes' => [],
                    ];
                }

                if (! Schema::hasTable('acl_roles') || ! Schema::hasTable('acl_permissions') || ! Schema::hasTable('acl_permission_role') || ! Schema::hasTable('acl_role_user')) {
                    return [
                        'is_super_admin' => false,
                        'allowed_routes' => [],
                    ];
                }

                if ($user->hasRoleKey('super_admin')) {
                    return [
                        'is_super_admin' => true,
                        'allowed_routes' => [],
                    ];
                }

                if (TenantContext::isResolved()) {
                    return [
                        'is_super_admin' => false,
                        'allowed_routes' => app(TenantAcl::class)->allowedRouteNames($user),
                    ];
                }

                $allowedRoutes = DB::table('acl_permissions')
                    ->select('acl_permissions.route_name')
                    ->join('acl_permission_role', 'acl_permission_role.acl_permission_id', '=', 'acl_permissions.id')
                    ->join('acl_role_user', 'acl_role_user.acl_role_id', '=', 'acl_permission_role.acl_role_id')
                    ->where('acl_role_user.user_id', $user->id)
                    ->where('acl_permission_role.active', 1)
                    ->distinct()
                    ->pluck('acl_permissions.route_name')
                    ->values()
                    ->all();

                return [
                    'is_super_admin' => false,
                    'allowed_routes' => $allowedRoutes,
                ];
            },
            'rwtable' => function (): array {
                $translations = trans('rwtable::rwtable.vue');

                return [
                    'locale' => app()->getLocale(),
                    'translations' => is_array($translations) ? $translations : [],
                ];
            },
        ];
    }

    private function flashId(Request $request): ?string
    {
        if (! $request->session()->has('status') && ! $request->session()->has('error') && ! $request->session()->has('warning')) {
            return null;
        }

        return (string) str()->uuid();
    }
}
