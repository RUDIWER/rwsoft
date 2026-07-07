<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsBlockOverride extends Model
{
    protected $fillable = [
        'cms_page_id',
        'cms_block_placement_id',
        'content',
        'settings',
        'is_active',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'cms_page_id');
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(CmsBlockPlacement::class, 'cms_block_placement_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
