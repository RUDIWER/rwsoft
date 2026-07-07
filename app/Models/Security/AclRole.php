<?php

namespace App\Models\Security;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AclRole extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'acl_role_user', 'acl_role_id', 'user_id')
            ->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(AclPermission::class, 'acl_permission_role', 'acl_role_id', 'acl_permission_id')
            ->withPivot('active')
            ->withTimestamps();
    }
}
