<?php

namespace App\Http\Controllers\PublicSite;

use App\Actions\PublicSite\LogCmsDownloadEventAction;
use App\Actions\PublicSite\ResolveAllowedCmsDownloadsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\UnlockCmsDownloadFolderRequest;
use App\Models\Cms\CmsDownloadAsset;
use App\Models\Cms\CmsDownloadFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CmsDownloadController extends Controller
{
    public function download(
        Request $request,
        int $download,
        ResolveAllowedCmsDownloadsAction $allowedDownloads,
        LogCmsDownloadEventAction $logDownloadEvent,
    ): StreamedResponse {
        $asset = CmsDownloadAsset::query()
            ->with(['folder.parent.parent', 'folder.accessRules', 'accessRules'])
            ->findOrFail($download);

        if (! $allowedDownloads->canDownload($asset, $request)) {
            $logDownloadEvent->handle($asset, 'denied', $request, [
                'reason' => 'access_denied',
            ]);

            abort(403, __('cms_public_ui.downloads.access_denied'));
        }

        $disk = (string) ($asset->disk ?: config('cms_downloads.disk', 'private'));
        $path = (string) $asset->path;

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            $logDownloadEvent->handle($asset, 'missing_file', $request, [
                'disk' => $disk,
            ]);

            abort(404);
        }

        $logDownloadEvent->handle($asset, 'downloaded', $request, [
            'filename' => (string) $asset->filename,
            'mime_type' => (string) $asset->mime_type,
            'size_bytes' => (int) $asset->size_bytes,
        ]);

        return Storage::disk($disk)->download(
            $path,
            $asset->original_filename ?: $asset->filename,
            ['Content-Type' => $asset->mime_type ?: 'application/octet-stream'],
        );
    }

    public function unlockFolder(
        UnlockCmsDownloadFolderRequest $request,
        int $folder,
        ResolveAllowedCmsDownloadsAction $allowedDownloads,
        LogCmsDownloadEventAction $logDownloadEvent,
    ): RedirectResponse {
        $downloadFolder = CmsDownloadFolder::query()->findOrFail($folder);
        $passwordHash = (string) $downloadFolder->password_hash;

        if ($passwordHash === '' || ! Hash::check((string) $request->validated('password'), $passwordHash)) {
            $logDownloadEvent->handle(null, 'folder_unlock_failed', $request, [
                'folder_id' => $downloadFolder->id,
            ]);

            return back()->withErrors([
                'password' => __('cms_public_ui.downloads.invalid_password'),
            ]);
        }

        $allowedDownloads->markFolderUnlocked($downloadFolder, $request);
        $logDownloadEvent->handle(null, 'folder_unlocked', $request, [
            'folder_id' => $downloadFolder->id,
        ]);

        return redirect($this->safeRedirectPath($request->validated('redirect_to')))
            ->with('status', __('cms_public_ui.downloads.folder_unlocked'));
    }

    private function safeRedirectPath(mixed $value): string
    {
        $path = trim((string) $value);

        if ($path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return '/';
        }

        return $path;
    }
}
