<?php

namespace App\Models\Security;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AclPermissionAction extends Model
{
    protected $fillable = [
        'id',
        'key',
        'name',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(AclPermission::class, 'action_id');
    }
}
