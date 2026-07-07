<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\MoveCmsDownloadFolderRequest;
use App\Http\Requests\Admin\Cms\StoreCmsDownloadFolderRequest;
use App\Http\Requests\Admin\Cms\UpdateCmsDownloadFolderRequest;
use App\Models\Cms\CmsDownloadAccessRule;
use App\Models\Cms\CmsDownloadFolder;
use App\Support\Cms\CmsDownloadLibraryPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CmsDownloadFolderController extends Controller
{
    public function __construct(private readonly CmsDownloadLibraryPayload $downloadLibraryPayload) {}

    public function store(StoreCmsDownloadFolderRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $parentId = $validated['parent_id'] ?? null;
        $accessMode = $validated['access_mode'] ?? 'inherit';

        if (filled($validated['password'] ?? null) && $accessMode === 'inherit') {
            $accessMode = 'password';
        }

        $folder = CmsDownloadFolder::query()->create([
            'parent_id' => $parentId,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug((string) $validated['name'], $parentId),
            'access_mode' => $accessMode,
            'password_hash' => filled($validated['password'] ?? null) ? Hash::make((string) $validated['password']) : null,
            'password_expires_minutes' => $validated['password_expires_minutes'] ?? null,
            'sort_order' => ((int) CmsDownloadFolder::query()->where('parent_id', $parentId)->max('sort_order')) + 1,
        ]);
        $this->syncAccessRules((int) $folder->id, (array) ($validated['access_rules'] ?? []));

        if ($request->expectsJson()) {
            return response()->json([
                'folder' => $this->downloadLibraryPayload->folder($folder->refresh()),
                'folders' => $this->downloadLibraryPayload->folders(),
            ]);
        }

        return redirect()->route('admin.cms.downloads.index')->with('status', __('cms_admin_ui.flash.created.download_folder'));
    }

    public function update(UpdateCmsDownloadFolderRequest $request, int $folder): JsonResponse
    {
        $downloadFolder = CmsDownloadFolder::query()->findOrFail($folder);
        $validated = $request->validated();
        $accessMode = $validated['access_mode'];

        if (filled($validated['password'] ?? null) && $accessMode === 'inherit') {
            $accessMode = 'password';
        }

        $downloadFolder->fill([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug((string) $validated['name'], $downloadFolder->parent_id, $downloadFolder->id),
            'access_mode' => $accessMode,
            'password_expires_minutes' => $validated['password_expires_minutes'] ?? null,
        ]);

        if ((bool) ($validated['clear_password'] ?? false)) {
            $downloadFolder->password_hash = null;
        }

        if (filled($validated['password'] ?? null)) {
            $downloadFolder->password_hash = Hash::make((string) $validated['password']);
        }

        $downloadFolder->save();
        $this->syncAccessRules((int) $downloadFolder->id, (array) ($validated['access_rules'] ?? []));

        return response()->json([
            'folder' => $this->downloadLibraryPayload->folder($downloadFolder->refresh()),
            'folders' => $this->downloadLibraryPayload->folders(),
        ]);
    }

    public function move(MoveCmsDownloadFolderRequest $request, int $folder): JsonResponse
    {
        $downloadFolder = CmsDownloadFolder::query()->findOrFail($folder);
        $parentId = $request->validated('parent_id');

        if ($this->wouldCreateCycle($downloadFolder, $parentId)) {
            return response()->json([
                'message' => __('cms_admin_ui.validation.download_folder_parent_cycle'),
                'errors' => [
                    'parent_id' => [__('cms_admin_ui.validation.download_folder_parent_cycle')],
                ],
            ], 422);
        }

        $downloadFolder->fill([
            'parent_id' => $parentId,
            'slug' => $this->uniqueSlug((string) $downloadFolder->name, $parentId, $downloadFolder->id),
            'sort_order' => ((int) CmsDownloadFolder::query()->where('parent_id', $parentId)->max('sort_order')) + 1,
        ]);
        $downloadFolder->save();

        return response()->json([
            'folder' => $this->downloadLibraryPayload->folder($downloadFolder),
            'folders' => $this->downloadLibraryPayload->folders(),
        ]);
    }

    private function uniqueSlug(string $name, ?int $parentId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'folder';
        $slug = $baseSlug;
        $suffix = 2;

        while (CmsDownloadFolder::query()
            ->where('parent_id', $parentId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function wouldCreateCycle(CmsDownloadFolder $folder, ?int $parentId): bool
    {
        if ($parentId === null) {
            return false;
        }

        if ((int) $folder->id === (int) $parentId) {
            return true;
        }

        $current = CmsDownloadFolder::query()->find($parentId);

        while ($current instanceof CmsDownloadFolder) {
            if ((int) $current->parent_id === (int) $folder->id) {
                return true;
            }

            $current = $current->parent_id ? CmsDownloadFolder::query()->find($current->parent_id) : null;
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     */
    private function syncAccessRules(int $folderId, array $rules): void
    {
        CmsDownloadAccessRule::query()->where('subject_type', 'folder')->where('subject_id', $folderId)->delete();

        foreach (array_values($rules) as $index => $rule) {
            CmsDownloadAccessRule::query()->create([
                'subject_type' => 'folder',
                'subject_id' => $folderId,
                'rule_type' => (string) ($rule['rule_type'] ?? ''),
                'site_user_id' => $rule['site_user_id'] ?? null,
                'cms_download_group_id' => $rule['cms_download_group_id'] ?? null,
                'profile_field_key' => filled($rule['profile_field_key'] ?? null) ? (string) $rule['profile_field_key'] : null,
                'operator' => filled($rule['operator'] ?? null) ? (string) $rule['operator'] : null,
                'value' => is_array($rule['value'] ?? null) ? $rule['value'] : (filled($rule['value'] ?? null) ? [(string) $rule['value']] : null),
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }
}
