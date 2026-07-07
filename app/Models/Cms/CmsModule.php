<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'name',
    'status',
    'settings',
    'installed_at',
])]
class CmsModule extends Model
{
    protected $connection = 'tenant';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'installed_at' => 'datetime',
        ];
    }
}
