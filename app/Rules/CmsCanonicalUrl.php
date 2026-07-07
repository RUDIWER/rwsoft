<?php

namespace App\Rules;

use App\Support\PublicSite\CmsCanonicalUrlPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class CmsCanonicalUrl implements ValidationRule
{
    public function __construct(private readonly ?string $locale) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(CmsCanonicalUrlPolicy::class)->isValid(
            is_string($value) ? $value : null,
            $this->locale,
            request(),
        )) {
            $fail('cms_validation.canonical_url')->translate();
        }
    }
}
