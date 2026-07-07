<?php

namespace App\Support\Security;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TenantAcl
{
    public function hasRoleKey(User $user, string $roleKey): bool
    {
        if (! TenantContext::isResolved() || ! $this->tablesExist()) {
            return false;
        }

        try {
            return DB::connection('tenant')
                ->table('acl_roles')
                ->join('acl_role_user', 'acl_role_user.acl_role_id', '=', 'acl_roles.id')
                ->where('acl_role_user.user_id', $user->id)
                ->where('acl_roles.key', $roleKey)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function canAccessRoute(User $user, string $routeName): bool
    {
        if (! TenantContext::isResolved() || ! $this->tablesExist()) {
            return false;
        }

        if ($this->hasRoleKey($user, 'super_admin')) {
            return true;
        }

        try {
            return DB::connection('tenant')
                ->table('acl_permissions')
                ->join('acl_permission_role', 'acl_permission_role.acl_permission_id', '=', 'acl_permissions.id')
                ->join('acl_role_user', 'acl_role_user.acl_role_id', '=', 'acl_permission_role.acl_role_id')
                ->where('acl_role_user.user_id', $user->id)
                ->where('acl_permissions.route_name', $routeName)
                ->where('acl_permission_role.active', true)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    public function allowedRouteNames(User $user): array
    {
        if (! TenantContext::isResolved() || ! $this->tablesExist()) {
            return [];
        }

        try {
            return DB::connection('tenant')
                ->table('acl_permissions')
                ->select('acl_permissions.route_name')
                ->join('acl_permission_role', 'acl_permission_role.acl_permission_id', '=', 'acl_permissions.id')
                ->join('acl_role_user', 'acl_role_user.acl_role_id', '=', 'acl_permission_role.acl_role_id')
                ->where('acl_role_user.user_id', $user->id)
                ->where('acl_permission_role.active', true)
                ->distinct()
                ->pluck('acl_permissions.route_name')
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function tablesExist(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('acl_roles')
                && Schema::connection('tenant')->hasTable('acl_permissions')
                && Schema::connection('tenant')->hasTable('acl_permission_role')
                && Schema::connection('tenant')->hasTable('acl_role_user');
        } catch (Throwable) {
            return false;
        }
    }
}
