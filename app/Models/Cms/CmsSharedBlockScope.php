<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsSharedBlockScope extends Model
{
    protected $fillable = [
        'cms_block_placement_id',
        'scope_type',
        'scope_value',
        'locale',
        'is_active',
        'sort_order',
        'settings',
    ];

    public function placement(): BelongsTo
    {
        return $this->belongsTo(CmsBlockPlacement::class, 'cms_block_placement_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForScope(Builder $query, string $scopeType, ?string $scopeValue = null): Builder
    {
        return $query->where('scope_type', $scopeType)->where('scope_value', $scopeValue);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'settings' => 'array',
        ];
    }
}
