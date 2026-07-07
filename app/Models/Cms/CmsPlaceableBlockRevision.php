<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsPlaceableBlockRevision extends Model
{
    protected $fillable = [
        'cms_placeable_block_id',
        'revision_number',
        'status',
        'title',
        'category',
        'source',
        'allowed_zones',
        'rendering_mode',
        'renderer_key',
        'template_source',
        'css_source',
        'schema',
        'defaults',
        'capabilities',
        'behavior_config',
        'context_config',
        'admin_component_key',
        'package_key',
        'sort_order',
        'is_locked',
        'requires_permission',
        'snapshot_hash',
        'author_id',
        'metadata',
        'published_at',
    ];

    public function placeableBlock(): BelongsTo
    {
        return $this->belongsTo(CmsPlaceableBlock::class, 'cms_placeable_block_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'allowed_zones' => 'array',
            'schema' => 'array',
            'defaults' => 'array',
            'capabilities' => 'array',
            'behavior_config' => 'array',
            'context_config' => 'array',
            'metadata' => 'array',
            'author_id' => 'integer',
            'sort_order' => 'integer',
            'is_locked' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
