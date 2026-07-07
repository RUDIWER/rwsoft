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
        'status',
        'created_by',
        'provisioned_at',
        'provisioning_error',
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

    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'provisioned_at' => 'datetime',
        ];
    }
}
