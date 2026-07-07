<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsEmailDelivery extends Model
{
    protected $fillable = [
        'cms_email_id',
        'context_type',
        'context_id',
        'recipient_email',
        'recipient_name',
        'status',
        'subject_snapshot',
        'error_message',
        'metadata',
        'sent_at',
    ];

    public function email(): BelongsTo
    {
        return $this->belongsTo(CmsEmail::class, 'cms_email_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context_id' => 'integer',
            'metadata' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
