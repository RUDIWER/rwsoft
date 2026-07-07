<?php

namespace App\Models\PublicSite;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'key',
    'label',
    'type',
    'options',
    'validation_rules',
    'is_required',
    'is_active',
    'show_on_register',
    'show_on_profile',
    'sort_order',
    'settings',
])]
class SiteUserProfileFieldDefinition extends Model
{
    protected $connection = 'tenant';

    public function values(): HasMany
    {
        return $this->hasMany(SiteUserProfileFieldValue::class);
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
            'show_on_register' => 'boolean',
            'show_on_profile' => 'boolean',
            'settings' => 'array',
        ];
    }
}
