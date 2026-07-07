<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsSearchChunk extends Model
{
    protected $fillable = [
        'cms_search_document_id',
        'chunk_index',
        'heading',
        'anchor',
        'content_markdown',
        'content_text',
        'token_count',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cms_search_document_id' => 'integer',
            'chunk_index' => 'integer',
            'token_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CmsSearchDocument::class, 'cms_search_document_id');
    }
}
