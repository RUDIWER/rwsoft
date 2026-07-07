<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsPlaceableBlock extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'name',
        'description',
        'category',
        'source',
        'status',
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
        'created_by',
        'updated_by',
        'published_at',
    ];

    public function revisions(): HasMany
    {
        return $this->hasMany(CmsPlaceableBlockRevision::class)->orderByDesc('revision_number');
    }

    public function latestPublishedRevision(): HasOne
    {
        return $this->hasOne(CmsPlaceableBlockRevision::class)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->latestOfMany('revision_number');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(CmsBlock::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_zones' => 'array',
            'schema' => 'array',
            'defaults' => 'array',
            'capabilities' => 'array',
            'behavior_config' => 'array',
            'context_config' => 'array',
            'sort_order' => 'integer',
            'is_locked' => 'boolean',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
