<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\MoveCmsMediaFolderRequest;
use App\Http\Requests\Admin\Cms\StoreCmsMediaFolderRequest;
use App\Http\Requests\Admin\Cms\UpdateCmsMediaFolderRequest;
use App\Models\Cms\CmsMediaFolder;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class CmsMediaFolderController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function store(StoreCmsMediaFolderRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $parentId = $validated['parent_id'] ?? null;
        $slug = $this->uniqueSlug((string) $validated['name'], $parentId);

        $folder = CmsMediaFolder::query()->create([
            'parent_id' => $parentId,
            'name' => $validated['name'],
            'slug' => $slug,
            'sort_order' => ((int) CmsMediaFolder::query()
                ->where('parent_id', $parentId)
                ->max('sort_order')) + 1,
        ]);

        $this->auditLogger->success(
            action: 'cms.media-folder.create',
            module: 'cms',
            subjectType: 'cms_media_folder',
            subjectKey: (string) $folder->id,
            message: __('cms_admin_ui.flash.created.media_folder'),
            meta: ['name' => (string) $folder->name],
            request: $request,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'folder' => $this->folderPayload($folder),
            ]);
        }

        return redirect()
            ->route('admin.cms.media.index')
            ->with('status', __('cms_admin_ui.flash.created.media_folder'));
    }

    public function update(UpdateCmsMediaFolderRequest $request, int $folder): JsonResponse
    {
        $mediaFolder = CmsMediaFolder::query()->findOrFail($folder);
        $validated = $request->validated();

        $mediaFolder->fill([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug((string) $validated['name'], $mediaFolder->parent_id, $mediaFolder->id),
        ]);
        $mediaFolder->save();

        return response()->json([
            'folder' => $this->folderPayload($mediaFolder),
        ]);
    }

    public function move(MoveCmsMediaFolderRequest $request, int $folder): JsonResponse
    {
        $mediaFolder = CmsMediaFolder::query()->findOrFail($folder);
        $parentId = $request->validated('parent_id');

        if ($this->wouldCreateCycle($mediaFolder, $parentId)) {
            return response()->json([
                'message' => __('cms_admin_ui.validation.media_folder_parent_cycle'),
                'errors' => [
                    'parent_id' => [__('cms_admin_ui.validation.media_folder_parent_cycle')],
                ],
            ], 422);
        }

        $mediaFolder->fill([
            'parent_id' => $parentId,
            'slug' => $this->uniqueSlug((string) $mediaFolder->name, $parentId, $mediaFolder->id),
            'sort_order' => ((int) CmsMediaFolder::query()->where('parent_id', $parentId)->max('sort_order')) + 1,
        ]);
        $mediaFolder->save();

        return response()->json([
            'folder' => $this->folderPayload($mediaFolder),
        ]);
    }

    private function uniqueSlug(string $name, ?int $parentId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'map';
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($slug, $parentId, $ignoreId)) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $parentId, ?int $ignoreId = null): bool
    {
        return CmsMediaFolder::query()
            ->where('parent_id', $parentId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    private function wouldCreateCycle(CmsMediaFolder $folder, ?int $parentId): bool
    {
        if ($parentId === null) {
            return false;
        }

        if ((int) $folder->id === (int) $parentId) {
            return true;
        }

        $current = CmsMediaFolder::query()->find($parentId);

        while ($current instanceof CmsMediaFolder) {
            if ((int) $current->parent_id === (int) $folder->id) {
                return true;
            }

            $current = $current->parent_id
                ? CmsMediaFolder::query()->find($current->parent_id)
                : null;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function folderPayload(CmsMediaFolder $folder): array
    {
        return [
            'id' => $folder->id,
            'parent_id' => $folder->parent_id,
            'name' => $folder->name,
            'slug' => $folder->slug,
            'sort_order' => $folder->sort_order,
        ];
    }
}
