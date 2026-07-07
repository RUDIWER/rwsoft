<?php

namespace App\Support\Cms;

use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsMediaFolder;
use App\Support\PublicSite\CmsLanguageSettings;
use Illuminate\Support\Facades\Storage;

class CmsMediaLibraryPayload
{
    public function __construct(private readonly CmsLanguageSettings $languageSettings) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function assets(): array
    {
        return CmsMediaAsset::query()
            ->with(['folder:id,name', 'translations'])
            ->where('visibility', 'public')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CmsMediaAsset $asset): array => $this->asset($asset))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function folders(): array
    {
        return CmsMediaFolder::query()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug', 'sort_order'])
            ->map(fn (CmsMediaFolder $folder): array => $this->folder($folder))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function asset(CmsMediaAsset $asset): array
    {
        $metadata = is_array($asset->metadata) ? $asset->metadata : [];

        return [
            'id' => $asset->id,
            'folder_id' => $asset->folder_id,
            'folder_name' => $asset->folder?->name,
            'title' => $this->assetTitle($asset),
            'url' => Storage::disk($asset->disk ?: 'public')->url($asset->path),
            'path' => $asset->path,
            'filename' => $asset->filename,
            'original_filename' => $asset->original_filename,
            'mime_type' => $asset->mime_type,
            'extension' => $asset->extension,
            'asset_kind' => $asset->asset_kind ?: 'library',
            'source_media_asset_id' => $asset->source_media_asset_id,
            'context_type' => $asset->context_type,
            'context_id' => $asset->context_id,
            'size_bytes' => $asset->size_bytes,
            'size_kb' => round(((int) $asset->size_bytes) / 1024, 1),
            'width' => $asset->width,
            'height' => $asset->height,
            'alt_text' => $asset->alt_text,
            'caption' => $asset->caption,
            'metadata' => $metadata,
            'optimization_status' => data_get($metadata, 'optimization.status'),
            'variants' => data_get($metadata, 'variants', []),
            'variant_urls' => $this->variantUrls($asset),
            'translations' => $this->mediaTranslations($asset),
            'sort_order' => $asset->sort_order,
            'created_at' => optional($asset->created_at)->toDateTimeString(),
            'updated_at' => optional($asset->updated_at)->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function folder(CmsMediaFolder $folder): array
    {
        return [
            'id' => $folder->id,
            'parent_id' => $folder->parent_id,
            'name' => $folder->name,
            'slug' => $folder->slug,
            'sort_order' => $folder->sort_order,
        ];
    }

    private function assetTitle(CmsMediaAsset $asset): string
    {
        return $asset->alt_text
            ?: $asset->original_filename
            ?: $asset->filename
            ?: $asset->path;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function variantUrls(CmsMediaAsset $asset): array
    {
        $metadata = is_array($asset->metadata) ? $asset->metadata : [];
        $variants = (array) data_get($metadata, 'variants', []);
        $disk = Storage::disk($asset->disk ?: 'public');
        $payload = [];

        foreach ($variants as $format => $items) {
            foreach ((array) $items as $width => $variant) {
                $path = data_get($variant, 'path');

                if (is_string($path) && $path !== '') {
                    $payload[(string) $format][(string) $width] = $disk->url($path);
                }
            }
        }

        return $payload;
    }

    /**
     * @return array<string, array{alt_text: ?string, caption: ?string}>
     */
    private function mediaTranslations(CmsMediaAsset $asset): array
    {
        $translations = $asset->relationLoaded('translations')
            ? $asset->translations
            : $asset->translations()->get();

        $translations = $translations->keyBy('locale');
        $payload = [];

        foreach ($this->languageSettings->languages(true) as $language) {
            $locale = (string) $language['locale'];
            $translation = $translations->get($locale);

            $payload[$locale] = [
                'alt_text' => $translation?->alt_text,
                'caption' => $translation?->caption,
            ];
        }

        return $payload;
    }
}
