<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\StoreCmsDownloadGroupRequest;
use App\Models\Cms\CmsDownloadGroup;
use App\Support\Cms\CmsDownloadLibraryPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CmsDownloadGroupController extends Controller
{
    public function __construct(private readonly CmsDownloadLibraryPayload $downloadLibraryPayload) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Cms/Downloads/Groups', [
            'groups' => CmsDownloadGroup::query()
                ->with('siteUsers:id,name,email')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (CmsDownloadGroup $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'slug' => $group->slug,
                    'description' => $group->description,
                    'is_active' => (bool) $group->is_active,
                    'site_user_ids' => $group->siteUsers->pluck('id')->values()->all(),
                    'site_users_label' => $group->siteUsers->pluck('name')->join(', '),
                    'created_at' => optional($group->created_at)->toDateTimeString(),
                    'updated_at' => optional($group->updated_at)->toDateTimeString(),
                ]),
            'siteUsers' => $this->downloadLibraryPayload->siteUsers(),
        ]);
    }

    public function store(StoreCmsDownloadGroupRequest $request, ?int $group = null): RedirectResponse
    {
        $validated = $request->validated();
        $downloadGroup = $group ? CmsDownloadGroup::query()->findOrFail($group) : new CmsDownloadGroup;
        $downloadGroup->fill([
            'name' => $validated['name'],
            'slug' => ($validated['slug'] ?? '') ?: Str::slug((string) $validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);
        $downloadGroup->save();
        $downloadGroup->siteUsers()->sync((array) ($validated['site_user_ids'] ?? []));

        return redirect()->route('admin.cms.download-groups.index')->with('status', __('cms_admin_ui.flash.saved.download_group'));
    }
}
