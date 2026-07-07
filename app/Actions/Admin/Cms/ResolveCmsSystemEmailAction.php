<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsEmail;
use App\Support\Cms\CmsSystemMailRegistry;

class ResolveCmsSystemEmailAction
{
    public function __construct(private readonly CmsSystemMailRegistry $registry) {}

    public function handle(string $systemKey, string $locale): ?CmsEmail
    {
        if ($this->registry->get($systemKey) === null) {
            return null;
        }

        return CmsEmail::query()
            ->with('mailTemplate')
            ->active()
            ->systemKey($systemKey, $locale)
            ->first()
            ?? CmsEmail::query()
                ->with('mailTemplate')
                ->active()
                ->systemKey($systemKey, 'en')
                ->first();
    }
}
