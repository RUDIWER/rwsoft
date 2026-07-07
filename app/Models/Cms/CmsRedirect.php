<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;

class CmsRedirect extends Model
{
    protected $fillable = [
        'import_key',
        'source_path',
        'target_url',
        'status_code',
        'locale',
        'is_active',
        'starts_at',
        'ends_at',
        'hit_count',
        'last_hit_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_hit_at' => 'datetime',
        ];
    }
}
