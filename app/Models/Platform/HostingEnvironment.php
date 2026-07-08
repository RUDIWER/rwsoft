<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HostingEnvironment extends Model
{
    protected $connection = 'central';

    protected $table = 'platform_hosting_environments';

    protected $fillable = [
        'hosting_connection_id',
        'name',
        'provider_application_id',
        'provider_environment_id',
        'provider_region',
        'default_tenant_database_mode',
        'default_database_name',
        'default_storage_mode',
        'status',
        'last_synced_at',
        'metadata',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(HostingConnection::class, 'hosting_connection_id');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(SitePublication::class);
    }

    protected function casts(): array
    {
        return [
            'hosting_connection_id' => 'integer',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
