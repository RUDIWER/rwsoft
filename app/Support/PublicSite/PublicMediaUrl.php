<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsMediaAsset;
use App\Support\Cms\CmsMediaVariantSelector;

class PublicMediaUrl
{
    public function __construct(
        private readonly PublicStorageUrl $storageUrl,
        private readonly CmsMediaVariantSelector $variantSelector,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function payload(?CmsMediaAsset $asset, ?string $locale = null): ?array
    {
        if (! $asset instanceof CmsMediaAsset) {
            return null;
        }

        if ($asset->visibility !== 'public' || $asset->disk !== 'public') {
            return null;
        }

        $translation = $asset->translationForLocale((string) $locale);

        return [
            'id' => $asset->id,
            'url' => $this->storageUrl->url($asset->path),
            'alt_text' => $translation?->alt_text ?: $asset->alt_text,
            'caption' => $translation?->caption ?: $asset->caption,
            'width' => $asset->width,
            'height' => $asset->height,
            'variants' => $this->variantPayload($asset),
            'responsive_variants' => $this->responsiveVariantPayload($asset),
        ];
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function variantPayload(CmsMediaAsset $asset): array
    {
        $metadata = is_array($asset->metadata) ? $asset->metadata : [];
        $variants = (array) data_get($metadata, 'variants', []);
        $payload = [];

        foreach ($variants as $format => $items) {
            foreach ((array) $items as $width => $variant) {
                $path = data_get($variant, 'path');

                if (! is_string($path) || $path === '') {
                    continue;
                }

                $payload[(string) $format][(string) $width] = array_merge((array) $variant, [
                    'url' => $this->storageUrl->url($path, $asset->disk ?: 'public'),
                ]);
            }
        }

        return $payload;
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    private function responsiveVariantPayload(CmsMediaAsset $asset): array
    {
        return collect($this->variantSelector->responsive($asset))
            ->map(function (?array $variant) use ($asset): ?array {
                $path = data_get($variant, 'path');

                if (! is_string($path) || $path === '') {
                    return null;
                }

                return array_merge($variant, [
                    'url' => $this->storageUrl->url($path, $asset->disk ?: 'public'),
                ]);
            })
            ->all();
    }
}
