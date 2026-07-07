<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsMenuItem extends Model
{
    protected $fillable = [
        'cms_menu_id',
        'locale',
        'translation_key',
        'translated_from_menu_item_id',
        'parent_id',
        'cms_page_id',
        'cms_post_id',
        'type',
        'label',
        'url',
        'target',
        'rel',
        'sort_order',
        'is_active',
        'metadata',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(CmsMenu::class, 'cms_menu_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function translatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'translated_from_menu_item_id');
    }

    public function translatedItems(): HasMany
    {
        return $this->hasMany(self::class, 'translation_key', 'translation_key')
            ->whereKeyNot($this->getKey())
            ->orderBy('locale')
            ->orderBy('sort_order');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'cms_page_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CmsPost::class, 'cms_post_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
