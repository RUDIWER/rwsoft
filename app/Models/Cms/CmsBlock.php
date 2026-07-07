<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsBlock extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'import_key',
        'revision_key',
        'cms_placeable_block_id',
        'placeable_block_revision_id',
        'type',
        'name',
        'content',
        'settings',
        'is_shared',
        'is_dynamic',
        'cache_strategy',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(CmsBlockPlacement::class, 'cms_block_id');
    }

    public function placeableBlock(): BelongsTo
    {
        return $this->belongsTo(CmsPlaceableBlock::class, 'cms_placeable_block_id');
    }

    public function placeableBlockRevision(): BelongsTo
    {
        return $this->belongsTo(CmsPlaceableBlockRevision::class, 'placeable_block_revision_id');
    }

    public function scopeShared(Builder $query): Builder
    {
        return $query->where('is_shared', true);
    }

    public function scopeStatic(Builder $query): Builder
    {
        return $query->where('is_dynamic', false);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'settings' => 'array',
            'cms_placeable_block_id' => 'integer',
            'placeable_block_revision_id' => 'integer',
            'is_shared' => 'boolean',
            'is_dynamic' => 'boolean',
            'created_by' => 'integer',
        ];
    }
}
