<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsFormField extends Model
{
    protected $fillable = [
        'cms_form_id',
        'type',
        'translation_key',
        'translated_from_form_field_id',
        'label',
        'placeholder',
        'help_text',
        'options',
        'validation_rules',
        'sort_order',
        'is_required',
        'is_active',
        'width',
        'settings',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(CmsForm::class, 'cms_form_id');
    }

    public function translatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'translated_from_form_field_id');
    }

    public function translatedFields(): HasMany
    {
        return $this->hasMany(self::class, 'translation_key', 'translation_key')
            ->whereKeyNot($this->getKey())
            ->orderBy('cms_form_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CmsFormSubmissionValue::class, 'cms_form_field_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'validation_rules' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }
}
