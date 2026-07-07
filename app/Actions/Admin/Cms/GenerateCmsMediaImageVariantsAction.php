<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsMediaAsset;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class GenerateCmsMediaImageVariantsAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(CmsMediaAsset $asset): array
    {
        $diskName = $asset->disk ?: 'public';
        $disk = Storage::disk($diskName);
        $metadata = is_array($asset->metadata) ? $asset->metadata : [];

        $asset->forceFill([
            'metadata' => array_replace_recursive($metadata, [
                'optimization' => [
                    'status' => 'processing',
                    'format' => 'webp',
                    'quality' => (int) config('cms_media.webp_quality', 80),
                    'source_hash' => (string) $asset->hash,
                ],
            ]),
        ])->save();

        try {
            $sourceBinary = $disk->get($asset->path);
            $sourceImage = $this->decodeImage($sourceBinary);
            $quality = (int) config('cms_media.webp_quality', 80);
            $shortHash = $this->shortHash((string) $asset->hash);
            $variants = [];

            foreach ($this->variantWidths() as $width) {
                if ($sourceImage->width() < $width) {
                    continue;
                }

                $image = $this->decodeImage($sourceBinary)->scaleDown(width: $width);
                $path = $this->variantPath($asset, $width, $shortHash);

                $disk->put($path, (string) $image->encodeUsingFormat(Format::WEBP, quality: $quality));

                $variants['webp'][(string) $width] = [
                    'path' => $path,
                    'width' => $image->width(),
                    'height' => $image->height(),
                    'size_bytes' => $disk->size($path),
                ];
            }

            $freshAsset = $asset->fresh();
            $metadata = is_array($freshAsset?->metadata) ? $freshAsset->metadata : [];
            $metadata['optimization'] = [
                'status' => 'completed',
                'format' => 'webp',
                'quality' => $quality,
                'source_hash' => (string) $asset->hash,
                'generated_at' => now()->toISOString(),
            ];
            $metadata['variants'] = $variants;

            $asset->forceFill(['metadata' => $metadata])->save();

            return $metadata;
        } catch (\Throwable $exception) {
            $freshAsset = $asset->fresh();
            $metadata = is_array($freshAsset?->metadata) ? $freshAsset->metadata : [];
            $metadata['optimization'] = [
                'status' => 'failed',
                'format' => 'webp',
                'quality' => (int) config('cms_media.webp_quality', 80),
                'source_hash' => (string) $asset->hash,
                'failed_at' => now()->toISOString(),
                'error' => $exception->getMessage(),
            ];

            $asset->forceFill(['metadata' => $metadata])->save();

            throw $exception;
        }
    }

    /**
     * @return array<int, int>
     */
    private function variantWidths(): array
    {
        return collect((array) config('cms_media.variants', []))
            ->map(fn (mixed $width): int => (int) $width)
            ->filter(fn (int $width): bool => $width > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function variantPath(CmsMediaAsset $asset, int $width, string $shortHash): string
    {
        return $this->assetDirectory($asset).'/variants/'.$width.'-'.$shortHash.'.webp';
    }

    private function assetDirectory(CmsMediaAsset $asset): string
    {
        $path = trim((string) $asset->path, '/');
        $originalSegment = '/original/';
        $position = strpos($path, $originalSegment);

        if ($position === false) {
            return trim(dirname($path), '/');
        }

        return substr($path, 0, $position);
    }

    private function shortHash(string $hash): string
    {
        return substr($hash, 0, 12) ?: 'image';
    }

    private function decodeImage(string $sourceBinary): ImageInterface
    {
        try {
            return ImageManager::usingDriver($this->driverClass())->decodeBinary($sourceBinary)->orient();
        } catch (\Throwable $exception) {
            if ($this->driverClass() === GdDriver::class) {
                throw $exception;
            }

            return ImageManager::usingDriver(GdDriver::class)->decodeBinary($sourceBinary)->orient();
        }
    }

    /**
     * @return class-string
     */
    private function driverClass(): string
    {
        return extension_loaded('imagick') ? ImagickDriver::class : GdDriver::class;
    }
}
