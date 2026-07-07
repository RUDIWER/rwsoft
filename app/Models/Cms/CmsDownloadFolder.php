<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsDownloadFolder extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'access_mode',
        'password_hash',
        'password_expires_minutes',
        'settings',
        'sort_order',
    ];

    protected $hidden = [
        'password_hash',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(CmsDownloadAsset::class, 'folder_id')->orderBy('sort_order');
    }

    public function accessRules(): HasMany
    {
        return $this->hasMany(CmsDownloadAccessRule::class, 'subject_id')
            ->where('subject_type', 'folder')
            ->orderBy('sort_order');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'password_expires_minutes' => 'integer',
        ];
    }
}
