<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteDomain extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = [
        'site_id',
        'host',
        'is_primary',
        'force_https',
        'verified_at',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    protected function casts(): array
    {
        return [
            'site_id' => 'integer',
            'is_primary' => 'boolean',
            'force_https' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }
}
