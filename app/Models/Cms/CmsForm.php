<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsForm extends Model
{
    protected $fillable = [
        'title',
        'locale',
        'translation_key',
        'translated_from_form_id',
        'form_kind',
        'system_key',
        'description',
        'notification_email',
        'submission_email_enabled',
        'submission_cms_email_id',
        'submission_to_cms_form_field_id',
        'submission_cc_recipients',
        'submission_bcc_recipients',
        'submit_button_label',
        'success_message',
        'is_active',
        'settings',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(CmsFormField::class, 'cms_form_id')->orderBy('sort_order');
    }

    public function translatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'translated_from_form_id');
    }

    public function submissionEmail(): BelongsTo
    {
        return $this->belongsTo(CmsEmail::class, 'submission_cms_email_id');
    }

    public function submissionToField(): BelongsTo
    {
        return $this->belongsTo(CmsFormField::class, 'submission_to_cms_form_field_id');
    }

    public function translatedForms(): HasMany
    {
        return $this->hasMany(self::class, 'translation_key', 'translation_key')
            ->whereKeyNot($this->getKey())
            ->orderBy('locale')
            ->orderBy('title');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CmsFormSubmission::class, 'cms_form_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'submission_email_enabled' => 'boolean',
            'submission_cc_recipients' => 'array',
            'submission_bcc_recipients' => 'array',
            'settings' => 'array',
        ];
    }
}
