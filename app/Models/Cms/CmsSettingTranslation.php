<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsSettingTranslation extends Model
{
    protected $fillable = [
        'cms_setting_id',
        'locale',
        'value',
    ];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(CmsSetting::class, 'cms_setting_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
