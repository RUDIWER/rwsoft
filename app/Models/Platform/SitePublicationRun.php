<?php

namespace App\Models\Platform;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePublicationRun extends Model
{
    protected $connection = 'central';

    protected $table = 'platform_site_publication_runs';

    protected $fillable = [
        'site_publication_id',
        'direction',
        'status',
        'started_at',
        'finished_at',
        'steps',
        'options',
        'error_message',
        'created_by',
    ];

    public function publication(): BelongsTo
    {
        return $this->belongsTo(SitePublication::class, 'site_publication_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'site_publication_id' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'steps' => 'array',
            'options' => 'array',
            'created_by' => 'integer',
        ];
    }
}
