<?php

namespace App\Actions\Admin\Cms;

use App\Support\Tenancy\TenantContext;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StoreCmsFaviconAction
{
    private const DISK = 'public';

    /**
     * @return array{favicon_32_path: string, favicon_192_path: string, apple_touch_icon_path: string, favicon_version: int}
     */
    public function handle(UploadedFile $file): array
    {
        $sourceContents = file_get_contents((string) $file->getRealPath());
        $sourceImage = $sourceContents !== false ? imagecreatefromstring($sourceContents) : false;

        if ($sourceImage === false) {
            throw ValidationException::withMessages([
                'favicon_file' => __('cms_admin_ui.validation.favicon_process_failed'),
            ]);
        }

        try {
            $directory = $this->directory();
            $paths = [
                'favicon_32_path' => $directory.'/favicon-32x32.png',
                'favicon_192_path' => $directory.'/favicon-192x192.png',
                'apple_touch_icon_path' => $directory.'/apple-touch-icon.png',
            ];

            $this->storePng($sourceImage, $paths['favicon_32_path'], 32);
            $this->storePng($sourceImage, $paths['favicon_192_path'], 192);
            $this->storePng($sourceImage, $paths['apple_touch_icon_path'], 180);

            return array_merge($paths, ['favicon_version' => now()->timestamp]);
        } finally {
            imagedestroy($sourceImage);
        }
    }

    private function storePng(GdImage $sourceImage, string $path, int $size): void
    {
        $targetImage = imagecreatetruecolor($size, $size);

        if ($targetImage === false) {
            throw ValidationException::withMessages([
                'favicon_file' => __('cms_admin_ui.validation.favicon_resize_failed'),
            ]);
        }

        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);

        $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        imagefilledrectangle($targetImage, 0, 0, $size, $size, $transparent);

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        $sourceSize = min($sourceWidth, $sourceHeight);
        $sourceX = (int) floor(($sourceWidth - $sourceSize) / 2);
        $sourceY = (int) floor(($sourceHeight - $sourceSize) / 2);

        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0,
            0,
            $sourceX,
            $sourceY,
            $size,
            $size,
            $sourceSize,
            $sourceSize,
        );

        ob_start();
        imagepng($targetImage, null, 9);
        $pngContents = ob_get_clean();
        imagedestroy($targetImage);

        if (! is_string($pngContents) || $pngContents === '') {
            throw ValidationException::withMessages([
                'favicon_file' => __('cms_admin_ui.validation.favicon_png_failed'),
            ]);
        }

        Storage::disk(self::DISK)->put($path, $pngContents);
    }

    private function directory(): string
    {
        $siteId = TenantContext::siteId();

        if (! $siteId) {
            return 'cms/favicon';
        }

        return 'sites/'.$siteId.'/cms/favicon';
    }
}
