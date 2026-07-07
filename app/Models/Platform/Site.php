<?php

namespace App\Models\Platform;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = [
        'name',
        'slug',
        'tenant_database',
        'tenant_table_prefix',
        'tenant_database_mode',
        'tenant_provisioning_mode',
        'tenant_database_url',
        'tenant_database_host',
        'tenant_database_port',
        'tenant_database_username',
        'tenant_database_password',
        'status',
        'created_by',
        'provisioned_at',
        'provisioning_error',
    ];

    protected $hidden = [
        'tenant_database_password',
        'tenant_database_url',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(SiteDomain::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(SiteUserMembership::class);
    }

    public function primaryDomain(): HasOne
    {
        return $this->hasOne(SiteDomain::class)->where('is_primary', true);
    }

    public function tenantDatabaseMode(): string
    {
        $mode = (string) ($this->tenant_database_mode ?: config('tenancy.default_database_mode', 'separate'));

        return in_array($mode, (array) config('tenancy.database_modes', []), true) ? $mode : 'separate';
    }

    public function tenantProvisioningMode(): string
    {
        $mode = (string) ($this->tenant_provisioning_mode ?: config('tenancy.default_provisioning_mode', 'create_database'));

        if ($this->tenantDatabaseMode() === 'shared_prefixed') {
            return 'shared_prefixed';
        }

        return in_array($mode, (array) config('tenancy.provisioning_modes', []), true) ? $mode : 'create_database';
    }

    public function usesSharedPrefixedTenantDatabase(): bool
    {
        return $this->tenantDatabaseMode() === 'shared_prefixed';
    }

    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'provisioned_at' => 'datetime',
            'tenant_database_port' => 'integer',
            'tenant_database_url' => 'encrypted',
            'tenant_database_password' => 'encrypted',
        ];
    }
}
