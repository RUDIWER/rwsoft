<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsDownloadEvent extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'cms_download_asset_id',
        'site_user_id',
        'event',
        'ip_hash',
        'user_agent_hash',
        'metadata',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmsDownloadAsset::class, 'cms_download_asset_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
