<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsVisitor extends Model
{
    protected $fillable = [
        'uuid',
        'ip',
        'ip_hash',
        'geo_checked',
        'country_code',
        'country_name',
        'region_code',
        'region_name',
        'city_name',
        'zip_code',
        'latitude',
        'longitude',
        'timezone',
        'first_seen_at',
        'last_seen_at',
    ];

    public function visits(): HasMany
    {
        return $this->hasMany(CmsVisit::class, 'cms_visitor_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'geo_checked' => 'integer',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
