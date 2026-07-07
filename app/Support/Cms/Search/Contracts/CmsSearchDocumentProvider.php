<?php

namespace App\Support\Cms\Search\Contracts;

use App\Support\Cms\Search\CmsSearchDocumentData;

interface CmsSearchDocumentProvider
{
    /**
     * @return iterable<int, CmsSearchDocumentData>
     */
    public function documents(?string $locale = null): iterable;

    /**
     * @return array<int, string>
     */
    public function sourceTypes(): array;
}
