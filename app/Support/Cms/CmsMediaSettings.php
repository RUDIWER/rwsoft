<?php

namespace App\Support\Cms;

use App\Models\Cms\CmsSetting;
use Illuminate\Support\Facades\Schema;

class CmsMediaSettings
{
    public const MAX_IMAGE_UPLOAD_FIELD = 'media_max_image_upload_mb';

    public const MAX_IMAGE_UPLOAD_GROUP = 'media';

    public const MAX_IMAGE_UPLOAD_KEY = 'max_image_upload_mb';

    public function maxImageUploadMb(): int
    {
        $default = (int) config('cms_media.default_max_image_upload_mb', 20);
        $minimum = (int) config('cms_media.max_image_upload_mb_min', 1);
        $maximum = (int) config('cms_media.max_image_upload_mb_max', 100);

        if (! Schema::hasTable('cms_settings')) {
            return $this->clamp($default, $minimum, $maximum);
        }

        $setting = CmsSetting::query()
            ->where('group', self::MAX_IMAGE_UPLOAD_GROUP)
            ->where('key', self::MAX_IMAGE_UPLOAD_KEY)
            ->first();

        $value = $setting instanceof CmsSetting
            ? (int) ($setting->value['value'] ?? $default)
            : $default;

        return $this->clamp($value, $minimum, $maximum);
    }

    public function maxImageUploadKb(): int
    {
        return $this->maxImageUploadMb() * 1024;
    }

    private function clamp(int $value, int $minimum, int $maximum): int
    {
        return min($maximum, max($minimum, $value));
    }
}
