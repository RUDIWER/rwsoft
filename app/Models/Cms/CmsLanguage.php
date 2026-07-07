<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsLanguage extends Model
{
    protected $fillable = [
        'locale',
        'name',
        'native_name',
        'flag_media_asset_id',
        'direction',
        'is_active',
        'sort_order',
    ];

    public function flagMediaAsset(): BelongsTo
    {
        return $this->belongsTo(CmsMediaAsset::class, 'flag_media_asset_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'flag_media_asset_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
