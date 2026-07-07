<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsBlockPlacementStyleRevision extends Model
{
    protected $fillable = [
        'cms_block_placement_id',
        'revision_number',
        'status',
        'title',
        'style_config',
        'css_source',
        'snapshot_hash',
        'author_id',
        'metadata',
        'published_at',
    ];

    public function placement(): BelongsTo
    {
        return $this->belongsTo(CmsBlockPlacement::class, 'cms_block_placement_id');
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
            'style_config' => 'array',
            'metadata' => 'array',
            'author_id' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
