<?php

namespace App\Models\PublicSite;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_user_id',
    'site_user_profile_field_definition_id',
    'profile_field_key',
    'value',
])]
class SiteUserProfileFieldValue extends Model
{
    protected $connection = 'tenant';

    public function siteUser(): BelongsTo
    {
        return $this->belongsTo(SiteUser::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(SiteUserProfileFieldDefinition::class, 'site_user_profile_field_definition_id');
    }
}
