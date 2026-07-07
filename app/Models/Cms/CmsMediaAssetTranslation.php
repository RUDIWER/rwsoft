<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsMediaAssetTranslation extends Model
{
    protected $fillable = [
        'cms_media_asset_id',
        'locale',
        'alt_text',
        'caption',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmsMediaAsset::class, 'cms_media_asset_id');
    }
}
