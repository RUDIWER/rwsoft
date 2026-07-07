<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\CreateCmsEditedImageAssetAction;
use App\Actions\Admin\Cms\EnsureCmsMediaContextFolderAction;
use App\Actions\Admin\Cms\FindCmsMediaAssetUsagesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\SortCmsMediaAssetsRequest;
use App\Http\Requests\Admin\Cms\StoreCmsEditedMediaRequest;
use App\Http\Requests\Admin\Cms\UpdateCmsMediaAssetRequest;
use App\Http\Requests\Admin\Cms\UploadCmsMediaRequest;
use App\Jobs\Admin\Cms\GenerateCmsMediaImageVariantsJob;
use App\Models\Cms\CmsMediaAsset;
use App\Support\Audit\AuditLogger;
use App\Support\Cms\CmsMediaLibraryPayload;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CmsMediaController extends Controller
{
    private const DISK = 'public';

    private const DIRECTORY = 'cms/media';

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsMediaLibraryPayload $mediaLibraryPayload,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Cms/Media/Index', [
            'assets' => $this->mediaLibraryPayload->assets(),
            'activeLanguages' => $this->languageSettings->languages(true),
            'defaultLocale' => $this->languageSettings->defaultLocale(),
            'folders' => $this->mediaLibraryPayload->folders(),
        ]);
    }

    public function store(UploadCmsMediaRequest $request, EnsureCmsMediaContextFolderAction $contextFolderAction): RedirectResponse|JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $hash = hash_file('sha256', (string) $file->getRealPath());
        $contextFolder = $contextFolderAction->handle(
            $request->validated('context_type'),
            $request->validated('context_id'),
        );
        $folderId = $contextFolder?->id ?? $request->validated('folder_id');

        $existingAsset = CmsMediaAsset::query()->where('hash', $hash)->first();
        if ($existingAsset instanceof CmsMediaAsset) {
            if ($request->expectsJson()) {
                return response()->json([
                    'asset' => $this->mediaLibraryPayload->asset($existingAsset->loadMissing(['folder:id,name', 'translations'])),
                    'folders' => $this->mediaLibraryPayload->folders(),
                    'already_exists' => true,
                ]);
            }

            return redirect()
                ->route('admin.cms.media.edit', ['id' => $existingAsset->id])
                ->with('warning', __('cms_admin_ui.flash.media_duplicate'));
        }

        [$width, $height] = $this->imageDimensions($file);
        $siteId = TenantContext::siteId();
        $extension = strtolower((string) $file->extension());
        $shortHash = $this->shortHash($hash);
        $filename = 'original-'.$shortHash.'.'.$extension;

        $asset = CmsMediaAsset::query()->create([
            'folder_id' => $folderId,
            'uploaded_by' => $request->user()?->id,
            'disk' => self::DISK,
            'visibility' => 'public',
            'path' => $this->pendingAssetPath($hash),
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size_bytes' => $file->getSize() ?: 0,
            'width' => $width,
            'height' => $height,
            'hash' => $hash,
            'alt_text' => $request->validated('alt_text'),
            'caption' => $request->validated('caption'),
            'metadata' => [
                'uploaded_from' => $request->validated('uploaded_from'),
                'context' => [
                    'type' => $request->validated('context_type'),
                    'id' => $request->validated('context_id'),
                ],
                'optimization' => [
                    'status' => 'pending',
                    'format' => 'webp',
                    'source_hash' => $hash,
                ],
                'variants' => [],
            ],
            'sort_order' => ((int) (CmsMediaAsset::query()->max('sort_order') ?? 0)) + 1,
        ]);

        $path = $this->assetDirectory($asset->id, $siteId).'/original/'.$filename;
        $storedPath = $file->storeAs($this->assetDirectory($asset->id, $siteId).'/original', $filename, self::DISK);

        if ($storedPath === false) {
            $asset->delete();

            throw new RuntimeException(__('cms_admin_ui.validation.media_file_uploaded'));
        }

        $asset->forceFill(['path' => $path])->save();

        if ($siteId !== null) {
            GenerateCmsMediaImageVariantsJob::dispatch($siteId, $asset->id);
        }

        $asset->refresh();

        $this->auditLogger->success(
            action: 'cms.media.upload',
            module: 'cms',
            subjectType: 'cms_media_asset',
            subjectKey: (string) $asset->id,
            message: __('cms_admin_ui.flash.uploaded.image'),
            meta: [
                'filename' => (string) $asset->filename,
                'mime_type' => (string) $asset->mime_type,
                'size_bytes' => (int) $asset->size_bytes,
            ],
            request: $request,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'asset' => $this->mediaLibraryPayload->asset($asset->loadMissing(['folder:id,name', 'translations'])),
                'folders' => $this->mediaLibraryPayload->folders(),
                'already_exists' => false,
            ]);
        }

        return redirect()
            ->route('admin.cms.media.index')
            ->with('status', __('cms_admin_ui.flash.uploaded.image'));
    }

    public function sort(SortCmsMediaAssetsRequest $request): JsonResponse
    {
        foreach ($request->validated('items') as $item) {
            CmsMediaAsset::query()
                ->whereKey((int) $item['id'])
                ->update(['sort_order' => (int) $item['sort_order']]);
        }

        return response()->json(['status' => 'ok']);
    }

    public function edit(int $id): Response
    {
        $asset = CmsMediaAsset::query()->findOrFail($id);

        return Inertia::render('Admin/Cms/Media/Edit', [
            'asset' => $this->mediaLibraryPayload->asset($asset),
            'activeLanguages' => $this->languageSettings->languages(true),
            'defaultLocale' => $this->languageSettings->defaultLocale(),
            'folders' => $this->mediaLibraryPayload->folders(),
        ]);
    }

    public function update(UpdateCmsMediaAssetRequest $request, int $id): RedirectResponse
    {
        $asset = CmsMediaAsset::query()->findOrFail($id);
        $this->applyMediaMetadata($asset, $request->validated());

        $this->auditLogger->success(
            action: 'cms.media.update',
            module: 'cms',
            subjectType: 'cms_media_asset',
            subjectKey: (string) $asset->id,
            message: __('cms_admin_ui.flash.saved.media_metadata'),
            meta: ['filename' => (string) $asset->filename],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.media.index')
            ->with('status', __('cms_admin_ui.flash.saved.media_metadata'));
    }

    public function metadata(UpdateCmsMediaAssetRequest $request, int $id): JsonResponse
    {
        $asset = CmsMediaAsset::query()->findOrFail($id);
        $this->applyMediaMetadata($asset, $request->validated());

        $this->auditLogger->success(
            action: 'cms.media.metadata',
            module: 'cms',
            subjectType: 'cms_media_asset',
            subjectKey: (string) $asset->id,
            message: __('cms_admin_ui.flash.saved.media_metadata'),
            meta: ['filename' => (string) $asset->filename],
            request: $request,
        );

        return response()->json([
            'asset' => $this->mediaLibraryPayload->asset($asset->loadMissing(['folder:id,name', 'translations'])),
        ]);
    }

    public function editCopy(StoreCmsEditedMediaRequest $request, int $id, CreateCmsEditedImageAssetAction $createEditedImage): JsonResponse
    {
        $sourceAsset = CmsMediaAsset::query()->findOrFail($id);
        $asset = $createEditedImage->handle($sourceAsset, $request->validated());

        $this->auditLogger->success(
            action: 'cms.media.edit_copy',
            module: 'cms',
            subjectType: 'cms_media_asset',
            subjectKey: (string) $asset->id,
            message: __('cms_admin_ui.flash.saved.edited_media'),
            meta: [
                'source_media_asset_id' => (int) $sourceAsset->id,
                'filename' => (string) $asset->filename,
            ],
            request: $request,
        );

        return response()->json([
            'asset' => $this->mediaLibraryPayload->asset($asset->loadMissing(['folder:id,name', 'translations'])),
        ]);
    }

    public function destroy(int $id, FindCmsMediaAssetUsagesAction $findUsages): RedirectResponse|JsonResponse
    {
        $asset = CmsMediaAsset::query()->findOrFail($id);
        $usages = $findUsages->handle($asset);

        if ($usages !== []) {
            if (request()->expectsJson()) {
                return response()->json([
                    'message' => __('cms_admin_ui.validation.media_delete_in_use'),
                    'usages' => $usages,
                ], 422);
            }

            return redirect()
                ->route('admin.cms.media.index')
                ->with('warning', __('cms_admin_ui.validation.media_delete_in_use'));
        }

        $path = $asset->path;
        $disk = $asset->disk ?: self::DISK;
        $directory = $this->assetDirectoryFromPath($path);

        $asset->delete();

        if (! CmsMediaAsset::withTrashed()->where('path', $path)->whereNull('deleted_at')->exists() && $this->isAssetBasedPath($path)) {
            Storage::disk($disk)->deleteDirectory($directory);
        } elseif (! CmsMediaAsset::withTrashed()->where('path', $path)->whereNull('deleted_at')->exists()) {
            Storage::disk($disk)->delete($path);
        }

        if (request()->expectsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return redirect()
            ->route('admin.cms.media.index')
            ->with('status', __('cms_admin_ui.flash.deleted.media'));
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function imageDimensions(UploadedFile $file): array
    {
        $dimensions = @getimagesize((string) $file->getRealPath());

        if (! is_array($dimensions)) {
            return [null, null];
        }

        return [(int) ($dimensions[0] ?? 0), (int) ($dimensions[1] ?? 0)];
    }

    private function mediaDirectory(): string
    {
        $siteId = TenantContext::siteId();

        if (! $siteId) {
            return self::DIRECTORY;
        }

        return self::DIRECTORY.'/site-'.$siteId;
    }

    private function pendingAssetPath(string $hash): string
    {
        return $this->mediaDirectory().'/pending/'.$this->shortHash($hash).'-'.Str::ulid();
    }

    private function assetDirectory(int $assetId, ?int $siteId = null): string
    {
        $siteId ??= TenantContext::siteId();

        if (! $siteId) {
            return self::DIRECTORY.'/site-0/assets/'.$assetId;
        }

        return self::DIRECTORY.'/site-'.$siteId.'/assets/'.$assetId;
    }

    private function assetDirectoryFromPath(?string $path): string
    {
        $path = trim((string) $path, '/');
        $originalSegment = '/original/';
        $position = strpos($path, $originalSegment);

        if ($position === false) {
            return trim(dirname($path), '/');
        }

        return substr($path, 0, $position);
    }

    private function isAssetBasedPath(?string $path): bool
    {
        $path = trim((string) $path, '/');

        return str_contains($path, '/assets/') && str_contains($path, '/original/');
    }

    private function shortHash(string $hash): string
    {
        return substr($hash, 0, 12) ?: 'image';
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyMediaMetadata(CmsMediaAsset $asset, array $validated): void
    {
        $defaultLocale = $this->languageSettings->defaultLocale();
        $defaultTranslation = (array) data_get($validated, 'translations.'.$defaultLocale, []);

        $asset->fill([
            'folder_id' => array_key_exists('folder_id', $validated) ? $validated['folder_id'] : $asset->folder_id,
            'alt_text' => $validated['alt_text'] ?? $defaultTranslation['alt_text'] ?? null,
            'caption' => $validated['caption'] ?? $defaultTranslation['caption'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $asset->sort_order,
        ]);
        $asset->save();

        $translations = (array) ($validated['translations'] ?? []);

        foreach ($this->languageSettings->languages(true) as $language) {
            $locale = (string) $language['locale'];
            $translation = (array) ($translations[$locale] ?? []);

            $asset->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'alt_text' => $this->nullableString($translation['alt_text'] ?? null),
                    'caption' => $this->nullableString($translation['caption'] ?? null),
                ],
            );
        }

        $asset->unsetRelation('translations');
        $asset->unsetRelation('folder');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
