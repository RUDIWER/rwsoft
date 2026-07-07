<?php

namespace App\Http\Controllers\Admin\Base;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Base\StoreRoleRequest;
use App\Models\Security\AclPermission;
use App\Models\Security\AclRole;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Roles/Index', [
            'roles' => AclRole::query()
                ->withCount('users')
                ->withCount('permissions')
                ->orderBy('name')
                ->get(['id', 'key', 'name', 'description']),
        ]);
    }

    public function edit(int $id): Response
    {
        $role = $id > 0
            ? AclRole::query()->with('permissions:id')->findOrFail($id)
            : null;

        return Inertia::render('Admin/Roles/Edit', [
            'role' => $role ? [
                'id' => $role->id,
                'key' => $role->key,
                'name' => $role->name,
                'description' => $role->description,
                'created_at' => $role->created_at?->toISOString(),
                'updated_at' => $role->updated_at?->toISOString(),
                'permission_ids' => $role->permissions->pluck('id')->values(),
            ] : null,
            'permissions' => AclPermission::query()
                ->orderBy('route_name')
                ->get(['id', 'route_name', 'description']),
        ]);
    }

    public function store(StoreRoleRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();

        $role = $id > 0
            ? AclRole::query()->findOrFail($id)
            : new AclRole;
        $isCreate = ! $role->exists;

        $role->fill([
            'key' => $validated['key'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);
        $role->save();

        $permissionIds = collect($validated['permission_ids'] ?? [])
            ->mapWithKeys(fn (int $permissionId): array => [$permissionId => ['active' => true]])
            ->all();

        $role->permissions()->sync($permissionIds);

        $this->auditLogger->success(
            action: $isCreate ? 'role.create' : 'role.update',
            module: 'security',
            subjectType: 'role',
            subjectKey: (string) $role->id,
            message: __('admin_security_ui.roles.feedback_saved'),
            meta: [
                'key' => (string) $role->key,
                'permission_count' => count($permissionIds),
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.roles.edit', ['id' => $role->id])
            ->with('status', __('admin_security_ui.roles.feedback_saved'));
    }
}
