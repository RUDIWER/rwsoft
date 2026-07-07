<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsVisit extends Model
{
    protected $fillable = [
        'cms_visitor_id',
        'uuid',
        'ip',
        'ip_hash',
        'method',
        'url',
        'path',
        'locale',
        'ref',
        'referer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'user_agent',
        'platform',
        'country_code_header',
        'is_crawler',
        'data',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(CmsVisitor::class, 'cms_visitor_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_crawler' => 'boolean',
            'data' => 'array',
        ];
    }
}
