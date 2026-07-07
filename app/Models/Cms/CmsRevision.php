<?php

namespace App\Models\Cms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CmsRevision extends Model
{
    protected $fillable = [
        'subject_type',
        'subject_id',
        'author_id',
        'revision_number',
        'scope',
        'title',
        'snapshot',
        'snapshot_hash',
        'restored_from_revision_id',
        'is_pinned',
        'metadata',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'subject_type', 'subject_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function restoredFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'restored_from_revision_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'metadata' => 'array',
            'revision_number' => 'integer',
            'restored_from_revision_id' => 'integer',
            'is_pinned' => 'boolean',
        ];
    }
}
