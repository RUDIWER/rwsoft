<?php

namespace App\Models\Platform;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteUserMembership extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = [
        'site_id',
        'user_id',
        'is_active',
        'last_accessed_at',
        'admin_locale',
        'allowed_content_locales',
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
            'is_active' => 'boolean',
            'last_accessed_at' => 'datetime',
            'admin_locale' => 'string',
            'allowed_content_locales' => 'array',
        ];
    }
}
