<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsEmail extends Model
{
    protected $fillable = [
        'cms_mail_template_id',
        'title',
        'locale',
        'translation_key',
        'email_type',
        'system_key',
        'context_key',
        'subject',
        'preheader',
        'content_blocks',
        'plain_text',
        'settings',
        'is_active',
    ];

    public function mailTemplate(): BelongsTo
    {
        return $this->belongsTo(CmsMailTemplate::class, 'cms_mail_template_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CmsEmailDelivery::class, 'cms_email_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSystemKey(Builder $query, string $systemKey, string $locale): Builder
    {
        return $query
            ->where('email_type', 'system')
            ->where('system_key', $systemKey)
            ->where('locale', $locale);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_blocks' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
