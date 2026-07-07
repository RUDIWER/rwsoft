<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsMediaAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'folder_id',
        'uploaded_by',
        'disk',
        'visibility',
        'asset_kind',
        'source_media_asset_id',
        'context_type',
        'context_id',
        'path',
        'filename',
        'original_filename',
        'mime_type',
        'extension',
        'size_bytes',
        'width',
        'height',
        'hash',
        'alt_text',
        'caption',
        'focal_point',
        'metadata',
        'sort_order',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(CmsMediaFolder::class, 'folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CmsMediaAssetTranslation::class, 'cms_media_asset_id');
    }

    public function translationForLocale(string $locale): ?CmsMediaAssetTranslation
    {
        $locale = trim($locale);

        if ($locale === '') {
            return null;
        }

        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('locale', $locale);
        }

        return $this->translations()->where('locale', $locale)->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'focal_point' => 'array',
            'metadata' => 'array',
            'source_media_asset_id' => 'integer',
            'context_id' => 'integer',
        ];
    }
}
