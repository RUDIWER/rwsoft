<?php

namespace App\Actions\Admin\Cms;

use App\Jobs\Admin\Cms\GenerateCmsMediaImageVariantsJob;
use App\Models\Cms\CmsMediaAsset;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CopyCmsMediaAssetToContextAction
{
    public function __construct(private readonly EnsureCmsMediaContextFolderAction $contextFolderAction) {}

    public function handle(CmsMediaAsset $sourceAsset, string $contextType, int $contextId): CmsMediaAsset
    {
        $contextType = $this->contextType($contextType);

        if ($contextType === null || $contextId <= 0) {
            return $sourceAsset;
        }

        if ($this->isContextAsset($sourceAsset, $contextType, $contextId)) {
            return $sourceAsset;
        }

        $reusableAsset = $this->reusableContextAsset($sourceAsset, $contextType, $contextId);

        if ($reusableAsset instanceof CmsMediaAsset) {
            return $reusableAsset;
        }

        $this->ensureCopyableSource($sourceAsset);

        $diskName = $sourceAsset->disk ?: 'public';
        $disk = Storage::disk($diskName);
        $binary = $disk->get((string) $sourceAsset->path);
        $hash = hash('sha256', $binary);
        $siteId = TenantContext::siteId();
        $contextFolder = $this->contextFolderAction->handle($contextType, $contextId);
        $extension = Str::lower((string) ($sourceAsset->extension ?: pathinfo((string) $sourceAsset->path, PATHINFO_EXTENSION)));
        $extension = $extension !== '' ? $extension : 'bin';
        $filename = 'copy-'.$this->shortHash($hash).'.'.$extension;
        $metadata = is_array($sourceAsset->metadata) ? $sourceAsset->metadata : [];
        $metadata['uploaded_from'] = 'cms_context_media_copy';
        $metadata['copied_from_media_asset_id'] = (int) $sourceAsset->id;
        $metadata['source_media_asset_id'] = $this->sourceMediaAssetId($sourceAsset);
        $metadata['context'] = [
            'type' => $contextType,
            'id' => $contextId,
        ];
        $metadata['variants'] = [];
        $metadata['optimization'] = [
            'status' => $this->supportsVariants($extension) ? 'pending' : 'skipped',
            'format' => 'webp',
            'source_hash' => $hash,
        ];

        $asset = CmsMediaAsset::query()->create([
            'folder_id' => $contextFolder?->id ?? $sourceAsset->folder_id,
            'uploaded_by' => request()->user()?->id,
            'disk' => 'public',
            'visibility' => 'public',
            'asset_kind' => 'site_image',
            'source_media_asset_id' => $this->sourceMediaAssetId($sourceAsset),
            'context_type' => $contextType,
            'context_id' => $contextId,
            'path' => $this->pendingPath($hash, $siteId, $extension),
            'filename' => $filename,
            'original_filename' => $sourceAsset->original_filename ?: $sourceAsset->filename,
            'mime_type' => $sourceAsset->mime_type,
            'extension' => $extension,
            'size_bytes' => strlen($binary),
            'width' => $sourceAsset->width,
            'height' => $sourceAsset->height,
            'hash' => $hash,
            'alt_text' => $sourceAsset->alt_text,
            'caption' => $sourceAsset->caption,
            'focal_point' => $sourceAsset->focal_point,
            'metadata' => $metadata,
            'sort_order' => ((int) (CmsMediaAsset::query()->max('sort_order') ?? 0)) + 1,
        ]);

        $path = $this->assetDirectory($asset, $siteId, $contextType, $contextId).'/original/'.$filename;
        $disk->put($path, $binary);
        $asset->forceFill(['path' => $path])->save();

        if ($siteId !== null && $this->supportsVariants($extension)) {
            GenerateCmsMediaImageVariantsJob::dispatch($siteId, (int) $asset->id);
        }

        return $asset->refresh();
    }

    private function isContextAsset(CmsMediaAsset $asset, string $contextType, int $contextId): bool
    {
        return ($asset->asset_kind ?: 'library') === 'site_image'
            && $asset->context_type === $contextType
            && (int) $asset->context_id === $contextId;
    }

    private function reusableContextAsset(CmsMediaAsset $sourceAsset, string $contextType, int $contextId): ?CmsMediaAsset
    {
        if (($sourceAsset->asset_kind ?: 'library') !== 'library') {
            return null;
        }

        return CmsMediaAsset::query()
            ->where('asset_kind', 'site_image')
            ->where('source_media_asset_id', (int) $sourceAsset->id)
            ->where('context_type', $contextType)
            ->where('context_id', $contextId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get()
            ->first(function (CmsMediaAsset $asset) use ($sourceAsset): bool {
                $metadata = is_array($asset->metadata) ? $asset->metadata : [];

                return data_get($metadata, 'uploaded_from') === 'cms_context_media_copy'
                    && (int) data_get($metadata, 'copied_from_media_asset_id') === (int) $sourceAsset->id;
            });
    }

    private function ensureCopyableSource(CmsMediaAsset $sourceAsset): void
    {
        if (($sourceAsset->disk ?: 'public') !== 'public' || $sourceAsset->visibility !== 'public') {
            throw ValidationException::withMessages([
                'media_asset_id' => __('cms_admin_ui.validation.media_edit_source_invalid'),
            ]);
        }
    }

    private function sourceMediaAssetId(CmsMediaAsset $sourceAsset): int
    {
        return (int) ($sourceAsset->source_media_asset_id ?: $sourceAsset->id);
    }

    private function supportsVariants(string $extension): bool
    {
        return in_array(Str::lower($extension), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    private function pendingPath(string $hash, ?int $siteId, string $extension): string
    {
        return 'cms/site-images/site-'.($siteId ?: 0).'/pending/'.$this->shortHash($hash).'-'.Str::ulid().'.'.$extension;
    }

    private function assetDirectory(CmsMediaAsset $asset, ?int $siteId, string $contextType, int $contextId): string
    {
        return 'cms/site-images/site-'.($siteId ?: 0).'/'.Str::plural($contextType).'/'.$contextId.'/assets/'.$asset->id;
    }

    private function contextType(string $value): ?string
    {
        return in_array($value, ['page', 'post', 'category', 'tag', 'cms_settings'], true) ? $value : null;
    }

    private function shortHash(string $hash): string
    {
        return substr($hash, 0, 12) ?: 'image';
    }
}
