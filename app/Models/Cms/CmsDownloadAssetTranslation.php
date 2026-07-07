<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsDownloadAssetTranslation extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'cms_download_asset_id',
        'locale',
        'title',
        'description',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmsDownloadAsset::class, 'cms_download_asset_id');
    }
}
