<?php

namespace App\Support\Cms\Search;

use Carbon\CarbonInterface;

class CmsSearchDocumentData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $sourceType,
        public readonly string $sourceKey,
        public readonly ?int $sourceId,
        public readonly string $locale,
        public readonly string $title,
        public readonly ?string $slug,
        public readonly ?string $summary,
        public readonly ?string $canonicalPath,
        public readonly ?string $canonicalUrl,
        public readonly ?string $markdownPath,
        public readonly ?string $markdownUrl,
        public readonly string $markdown,
        public readonly string $plainText,
        public readonly array $metadata = [],
        public readonly bool $isActive = true,
        public readonly bool $isSearchable = true,
        public readonly bool $noindex = false,
        public readonly ?CarbonInterface $publishedAt = null,
        public readonly ?CarbonInterface $sourceUpdatedAt = null,
    ) {}
}
