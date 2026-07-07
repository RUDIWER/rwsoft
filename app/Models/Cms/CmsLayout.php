<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsLayout extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'import_key',
        'name',
        'locale',
        'translation_key',
        'translated_from_layout_id',
        'is_default',
        'is_active',
        'cache_strategy',
        'settings',
    ];

    public function translatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'translated_from_layout_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(self::class, 'translation_key', 'translation_key');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(CmsTemplate::class, 'layout_id');
    }

    public function sections(): MorphMany
    {
        return $this->morphMany(CmsSection::class, 'owner')->orderBy('sort_order');
    }

    public function headerSections(): MorphMany
    {
        return $this->sections()->where('zone', 'header');
    }

    public function footerSections(): MorphMany
    {
        return $this->sections()->where('zone', 'footer');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefaultForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale)->where('is_default', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }
}
