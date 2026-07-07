<?php

namespace App\Http\Controllers\Admin\Base;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Base\StoreUserRequest;
use App\Models\Platform\SiteUserMembership;
use App\Models\Security\AclRole;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Localization\AdminLocaleResolver;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()
                ->with('roles:id,key,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'created_at']),
        ]);
    }

    public function edit(
        int $id,
        AdminLocaleResolver $adminLocaleResolver,
        CmsLanguageSettings $languageSettings,
    ): Response {
        $user = $id > 0
            ? User::query()->with('roles:id')->findOrFail($id)
            : null;
        $membership = $user && TenantContext::isResolved()
            ? SiteUserMembership::query()
                ->where('site_id', TenantContext::siteId())
                ->where('user_id', $user->id)
                ->first()
            : null;

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toISOString(),
                'updated_at' => $user->updated_at?->toISOString(),
                'admin_locale' => $membership?->admin_locale,
                'allowed_content_locales' => $membership?->allowed_content_locales ?? null,
                'role_ids' => $user->roles->pluck('id')->values(),
                'database_view_access' => (bool) ($user->database_view_access ?? false),
                'database_edit_access' => (bool) ($user->database_edit_access ?? false),
                'database_add_access' => (bool) ($user->database_add_access ?? false),
                'database_delete_access' => (bool) ($user->database_delete_access ?? false),
                'database_export_access' => (bool) ($user->database_export_access ?? false),
                'database_sql_query_access' => (bool) ($user->database_sql_query_access ?? false),
                'database_sql_destructive_access' => (bool) ($user->database_sql_destructive_access ?? false),
                'database_full_backup_access' => (bool) ($user->database_full_backup_access ?? false),
            ] : null,
            'roles' => AclRole::query()
                ->orderBy('name')
                ->get(['id', 'key', 'name']),
            'locale_options' => $adminLocaleResolver->localeOptions(),
            'content_locale_options' => collect($languageSettings->languages(true))
                ->map(fn (array $language): array => [
                    'value' => (string) $language['locale'],
                    'label' => (string) ($language['native_name'] ?: strtoupper((string) $language['locale'])),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreUserRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();

        $user = $id > 0
            ? User::query()->findOrFail($id)
            : new User;
        $isCreate = ! $user->exists;

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if (filled($validated['password'] ?? null)) {
            $user->password = Hash::make($validated['password']);
        }

        $user->database_view_access = (bool) ($validated['database_view_access'] ?? false);
        $user->database_edit_access = (bool) ($validated['database_edit_access'] ?? false);
        $user->database_add_access = (bool) ($validated['database_add_access'] ?? false);
        $user->database_delete_access = (bool) ($validated['database_delete_access'] ?? false);
        $user->database_export_access = (bool) ($validated['database_export_access'] ?? false);
        $user->database_sql_query_access = (bool) ($validated['database_sql_query_access'] ?? false);
        $user->database_sql_destructive_access = (bool) ($validated['database_sql_destructive_access'] ?? false);
        $user->database_full_backup_access = (bool) ($validated['database_full_backup_access'] ?? false);

        $user->save();

        if (TenantContext::isResolved()) {
            SiteUserMembership::query()->updateOrCreate(
                [
                    'site_id' => TenantContext::siteId(),
                    'user_id' => $user->id,
                ],
                [
                    'is_active' => true,
                    'admin_locale' => $validated['admin_locale'] ?? null,
                    'allowed_content_locales' => array_values((array) ($validated['allowed_content_locales'] ?? [])),
                ],
            );
        }

        $user->roles()->sync($validated['role_ids'] ?? []);

        $this->auditLogger->success(
            action: $isCreate ? 'user.create' : 'user.update',
            module: 'security',
            subjectType: 'user',
            subjectKey: (string) $user->id,
            message: __('admin_security_ui.users.feedback_saved'),
            meta: [
                'email' => (string) $user->email,
                'role_count' => count((array) ($validated['role_ids'] ?? [])),
                'database_view_access' => (bool) ($validated['database_view_access'] ?? false),
                'database_edit_access' => (bool) ($validated['database_edit_access'] ?? false),
                'database_add_access' => (bool) ($validated['database_add_access'] ?? false),
                'database_delete_access' => (bool) ($validated['database_delete_access'] ?? false),
                'database_export_access' => (bool) ($validated['database_export_access'] ?? false),
                'database_sql_query_access' => (bool) ($validated['database_sql_query_access'] ?? false),
                'database_sql_destructive_access' => (bool) ($validated['database_sql_destructive_access'] ?? false),
                'database_full_backup_access' => (bool) ($validated['database_full_backup_access'] ?? false),
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.users.edit', ['id' => $user->id])
            ->with('status', __('admin_security_ui.users.feedback_saved'));
    }
}
