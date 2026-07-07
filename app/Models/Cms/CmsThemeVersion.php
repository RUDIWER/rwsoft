<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsThemeVersion extends Model
{
    protected $fillable = [
        'cms_theme_id',
        'version_hash',
        'developer_css_path',
        'generated_css_path',
        'minified_css_path',
        'settings',
        'source_manifest',
        'external_assets',
        'file_size_kb',
        'published_at',
        'created_by',
    ];

    public function theme(): BelongsTo
    {
        return $this->belongsTo(CmsTheme::class, 'cms_theme_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'source_manifest' => 'array',
            'external_assets' => 'array',
            'file_size_kb' => 'integer',
            'published_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }
}
