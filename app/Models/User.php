<?php

namespace App\Models;

use App\Models\Platform\SiteUserMembership;
use App\Models\Security\AclRole;
use App\Support\Security\TenantAcl;
use App\Support\Tenancy\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'name',
    'email',
    'password',
    'is_platform_admin',
    'database_view_access',
    'database_edit_access',
    'database_add_access',
    'database_delete_access',
    'database_export_access',
    'database_sql_query_access',
    'database_sql_destructive_access',
    'database_full_backup_access',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $connection = 'central';

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AclRole::class, 'acl_role_user', 'user_id', 'acl_role_id')
            ->withTimestamps();
    }

    public function siteMemberships(): HasMany
    {
        return $this->hasMany(SiteUserMembership::class);
    }

    public function hasRoleKey(string $roleKey): bool
    {
        if (TenantContext::isResolved()) {
            return app(TenantAcl::class)->hasRoleKey($this, $roleKey);
        }

        if (! Schema::hasTable('acl_roles') || ! Schema::hasTable('acl_role_user')) {
            return false;
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(static function (AclRole $role) use ($roleKey): bool {
                return $role->key === $roleKey;
            });
        }

        return $this->roles()->where('key', $roleKey)->exists();
    }

    public function canAccessRoute(string $routeName): bool
    {
        if (TenantContext::isResolved()) {
            return app(TenantAcl::class)->canAccessRoute($this, $routeName);
        }

        if (! Schema::hasTable('acl_roles') || ! Schema::hasTable('acl_permissions') || ! Schema::hasTable('acl_permission_role') || ! Schema::hasTable('acl_role_user')) {
            return false;
        }

        if ($this->hasRoleKey('super_admin')) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($routeName): void {
                $query
                    ->where('route_name', $routeName)
                    ->where('acl_permission_role.active', true);
            })
            ->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'database_view_access' => 'boolean',
            'database_edit_access' => 'boolean',
            'database_add_access' => 'boolean',
            'database_delete_access' => 'boolean',
            'database_export_access' => 'boolean',
            'database_sql_query_access' => 'boolean',
            'database_sql_destructive_access' => 'boolean',
            'database_full_backup_access' => 'boolean',
        ];
    }
}
