<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CmsTag extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'locale',
        'translation_key',
        'translated_from_tag_id',
        'landing_page_id',
        'archive_template_id',
        'detail_template_id',
        'description',
        'is_active',
        'settings',
    ];

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

    public function translatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'translated_from_tag_id');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(CmsPost::class, 'cms_post_tag', 'cms_tag_id', 'cms_post_id');
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
