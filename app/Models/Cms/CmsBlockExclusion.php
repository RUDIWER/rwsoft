<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsBlockExclusion extends Model
{
    protected $fillable = [
        'cms_page_id',
        'cms_block_placement_id',
        'reason',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'cms_page_id');
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(CmsBlockPlacement::class, 'cms_block_placement_id');
    }
}
