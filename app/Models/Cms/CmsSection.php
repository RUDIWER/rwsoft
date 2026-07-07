<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CmsSection extends Model
{
    protected $fillable = [
        'import_key',
        'revision_key',
        'owner_type',
        'owner_id',
        'zone',
        'name',
        'sort_order',
        'is_active',
        'visible_mobile',
        'visible_tablet',
        'visible_desktop',
        'settings',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function placements(): HasMany
    {
        return $this->hasMany(CmsBlockPlacement::class, 'cms_section_id')->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeZone(Builder $query, string $zone): Builder
    {
        return $query->where('zone', $zone);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'visible_mobile' => 'boolean',
            'visible_tablet' => 'boolean',
            'visible_desktop' => 'boolean',
            'settings' => 'array',
        ];
    }
}
