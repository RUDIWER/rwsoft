<?php

namespace App\Actions\Admin\Cms;

use App\Jobs\Admin\Cms\GenerateCmsMediaImageVariantsJob;
use App\Models\Cms\CmsMediaAsset;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class CreateCmsEditedImageAssetAction
{
    public function __construct(private readonly EnsureCmsMediaContextFolderAction $contextFolderAction) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(CmsMediaAsset $sourceAsset, array $data): CmsMediaAsset
    {
        $this->ensureEditableSource($sourceAsset);

        $diskName = $sourceAsset->disk ?: 'public';
        $disk = Storage::disk($diskName);
        $sourceBinary = $disk->get((string) $sourceAsset->path);
        $image = $this->decodeImage($sourceBinary);

        $this->applyCrop($image, $data);
        $this->applyZoom($image, $data);
        $this->applyFilters($image, $data);

        $maxWidth = (int) ($data['max_width'] ?? 0);
        $maxHeight = (int) ($data['max_height'] ?? 0);
        if ($maxWidth > 0 || $maxHeight > 0) {
            $image = $image->scaleDown(
                width: $maxWidth > 0 ? $maxWidth : null,
                height: $maxHeight > 0 ? $maxHeight : null,
            );
        }

        $quality = (int) ($data['quality'] ?? config('cms_media.webp_quality', 80));
        $quality = max(1, min(100, $quality));
        $binary = (string) $image->encodeUsingFormat(Format::WEBP, quality: $quality);
        $hash = hash('sha256', $binary);
        $filename = 'edited-'.$this->shortHash($hash).'.webp';
        $siteId = TenantContext::siteId();
        $contextType = $this->nullableContextType($data['context_type'] ?? null);
        $contextId = $this->nullableContextId($data['context_id'] ?? null);
        $contextFolder = $this->contextFolderAction->handle($contextType, $contextId);

        $asset = CmsMediaAsset::query()->create([
            'folder_id' => $contextFolder?->id ?? $sourceAsset->folder_id,
            'uploaded_by' => request()->user()?->id,
            'disk' => 'public',
            'visibility' => 'public',
            'asset_kind' => 'site_image',
            'source_media_asset_id' => (int) $sourceAsset->id,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'path' => $this->pendingPath($hash, $siteId),
            'filename' => $filename,
            'original_filename' => $this->editedOriginalFilename($sourceAsset),
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'size_bytes' => strlen($binary),
            'width' => $image->width(),
            'height' => $image->height(),
            'hash' => $hash,
            'alt_text' => $this->nullableString($data['alt_text'] ?? $sourceAsset->alt_text),
            'caption' => $this->nullableString($data['caption'] ?? $sourceAsset->caption),
            'focal_point' => $sourceAsset->focal_point,
            'metadata' => [
                'uploaded_from' => 'cms_image_editor',
                'source_media_asset_id' => (int) $sourceAsset->id,
                'context' => [
                    'type' => $contextType,
                    'id' => $contextId,
                ],
                'image_edit' => $this->editMetadata($data),
                'optimization' => [
                    'status' => 'pending',
                    'format' => 'webp',
                    'source_hash' => $hash,
                ],
                'variants' => [],
            ],
            'sort_order' => ((int) (CmsMediaAsset::query()->max('sort_order') ?? 0)) + 1,
        ]);

        $path = $this->assetDirectory($asset, $siteId, $contextType, $contextId).'/original/'.$filename;
        $disk->put($path, $binary);
        $asset->forceFill(['path' => $path])->save();

        if ($siteId !== null) {
            GenerateCmsMediaImageVariantsJob::dispatch($siteId, (int) $asset->id);
        }

        return $asset->refresh();
    }

    private function ensureEditableSource(CmsMediaAsset $sourceAsset): void
    {
        if (($sourceAsset->disk ?: 'public') !== 'public' || $sourceAsset->visibility !== 'public') {
            throw ValidationException::withMessages([
                'media_asset_id' => __('cms_admin_ui.validation.media_edit_source_invalid'),
            ]);
        }

        if (! in_array(Str::lower((string) $sourceAsset->extension), ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw ValidationException::withMessages([
                'media_asset_id' => __('cms_admin_ui.validation.media_edit_source_invalid'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyCrop(mixed &$image, array $data): void
    {
        $crop = is_array($data['crop'] ?? null) ? $data['crop'] : [];
        $width = (int) ($crop['width'] ?? 0);
        $height = (int) ($crop['height'] ?? 0);

        if ($width <= 0 || $height <= 0) {
            return;
        }

        $this->cropImage(
            $image,
            (int) ($crop['x'] ?? 0),
            (int) ($crop['y'] ?? 0),
            $width,
            $height,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyZoom(mixed &$image, array $data): void
    {
        $zoom = max(100, min(400, (int) ($data['zoom'] ?? 100)));
        if ($zoom <= 100 || is_array($data['crop'] ?? null)) {
            return;
        }

        $factor = $zoom / 100;
        $cropWidth = max(1, (int) floor($image->width() / $factor));
        $cropHeight = max(1, (int) floor($image->height() / $factor));
        $focalX = max(0, min(100, (int) ($data['focal_x'] ?? 50))) / 100;
        $focalY = max(0, min(100, (int) ($data['focal_y'] ?? 50))) / 100;
        $x = (int) round(($image->width() * $focalX) - ($cropWidth / 2));
        $y = (int) round(($image->height() * $focalY) - ($cropHeight / 2));

        $this->cropImage($image, $x, $y, $cropWidth, $cropHeight);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyFilters(mixed $image, array $data): void
    {
        if ((bool) ($data['grayscale'] ?? false) && method_exists($image, 'grayscale')) {
            $image->grayscale();
        } elseif ((bool) ($data['grayscale'] ?? false) && method_exists($image, 'greyscale')) {
            $image->greyscale();
        }

        $brightness = (int) ($data['brightness'] ?? 0);
        if ($brightness !== 0 && method_exists($image, 'brightness')) {
            $image->brightness($brightness);
        }

        $contrast = (int) ($data['contrast'] ?? 0);
        if ($contrast !== 0 && method_exists($image, 'contrast')) {
            $image->contrast($contrast);
        }
    }

    private function cropImage(mixed &$image, int $x, int $y, int $width, int $height): void
    {
        $x = max(0, min($x, max(0, $image->width() - 1)));
        $y = max(0, min($y, max(0, $image->height() - 1)));
        $width = max(1, min($width, $image->width() - $x));
        $height = max(1, min($height, $image->height() - $y));

        $image = $image->crop($width, $height, $x, $y);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function editMetadata(array $data): array
    {
        return Arr::only($data, [
            'crop',
            'zoom',
            'focal_x',
            'focal_y',
            'max_width',
            'max_height',
            'grayscale',
            'brightness',
            'contrast',
            'quality',
        ]);
    }

    private function pendingPath(string $hash, ?int $siteId): string
    {
        return 'cms/site-images/site-'.($siteId ?: 0).'/pending/'.$this->shortHash($hash).'-'.Str::ulid().'.webp';
    }

    private function assetDirectory(CmsMediaAsset $asset, ?int $siteId, ?string $contextType, ?int $contextId): string
    {
        $contextSegment = $contextType && $contextId
            ? Str::plural($contextType).'/'.$contextId
            : 'uncategorized';

        return 'cms/site-images/site-'.($siteId ?: 0).'/'.$contextSegment.'/assets/'.$asset->id;
    }

    private function editedOriginalFilename(CmsMediaAsset $sourceAsset): string
    {
        $name = pathinfo((string) ($sourceAsset->original_filename ?: $sourceAsset->filename ?: 'image'), PATHINFO_FILENAME);

        return (Str::slug($name) ?: 'image').'-edited.webp';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function nullableContextType(mixed $value): ?string
    {
        $value = trim((string) $value);

        return in_array($value, ['page', 'post', 'category', 'tag', 'cms_settings'], true) ? $value : null;
    }

    private function nullableContextId(mixed $value): ?int
    {
        $value = (int) $value;

        return $value > 0 ? $value : null;
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
