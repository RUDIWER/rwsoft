<?php

namespace App\Models\Validation;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientValidationRuleVersion extends Model
{
    protected $table = 'rw_client_rule_versions';

    protected $fillable = [
        'version',
        'state',
        'code',
        'checksum',
        'build_status',
        'build_log',
        'build_started_at',
        'build_finished_at',
        'published_at',
        'created_by',
        'published_by',
    ];

    protected $casts = [
        'build_started_at' => 'datetime',
        'build_finished_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
