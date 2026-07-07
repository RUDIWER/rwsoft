<?php

namespace App\Models\Security;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AclPermission extends Model
{
    protected $fillable = [
        'route_name',
        'description',
        'module_id',
        'action_id',
        'type_id',
        'query_id',
        'menu',
        'url',
    ];

    protected $casts = [
        'menu' => 'boolean',
        'module_id' => 'integer',
        'action_id' => 'integer',
        'type_id' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(AclPermissionModule::class, 'module_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(AclPermissionAction::class, 'action_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AclPermissionType::class, 'type_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AclRole::class, 'acl_permission_role', 'acl_permission_id', 'acl_role_id')
            ->withPivot('active')
            ->withTimestamps();
    }
}
