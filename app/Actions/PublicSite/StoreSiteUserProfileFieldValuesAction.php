<?php

namespace App\Actions\PublicSite;

use App\Models\PublicSite\SiteUser;
use App\Support\PublicSite\PublicAccountProfileFields;

class StoreSiteUserProfileFieldValuesAction
{
    public function __construct(private readonly PublicAccountProfileFields $profileFields) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function handle(SiteUser $siteUser, array $values, string $context): void
    {
        foreach ($this->profileFields->definitions($context) as $definition) {
            $key = (string) $definition->key;

            if (! array_key_exists($key, $values)) {
                continue;
            }

            $siteUser->profileFieldValues()->updateOrCreate(
                ['site_user_profile_field_definition_id' => $definition->id],
                [
                    'profile_field_key' => $key,
                    'value' => $this->normalizeValue($values[$key]),
                ],
            );
        }
    }

    private function normalizeValue(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
