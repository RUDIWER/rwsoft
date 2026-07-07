<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsDownloadAsset extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'folder_id',
        'uploaded_by',
        'disk',
        'visibility',
        'access_mode',
        'path',
        'filename',
        'original_filename',
        'mime_type',
        'extension',
        'size_bytes',
        'hash',
        'title',
        'description',
        'published_at',
        'expires_at',
        'metadata',
        'sort_order',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(CmsDownloadFolder::class, 'folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CmsDownloadAssetTranslation::class, 'cms_download_asset_id');
    }

    public function accessRules(): HasMany
    {
        return $this->hasMany(CmsDownloadAccessRule::class, 'subject_id')
            ->where('subject_type', 'asset')
            ->orderBy('sort_order');
    }

    public function translationForLocale(string $locale): ?CmsDownloadAssetTranslation
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
            'metadata' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }
}
