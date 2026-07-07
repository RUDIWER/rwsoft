<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsCategory extends Model
{
    protected $fillable = [
        'parent_id',
        'type',
        'title',
        'slug',
        'locale',
        'translation_key',
        'translated_from_category_id',
        'landing_page_id',
        'archive_template_id',
        'detail_template_id',
        'description',
        'sort_order',
        'is_active',
        'settings',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function translatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'translated_from_category_id');
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'landing_page_id');
    }

    public function archiveTemplate(): BelongsTo
    {
        return $this->belongsTo(CmsTemplate::class, 'archive_template_id');
    }

    public function detailTemplate(): BelongsTo
    {
        return $this->belongsTo(CmsTemplate::class, 'detail_template_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(CmsPost::class, 'cms_post_category', 'cms_category_id', 'cms_post_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'archive_template_id' => 'integer',
            'detail_template_id' => 'integer',
        ];
    }
}
