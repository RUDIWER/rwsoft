<?php

namespace App\Http\Controllers\Admin\Base;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Base\StorePermissionRequest;
use App\Models\Security\AclPermission;
use App\Models\Security\AclPermissionAction;
use App\Models\Security\AclPermissionModule;
use App\Models\Security\AclPermissionType;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Permissions/Index', [
            'permissions' => AclPermission::query()
                ->with(['module:id,key,name', 'action:id,key,name', 'type:id,key,name'])
                ->orderBy('route_name')
                ->get(['id', 'route_name', 'description', 'module_id', 'action_id', 'type_id', 'menu']),
        ]);
    }

    public function edit(int $id): Response
    {
        $permission = $id > 0
            ? AclPermission::query()->findOrFail($id)
            : null;

        return Inertia::render('Admin/Permissions/Edit', [
            'permission' => $permission ? [
                'id' => $permission->id,
                'route_name' => $permission->route_name,
                'description' => $permission->description,
                'module_id' => $permission->module_id,
                'action_id' => $permission->action_id,
                'type_id' => $permission->type_id,
                'query_id' => $permission->query_id,
                'menu' => (bool) $permission->menu,
                'url' => $permission->url,
                'created_at' => $permission->created_at?->toISOString(),
                'updated_at' => $permission->updated_at?->toISOString(),
            ] : null,
            'modules' => $this->permissionModules(),
            'actions' => $this->permissionActions(),
            'types' => $this->permissionTypes(),
        ]);
    }

    public function store(StorePermissionRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();

        $permission = $id > 0
            ? AclPermission::query()->findOrFail($id)
            : new AclPermission;
        $isCreate = ! $permission->exists;

        $validated['menu'] = (bool) ($validated['menu'] ?? false);

        $permission->fill($validated);
        $permission->save();
        $permission->loadMissing(['module:id,name', 'action:id,name', 'type:id,name']);

        $this->auditLogger->success(
            action: $isCreate ? 'permission.create' : 'permission.update',
            module: 'security',
            subjectType: 'permission',
            subjectKey: (string) $permission->id,
            message: __('admin_security_ui.permissions.feedback_saved'),
            meta: [
                'route_name' => (string) $permission->route_name,
                'module_id' => $permission->module_id,
                'module' => (string) ($permission->module?->name ?? ''),
                'action_id' => $permission->action_id,
                'action_name' => (string) ($permission->action?->name ?? ''),
                'type_id' => $permission->type_id,
                'type_name' => (string) ($permission->type?->name ?? ''),
                'menu' => (bool) $permission->menu,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.permissions.edit', ['id' => $permission->id])
            ->with('status', __('admin_security_ui.permissions.feedback_saved'));
    }

    /**
     * @return array<int, array{id:int, key:string, name:string}>
     */
    private function permissionModules(): array
    {
        return AclPermissionModule::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'key', 'name'])
            ->map(fn (AclPermissionModule $module): array => [
                'id' => (int) $module->id,
                'key' => (string) $module->key,
                'name' => (string) $module->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int, key:string, name:string}>
     */
    private function permissionActions(): array
    {
        return AclPermissionAction::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'key', 'name'])
            ->map(fn (AclPermissionAction $action): array => [
                'id' => (int) $action->id,
                'key' => (string) $action->key,
                'name' => (string) $action->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int, key:string, name:string}>
     */
    private function permissionTypes(): array
    {
        return AclPermissionType::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'key', 'name'])
            ->map(fn (AclPermissionType $type): array => [
                'id' => (int) $type->id,
                'key' => (string) $type->key,
                'name' => (string) $type->name,
            ])
            ->all();
    }
}
