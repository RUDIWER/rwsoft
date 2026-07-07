<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'author_id',
        'featured_media_asset_id',
        'detail_template_id',
        'title',
        'slug',
        'locale',
        'translation_key',
        'translated_from_post_id',
        'status',
        'excerpt',
        'content_blocks',
        'seo_title',
        'seo_description',
        'canonical_url',
        'og_image_path',
        'noindex',
        'is_featured',
        'is_searchable',
        'published_at',
        'settings',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(CmsMediaAsset::class, 'featured_media_asset_id');
    }

    public function detailTemplate(): BelongsTo
    {
        return $this->belongsTo(CmsTemplate::class, 'detail_template_id');
    }

    public function translatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'translated_from_post_id');
    }

    public function translatedPosts(): HasMany
    {
        return $this->hasMany(self::class, 'translation_key', 'translation_key')
            ->whereKeyNot($this->getKey())
            ->orderBy('locale')
            ->orderBy('title');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(CmsCategory::class, 'cms_post_category', 'cms_post_id', 'cms_category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CmsTag::class, 'cms_post_tag', 'cms_post_id', 'cms_tag_id');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(CmsMenuItem::class, 'cms_post_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'settings' => 'array',
            'detail_template_id' => 'integer',
            'noindex' => 'boolean',
            'is_featured' => 'boolean',
            'is_searchable' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
