<?php

namespace App\Models\Cms;

use App\Models\PublicSite\SiteUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CmsDownloadGroup extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
        'settings',
    ];

    public function siteUsers(): BelongsToMany
    {
        return $this->belongsToMany(SiteUser::class, 'cms_download_group_site_user')
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }
}
