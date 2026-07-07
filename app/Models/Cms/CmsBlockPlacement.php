<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsBlockPlacement extends Model
{
    protected $fillable = [
        'import_key',
        'revision_key',
        'cms_section_id',
        'parent_placement_id',
        'slot_key',
        'cms_block_id',
        'sort_order',
        'is_active',
        'visible_mobile',
        'visible_tablet',
        'visible_desktop',
        'mobile_span',
        'tablet_span',
        'desktop_span',
        'layout_config',
        'style_config',
        'published_style_revision_id',
        'height_mode',
        'height_value',
        'cache_strategy',
        'settings',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(CmsSection::class, 'cms_section_id');
    }

    public function parentPlacement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_placement_id');
    }

    public function childPlacements(): HasMany
    {
        return $this->hasMany(self::class, 'parent_placement_id')->orderBy('sort_order');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(CmsBlock::class, 'cms_block_id');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(CmsBlockOverride::class, 'cms_block_placement_id');
    }

    public function exclusions(): HasMany
    {
        return $this->hasMany(CmsBlockExclusion::class, 'cms_block_placement_id');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(CmsSharedBlockScope::class, 'cms_block_placement_id')->orderBy('sort_order');
    }

    public function styleRevisions(): HasMany
    {
        return $this->hasMany(CmsBlockPlacementStyleRevision::class, 'cms_block_placement_id')->orderByDesc('revision_number');
    }

    public function publishedStyleRevision(): BelongsTo
    {
        return $this->belongsTo(CmsBlockPlacementStyleRevision::class, 'published_style_revision_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_placement_id');
    }

    public function scopeChildren(Builder $query): Builder
    {
        return $query->whereNotNull('parent_placement_id');
    }

    public function scopeForSlot(Builder $query, string $slotKey): Builder
    {
        return $query->where('slot_key', $slotKey);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'parent_placement_id' => 'integer',
            'is_active' => 'boolean',
            'visible_mobile' => 'boolean',
            'visible_tablet' => 'boolean',
            'visible_desktop' => 'boolean',
            'mobile_span' => 'integer',
            'tablet_span' => 'integer',
            'desktop_span' => 'integer',
            'layout_config' => 'array',
            'style_config' => 'array',
            'published_style_revision_id' => 'integer',
            'settings' => 'array',
        ];
    }
}
