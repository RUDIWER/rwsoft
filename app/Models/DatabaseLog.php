<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseLog extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'user_id',
        'project_name',
        'filename',
        'status',
        'error_message',
        'selected_tables',
        'file_size_kb',
        'log_details',
    ];

    protected $casts = [
        'selected_tables' => 'array',
        'file_size_kb' => 'integer',
        'log_details' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
