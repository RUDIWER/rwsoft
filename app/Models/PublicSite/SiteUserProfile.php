<?php

namespace App\Models\PublicSite;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_user_id',
    'first_name',
    'last_name',
    'phone',
    'locale',
    'marketing_opt_in',
])]
class SiteUserProfile extends Model
{
    protected $connection = 'tenant';

    public function siteUser(): BelongsTo
    {
        return $this->belongsTo(SiteUser::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'marketing_opt_in' => 'boolean',
        ];
    }
}
