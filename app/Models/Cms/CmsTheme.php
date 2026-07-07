<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsTheme extends Model
{
    protected $fillable = [
        'import_key',
        'key',
        'name',
        'description',
        'author',
        'version',
        'status',
        'is_active',
        'active_version_id',
        'created_by',
        'updated_by',
    ];

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(CmsThemeVersion::class, 'active_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CmsThemeVersion::class)->latest('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'active_version_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }
}
