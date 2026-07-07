<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PlatformMailTransport extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'name',
        'provider',
        'is_active',
        'status',
        'from_name',
        'from_email',
        'reply_to_email',
        'host',
        'port',
        'encryption',
        'username',
        'encrypted_secret',
        'provider_config',
        'last_tested_at',
        'last_test_status',
        'last_test_error',
        'metadata',
    ];

    public static function active(): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->where('status', 'ready')
            ->latest('id')
            ->first();
    }

    public function secret(): ?string
    {
        if (blank($this->encrypted_secret)) {
            return null;
        }

        return Crypt::decryptString((string) $this->encrypted_secret);
    }

    public function setSecret(?string $secret): void
    {
        $secret = $secret !== null ? trim($secret) : null;

        if ($secret === null || $secret === '') {
            return;
        }

        $this->encrypted_secret = Crypt::encryptString($secret);
    }

    public function hasSecret(): bool
    {
        return filled($this->encrypted_secret);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'port' => 'integer',
            'last_tested_at' => 'datetime',
            'provider_config' => 'array',
            'metadata' => 'array',
        ];
    }
}
