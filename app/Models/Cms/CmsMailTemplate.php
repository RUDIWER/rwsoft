<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CmsMailTemplate extends Model
{
    protected $fillable = [
        'name',
        'key',
        'description',
        'context_key',
        'body_blocks',
        'settings',
        'is_active',
    ];

    public function emails(): HasMany
    {
        return $this->hasMany(CmsEmail::class, 'cms_mail_template_id');
    }

    public function sections(): MorphMany
    {
        return $this->morphMany(CmsSection::class, 'owner')->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'body_blocks' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
