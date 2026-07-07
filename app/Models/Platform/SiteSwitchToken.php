<?php

namespace App\Models\Platform;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSwitchToken extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = [
        'site_id',
        'user_id',
        'token_hash',
        'expires_at',
        'used_at',
        'ip_address',
        'user_agent',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'site_id' => 'integer',
            'user_id' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
