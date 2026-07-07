<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsPublicText extends Model
{
    protected $fillable = [
        'group',
        'key',
        'label',
        'description',
        'default_value',
        'type',
        'is_system',
        'sort_order',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(CmsPublicTextTranslation::class, 'cms_public_text_id')
            ->orderBy('locale');
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
