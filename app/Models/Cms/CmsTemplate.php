<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'import_key',
        'name',
        'locale',
        'translation_key',
        'translated_from_template_id',
        'layout_id',
        'template_class',
        'template_key',
        'module_key',
        'is_default',
        'is_active',
        'cache_strategy',
        'settings',
        'data_contract',
    ];

    public function translatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'translated_from_template_id');
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(CmsLayout::class, 'layout_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(self::class, 'translation_key', 'translation_key');
    }

    public function sections(): MorphMany
    {
        return $this->morphMany(CmsSection::class, 'owner')->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefaultFor(Builder $query, string $templateKey, string $locale): Builder
    {
        return $query
            ->where('template_key', $templateKey)
            ->where('locale', $locale)
            ->where('is_default', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'layout_id' => 'integer',
            'settings' => 'array',
            'data_contract' => 'array',
        ];
    }
}
