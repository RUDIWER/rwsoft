<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsDocVersion extends Model
{
    protected $fillable = [
        'cms_doc_collection_id',
        'label',
        'slug',
        'is_default',
        'is_active',
        'sort_order',
        'settings',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(CmsDocCollection::class, 'cms_doc_collection_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(CmsDocPage::class)->orderBy('sort_order')->orderBy('title');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cms_doc_collection_id' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'settings' => 'array',
        ];
    }
}
