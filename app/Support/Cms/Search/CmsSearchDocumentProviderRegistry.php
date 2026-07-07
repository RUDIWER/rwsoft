<?php

namespace App\Support\Cms\Search;

use App\Support\Cms\Search\Contracts\CmsSearchDocumentProvider;
use App\Support\Cms\Search\Providers\CmsPublicContentSearchDocumentProvider;

class CmsSearchDocumentProviderRegistry
{
    public function __construct(private readonly CmsPublicContentSearchDocumentProvider $publicContentProvider) {}

    /**
     * @return array<int, CmsSearchDocumentProvider>
     */
    public function providers(?string $sourceType = null): array
    {
        $providers = [$this->publicContentProvider];

        if ($sourceType === null || $sourceType === '') {
            return $providers;
        }

        return collect($providers)
            ->filter(fn (CmsSearchDocumentProvider $provider): bool => in_array($sourceType, $provider->sourceTypes(), true))
            ->values()
            ->all();
    }
}
