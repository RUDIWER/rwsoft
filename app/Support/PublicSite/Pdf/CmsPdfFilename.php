<?php

namespace App\Support\PublicSite\Pdf;

use Illuminate\Support\Str;

class CmsPdfFilename
{
    public function make(string $title, ?string $locale = null): string
    {
        $base = Str::slug($title) ?: 'download';
        $localeSuffix = filled($locale) ? '-'.Str::slug((string) $locale) : '';

        return $base.$localeSuffix.'.pdf';
    }
}
