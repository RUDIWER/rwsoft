<?php

namespace App\Support\Cms;

use App\Models\Cms\CmsMediaAsset;

class CmsMediaVariantSelector
{
    /**
     * @return array<string, mixed>|null
     */
    public function select(CmsMediaAsset $asset, int $targetWidth, string $format = 'webp'): ?array
    {
        $variants = $this->variants($asset, $format);

        if ($variants === []) {
            return null;
        }

        if (array_key_exists($targetWidth, $variants)) {
            return $variants[$targetWidth];
        }

        foreach ($variants as $width => $variant) {
            if ($width >= $targetWidth) {
                return $variant;
            }
        }

        return end($variants) ?: null;
    }

    /**
     * @param  array<string, int>|null  $targets
     * @return array<string, array<string, mixed>|null>
     */
    public function responsive(CmsMediaAsset $asset, ?array $targets = null, string $format = 'webp'): array
    {
        $targets ??= (array) config('cms_media.responsive_targets', []);
        $selected = [];

        foreach ($targets as $key => $width) {
            $selected[(string) $key] = $this->select($asset, (int) $width, $format);
        }

        return $selected;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function variants(CmsMediaAsset $asset, string $format): array
    {
        $metadata = is_array($asset->metadata) ? $asset->metadata : [];
        $items = (array) data_get($metadata, 'variants.'.$format, []);
        $variants = [];

        foreach ($items as $width => $variant) {
            $width = (int) $width;

            if ($width > 0 && is_array($variant)) {
                $variants[$width] = $variant;
            }
        }

        ksort($variants);

        return $variants;
    }
}
