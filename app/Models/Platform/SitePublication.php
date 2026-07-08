<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SitePublication extends Model
{
    protected $connection = 'central';

    protected $table = 'platform_site_publications';

    protected $fillable = [
        'site_id',
        'hosting_environment_id',
        'remote_site_slug',
        'remote_domain',
        'remote_tenant_database_mode',
        'remote_tenant_database',
        'remote_tenant_table_prefix',
        'remote_site_id',
        'status',
        'last_push_at',
        'last_pull_at',
        'metadata',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function hostingEnvironment(): BelongsTo
    {
        return $this->belongsTo(HostingEnvironment::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SitePublicationRun::class);
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(SitePublicationRun::class)->latestOfMany();
    }

    protected function casts(): array
    {
        return [
            'site_id' => 'integer',
            'hosting_environment_id' => 'integer',
            'last_push_at' => 'datetime',
            'last_pull_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
