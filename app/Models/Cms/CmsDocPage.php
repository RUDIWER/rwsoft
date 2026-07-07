<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsDocPage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cms_doc_version_id',
        'parent_id',
        'author_id',
        'title',
        'slug',
        'path',
        'locale',
        'translation_key',
        'translated_from_doc_page_id',
        'status',
        'body_format',
        'body',
        'plain_text',
        'seo_title',
        'seo_description',
        'noindex',
        'sort_order',
        'published_at',
        'settings',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(CmsDocVersion::class, 'cms_doc_version_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('title');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function translatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'translated_from_doc_page_id');
    }

    public function translatedPages(): HasMany
    {
        return $this->hasMany(self::class, 'translation_key', 'translation_key')
            ->whereKeyNot($this->getKey())
            ->orderBy('locale')
            ->orderBy('title');
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(CmsRevision::class, 'subject')->orderByDesc('revision_number');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cms_doc_version_id' => 'integer',
            'parent_id' => 'integer',
            'author_id' => 'integer',
            'translated_from_doc_page_id' => 'integer',
            'noindex' => 'boolean',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
            'settings' => 'array',
        ];
    }
}
