<?php

namespace App\Models\Platform;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HostingConnection extends Model
{
    protected $connection = 'central';

    protected $table = 'platform_hosting_connections';

    protected $fillable = [
        'name',
        'provider',
        'api_base_url',
        'api_token',
        'status',
        'last_checked_at',
        'last_error',
        'metadata',
        'created_by',
    ];

    protected $hidden = [
        'api_token',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function environments(): HasMany
    {
        return $this->hasMany(HostingEnvironment::class);
    }

    public function hasApiToken(): bool
    {
        return filled($this->api_token);
    }

    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
            'last_checked_at' => 'datetime',
            'metadata' => 'array',
            'created_by' => 'integer',
        ];
    }
}
