<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsSetting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'label',
        'type',
        'value',
        'is_public',
        'sort_order',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(CmsSettingTranslation::class, 'cms_setting_id');
    }

    /**
     * @return array<string, mixed>
     */
    public static function contactPayload(): array
    {
        return self::query()
            ->where('group', 'contact')
            ->get(['key', 'value'])
            ->mapWithKeys(fn (self $setting): array => [
                $setting->key => $setting->value['value'] ?? null,
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }
}
