<?php

namespace App\Models\PublicSite;

use App\Models\Cms\CmsDownloadGroup;
use App\Notifications\PublicSite\SiteUserResetPasswordNotification;
use App\Notifications\PublicSite\SiteUserVerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'name',
    'email',
    'email_verified_at',
    'password',
    'status',
    'last_login_at',
    'last_login_ip_hash',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'two_factor_confirmed_at',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class SiteUser extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $connection = 'tenant';

    public function profile(): HasOne
    {
        return $this->hasOne(SiteUserProfile::class);
    }

    public function profileFieldValues(): HasMany
    {
        return $this->hasMany(SiteUserProfileFieldValue::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(SiteUserSession::class);
    }

    public function downloadGroups(): BelongsToMany
    {
        return $this->belongsToMany(CmsDownloadGroup::class, 'cms_download_group_site_user')
            ->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new SiteUserResetPasswordNotification((string) $token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new SiteUserVerifyEmailNotification);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
