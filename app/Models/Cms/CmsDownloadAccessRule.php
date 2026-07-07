<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;

class CmsDownloadAccessRule extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'rule_type',
        'site_user_id',
        'cms_download_group_id',
        'profile_field_key',
        'operator',
        'value',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
