<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\EnsureCmsDownloadContextFolderAction;
use App\Actions\Admin\Cms\StoreCmsDownloadFileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\ReplaceCmsDownloadFileRequest;
use App\Http\Requests\Admin\Cms\UpdateCmsDownloadAssetRequest;
use App\Http\Requests\Admin\Cms\UploadCmsDownloadRequest;
use App\Models\Cms\CmsDownloadAccessRule;
use App\Models\Cms\CmsDownloadAsset;
use App\Support\Audit\AuditLogger;
use App\Support\Cms\CmsDownloadLibraryPayload;
use App\Support\PublicSite\CmsLanguageSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CmsDownloadController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CmsDownloadLibraryPayload $downloadLibraryPayload,
        private readonly CmsLanguageSettings $languageSettings,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Cms/Downloads/Index', $this->indexPayload());
    }

    /**
     * @return array<string, mixed>
     */
    private function indexPayload(?array $editAsset = null): array
    {
        return [
            'assets' => $this->downloadLibraryPayload->assets(),
            'folders' => $this->downloadLibraryPayload->folders(),
            'groups' => $this->downloadLibraryPayload->groups(),
            'siteUsers' => $this->downloadLibraryPayload->siteUsers(),
            'editAsset' => $editAsset,
        ];
    }

    public function store(
        UploadCmsDownloadRequest $request,
        EnsureCmsDownloadContextFolderAction $contextFolderAction,
        StoreCmsDownloadFileAction $storeFile,
    ): RedirectResponse|JsonResponse {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $contextFolder = $contextFolderAction->handle(
            $request->validated('context_type'),
            $request->validated('context_id'),
        );
        $folderId = $contextFolder?->id ?? $request->validated('folder_id');

        $asset = $storeFile->create($file, [
            'folder_id' => $folderId,
            'uploaded_by' => $request->user()?->id,
            'access_mode' => $request->validated('access_mode') ?: 'inherit',
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'published_at' => $request->validated('published_at'),
            'expires_at' => $request->validated('expires_at'),
            'metadata' => [
                'uploaded_from' => $request->validated('uploaded_from'),
                'context' => [
                    'type' => $request->validated('context_type'),
                    'id' => $request->validated('context_id'),
                ],
            ],
            'sort_order' => ((int) (CmsDownloadAsset::query()->max('sort_order') ?? 0)) + 1,
        ]);

        $this->auditLogger->success(
            action: 'cms.download.upload',
            module: 'cms',
            subjectType: 'cms_download_asset',
            subjectKey: (string) $asset->id,
            message: __('cms_admin_ui.flash.uploaded.download'),
            meta: [
                'filename' => (string) $asset->filename,
                'mime_type' => (string) $asset->mime_type,
                'size_bytes' => (int) $asset->size_bytes,
            ],
            request: $request,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'asset' => $this->downloadLibraryPayload->asset($asset->loadMissing(['folder:id,name', 'translations'])),
                'folders' => $this->downloadLibraryPayload->folders(),
            ]);
        }

        return redirect()
            ->route('admin.cms.downloads.edit', ['download' => $asset->id])
            ->with('status', __('cms_admin_ui.flash.uploaded.download'));
    }

    public function edit(int $download): Response
    {
        $asset = CmsDownloadAsset::query()
            ->with(['translations', 'accessRules'])
            ->findOrFail($download);

        return Inertia::render('Admin/Cms/Downloads/Index', $this->indexPayload(
            $this->downloadAssetPayload($asset),
        ));
    }

    public function update(UpdateCmsDownloadAssetRequest $request, int $download): RedirectResponse|JsonResponse
    {
        $asset = CmsDownloadAsset::query()->findOrFail($download);
        $validated = $request->validated();

        $asset->fill([
            'folder_id' => $validated['folder_id'] ?? null,
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'access_mode' => $validated['access_mode'],
            'published_at' => $validated['published_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $asset->sort_order,
        ]);
        $asset->save();
        $this->syncTranslations($asset, (array) ($validated['translations'] ?? []));
        $this->syncAccessRules('asset', (int) $asset->id, (array) ($validated['access_rules'] ?? []));

        $this->auditLogger->success(
            action: 'cms.download.update',
            module: 'cms',
            subjectType: 'cms_download_asset',
            subjectKey: (string) $asset->id,
            message: __('cms_admin_ui.flash.saved.download'),
            meta: ['filename' => (string) $asset->filename],
            request: $request,
        );

        if ($request->expectsJson()) {
            return response()->json(['asset' => $this->downloadAssetPayload($asset->refresh()->loadMissing(['folder:id,name', 'translations', 'accessRules']))]);
        }

        return redirect()
            ->route('admin.cms.downloads.edit', ['download' => $asset->id])
            ->with('status', __('cms_admin_ui.flash.saved.download'));
    }

    public function replaceFile(ReplaceCmsDownloadFileRequest $request, int $download, StoreCmsDownloadFileAction $storeFile): RedirectResponse|JsonResponse
    {
        $asset = CmsDownloadAsset::query()->findOrFail($download);
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $storeFile->replace($asset, $file);

        $this->auditLogger->success(
            action: 'cms.download.replace_file',
            module: 'cms',
            subjectType: 'cms_download_asset',
            subjectKey: (string) $asset->id,
            message: __('cms_admin_ui.flash.saved.download_file_replaced'),
            meta: ['filename' => (string) $asset->filename],
            request: $request,
        );

        if ($request->expectsJson()) {
            return response()->json(['asset' => $this->downloadAssetPayload($asset->refresh()->loadMissing(['folder:id,name', 'translations', 'accessRules']))]);
        }

        return redirect()
            ->route('admin.cms.downloads.edit', ['download' => $asset->id])
            ->with('status', __('cms_admin_ui.flash.saved.download_file_replaced'));
    }

    public function destroy(int $download): RedirectResponse|JsonResponse
    {
        $asset = CmsDownloadAsset::query()->findOrFail($download);
        $path = $asset->path;
        $disk = $asset->disk ?: (string) config('cms_downloads.disk', 'private');

        $asset->delete();

        if (is_string($path) && $path !== '') {
            Storage::disk($disk)->delete($path);
        }

        if (request()->expectsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return redirect()
            ->route('admin.cms.downloads.index')
            ->with('status', __('cms_admin_ui.flash.deleted.download'));
    }

    /**
     * @param  array<string, array<string, string|null>>  $translations
     */
    private function syncTranslations(CmsDownloadAsset $asset, array $translations): void
    {
        foreach ($this->languageSettings->languages(true) as $language) {
            $locale = (string) $language['locale'];
            $translation = (array) ($translations[$locale] ?? []);

            $asset->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'title' => $this->nullableString($translation['title'] ?? null),
                    'description' => $this->nullableString($translation['description'] ?? null),
                ],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function downloadAssetPayload(CmsDownloadAsset $asset): array
    {
        $asset->loadMissing(['folder:id,name', 'translations', 'accessRules']);

        return [
            ...$this->downloadLibraryPayload->asset($asset),
            'access_rules' => $asset->accessRules->map(fn (CmsDownloadAccessRule $rule): array => $this->accessRulePayload($rule))->values()->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     */
    private function syncAccessRules(string $subjectType, int $subjectId, array $rules): void
    {
        CmsDownloadAccessRule::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->delete();

        foreach (array_values($rules) as $index => $rule) {
            CmsDownloadAccessRule::query()->create([
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'rule_type' => (string) ($rule['rule_type'] ?? ''),
                'site_user_id' => $rule['site_user_id'] ?? null,
                'cms_download_group_id' => $rule['cms_download_group_id'] ?? null,
                'profile_field_key' => $this->nullableString($rule['profile_field_key'] ?? null),
                'operator' => $this->nullableString($rule['operator'] ?? null),
                'value' => $this->accessRuleValue($rule['value'] ?? null),
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function accessRulePayload(CmsDownloadAccessRule $rule): array
    {
        return [
            'rule_type' => $rule->rule_type,
            'site_user_id' => $rule->site_user_id,
            'cms_download_group_id' => $rule->cms_download_group_id,
            'profile_field_key' => $rule->profile_field_key,
            'operator' => $rule->operator,
            'value' => $rule->value,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<int, string>|null
     */
    private function accessRuleValue(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return collect($value)->map(fn (mixed $item): string => (string) $item)->filter()->values()->all();
        }

        return [(string) $value];
    }
}
