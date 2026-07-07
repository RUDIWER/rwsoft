<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsPublicTextTranslation extends Model
{
    protected $fillable = [
        'cms_public_text_id',
        'locale',
        'value',
    ];

    public function publicText(): BelongsTo
    {
        return $this->belongsTo(CmsPublicText::class, 'cms_public_text_id');
    }
}
