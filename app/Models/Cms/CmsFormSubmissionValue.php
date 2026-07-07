<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsFormSubmissionValue extends Model
{
    protected $fillable = [
        'cms_form_submission_id',
        'cms_form_field_id',
        'field_translation_key',
        'field_label_snapshot',
        'value',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(CmsFormSubmission::class, 'cms_form_submission_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(CmsFormField::class, 'cms_form_field_id');
    }
}
