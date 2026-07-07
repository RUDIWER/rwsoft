<?php

namespace App\Actions\Admin\Cms;

use App\Support\Tenancy\TenantContext;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StoreCmsLogoAction
{
    private const DISK = 'public';

    /**
     * @return array{logo_path: string, logo_version: int}
     */
    public function handle(UploadedFile $file): array
    {
        $sourceContents = file_get_contents((string) $file->getRealPath());
        $sourceImage = $sourceContents !== false ? imagecreatefromstring($sourceContents) : false;

        if ($sourceImage === false) {
            throw ValidationException::withMessages([
                'logo_file' => __('cms_admin_ui.validation.logo_process_failed'),
            ]);
        }

        try {
            $path = $this->directory().'/logo.png';

            $this->storePng($sourceImage, $path, 480, 160);

            return [
                'logo_path' => $path,
                'logo_version' => now()->timestamp,
            ];
        } finally {
            imagedestroy($sourceImage);
        }
    }

    private function storePng(GdImage $sourceImage, string $path, int $width, int $height): void
    {
        $targetImage = imagecreatetruecolor($width, $height);

        if ($targetImage === false) {
            throw ValidationException::withMessages([
                'logo_file' => __('cms_admin_ui.validation.logo_resize_failed'),
            ]);
        }

        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);

        $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        imagefilledrectangle($targetImage, 0, 0, $width, $height, $transparent);

        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $width,
            $height,
            imagesx($sourceImage),
            imagesy($sourceImage),
        );

        ob_start();
        imagepng($targetImage, null, 9);
        $pngContents = ob_get_clean();
        imagedestroy($targetImage);

        if (! is_string($pngContents) || $pngContents === '') {
            throw ValidationException::withMessages([
                'logo_file' => __('cms_admin_ui.validation.logo_png_failed'),
            ]);
        }

        Storage::disk(self::DISK)->put($path, $pngContents);
    }

    private function directory(): string
    {
        $siteId = TenantContext::siteId();

        if (! $siteId) {
            return 'cms/branding';
        }

        return 'sites/'.$siteId.'/cms/branding';
    }
}
