<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsMenu extends Model
{
    protected $fillable = [
        'title',
        'placements',
        'is_active',
        'settings',
    ];

    public function availableForPlacement(string $placement): bool
    {
        return in_array($placement, $this->placements ?? [], true);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CmsMenuItem::class, 'cms_menu_id')->orderBy('sort_order');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CmsMenuTranslation::class, 'cms_menu_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'placements' => 'array',
            'settings' => 'array',
        ];
    }
}
