<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'occurred_at',
        'request_id',
        'actor_user_id',
        'actor_name',
        'actor_email',
        'application_slug',
        'application_name',
        'execution_mode',
        'module',
        'action',
        'subject_type',
        'subject_key',
        'success',
        'severity',
        'message',
        'meta',
        'ip_address',
        'user_agent',
        'route_name',
        'http_method',
        'url',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'success' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
