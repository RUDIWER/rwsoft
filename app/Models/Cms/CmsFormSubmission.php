<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsFormSubmission extends Model
{
    protected $fillable = [
        'cms_form_id',
        'cms_page_id',
        'locale',
        'form_translation_key',
        'status',
        'ip_address',
        'user_agent',
        'submitted_at',
        'metadata',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(CmsForm::class, 'cms_form_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'cms_page_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CmsFormSubmissionValue::class, 'cms_form_submission_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
