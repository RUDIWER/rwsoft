<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsDownloadAsset;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class StoreCmsDownloadFileAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(UploadedFile $file, array $attributes): CmsDownloadAsset
    {
        $hash = hash_file('sha256', (string) $file->getRealPath());
        $extension = strtolower((string) $file->extension());
        $disk = (string) config('cms_downloads.disk', 'private');
        $filename = $this->filename($file, $hash, $extension);

        $asset = CmsDownloadAsset::query()->create([
            ...$attributes,
            'disk' => $disk,
            'visibility' => 'protected',
            'path' => $this->pendingPath($hash),
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size_bytes' => $file->getSize() ?: 0,
            'hash' => $hash,
        ]);

        $path = $this->assetDirectory((int) $asset->id).'/'.$filename;
        $storedPath = $file->storeAs($this->assetDirectory((int) $asset->id), $filename, $disk);

        if ($storedPath === false) {
            $asset->delete();

            throw new RuntimeException(__('cms_admin_ui.validation.download_file_uploaded'));
        }

        $asset->forceFill(['path' => $path])->save();

        return $asset->refresh();
    }

    public function replace(CmsDownloadAsset $asset, UploadedFile $file): CmsDownloadAsset
    {
        $oldPath = $asset->path;
        $oldDisk = $asset->disk ?: (string) config('cms_downloads.disk', 'private');
        $hash = hash_file('sha256', (string) $file->getRealPath());
        $extension = strtolower((string) $file->extension());
        $filename = $this->filename($file, $hash, $extension);
        $disk = (string) ($asset->disk ?: config('cms_downloads.disk', 'private'));
        $path = $this->assetDirectory((int) $asset->id).'/'.$filename;
        $storedPath = $file->storeAs($this->assetDirectory((int) $asset->id), $filename, $disk);

        if ($storedPath === false) {
            throw new RuntimeException(__('cms_admin_ui.validation.download_file_uploaded'));
        }

        $asset->forceFill([
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size_bytes' => $file->getSize() ?: 0,
            'hash' => $hash,
        ])->save();

        if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $path) {
            Storage::disk($oldDisk)->delete($oldPath);
        }

        return $asset->refresh();
    }

    private function filename(UploadedFile $file, string $hash, string $extension): string
    {
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'download';

        return $base.'-'.substr($hash, 0, 12).'.'.$extension;
    }

    private function pendingPath(string $hash): string
    {
        return $this->downloadDirectory().'/pending/'.substr($hash, 0, 12).'-'.Str::ulid();
    }

    private function assetDirectory(int $assetId): string
    {
        return $this->downloadDirectory().'/assets/'.$assetId;
    }

    private function downloadDirectory(): string
    {
        $siteId = TenantContext::siteId();
        $base = trim((string) config('cms_downloads.directory', 'cms/downloads'), '/');

        return $base.'/site-'.($siteId ?: 0);
    }
}
