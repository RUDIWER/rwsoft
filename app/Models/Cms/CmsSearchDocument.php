<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsSearchDocument extends Model
{
    protected $fillable = [
        'source_type',
        'source_key',
        'source_id',
        'locale',
        'title',
        'slug',
        'summary',
        'canonical_path',
        'canonical_url',
        'markdown_path',
        'markdown_url',
        'source_updated_at',
        'published_at',
        'is_active',
        'is_searchable',
        'noindex',
        'markdown_hash',
        'plain_text_hash',
        'markdown',
        'plain_text',
        'metadata',
        'indexed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'source_updated_at' => 'datetime',
            'published_at' => 'datetime',
            'is_active' => 'boolean',
            'is_searchable' => 'boolean',
            'noindex' => 'boolean',
            'metadata' => 'array',
            'indexed_at' => 'datetime',
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(CmsSearchChunk::class);
    }
}
