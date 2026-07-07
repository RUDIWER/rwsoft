<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsPage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'detail_template_id',
        'author_id',
        'title',
        'slug',
        'locale',
        'translation_key',
        'translated_from_page_id',
        'status',
        'template',
        'short_description',
        'content_blocks',
        'template_data',
        'seo_title',
        'seo_description',
        'canonical_url',
        'og_image_path',
        'noindex',
        'is_home',
        'is_searchable',
        'sort_order',
        'published_at',
        'settings',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function detailTemplate(): BelongsTo
    {
        return $this->belongsTo(CmsTemplate::class, 'detail_template_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function translatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'translated_from_page_id');
    }

    public function translatedPages(): HasMany
    {
        return $this->hasMany(self::class, 'translation_key', 'translation_key')
            ->whereKeyNot($this->getKey())
            ->orderBy('locale')
            ->orderBy('title');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(CmsMenuItem::class, 'cms_page_id');
    }

    public function formSubmissions(): HasMany
    {
        return $this->hasMany(CmsFormSubmission::class, 'cms_page_id');
    }

    public function blockOverrides(): HasMany
    {
        return $this->hasMany(CmsBlockOverride::class, 'cms_page_id');
    }

    public function blockExclusions(): HasMany
    {
        return $this->hasMany(CmsBlockExclusion::class, 'cms_page_id');
    }

    public function sections(): MorphMany
    {
        return $this->morphMany(CmsSection::class, 'owner')->orderBy('sort_order');
    }

    public function contentSections(): MorphMany
    {
        return $this->sections()->where('zone', 'content');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'template_data' => 'array',
            'settings' => 'array',
            'detail_template_id' => 'integer',
            'noindex' => 'boolean',
            'is_home' => 'boolean',
            'is_searchable' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
