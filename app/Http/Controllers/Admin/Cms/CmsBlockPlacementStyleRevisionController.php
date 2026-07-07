<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\PublishCmsPlacementStyleRevisionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\PublishCmsPlacementStyleRevisionRequest;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsBlockPlacementStyleRevision;
use App\Support\Audit\AuditLogger;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CmsBlockPlacementStyleRevisionController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request, int $placement): JsonResponse
    {
        $placement = $this->placement($placement);

        $this->authorizePlacement($request, $placement);

        return response()->json([
            'revisions' => $this->revisionPayload($placement),
        ]);
    }

    public function publish(
        PublishCmsPlacementStyleRevisionRequest $request,
        int $placement,
        PublishCmsPlacementStyleRevisionAction $publishStyleRevision,
    ): RedirectResponse {
        $placement = $this->placement($placement);

        $revision = $publishStyleRevision->handle(
            $placement,
            (string) $request->validated('css_source'),
            (array) ($request->validated('style_config') ?? []),
            $request->user()?->id,
        );

        $this->auditLogger->success(
            action: 'cms.block-placement-style-revision.publish',
            module: 'cms',
            subjectType: 'cms_block_placement',
            subjectKey: (string) $placement->id,
            message: __('cms_admin_ui.flash.block_placement_style_published'),
            meta: [
                'revision_id' => $revision->id,
                'revision_number' => $revision->revision_number,
            ],
            request: $request,
        );

        return back()->with('status', __('cms_admin_ui.flash.block_placement_style_published'));
    }

    public function republish(
        Request $request,
        int $placement,
        int $revision,
        PublishCmsPlacementStyleRevisionAction $publishStyleRevision,
    ): RedirectResponse {
        $placement = $this->placement($placement);
        $revision = $this->revision($revision);

        $this->authorizePlacement($request, $placement);
        abort_unless((int) $revision->cms_block_placement_id === (int) $placement->id, 404);
        abort_unless($revision->status === 'published', 422);

        $publishStyleRevision->republish($placement, $revision);

        $this->auditLogger->success(
            action: 'cms.block-placement-style-revision.republish',
            module: 'cms',
            subjectType: 'cms_block_placement',
            subjectKey: (string) $placement->id,
            message: __('cms_admin_ui.flash.block_placement_style_republished'),
            meta: [
                'revision_id' => $revision->id,
                'revision_number' => $revision->revision_number,
            ],
            request: $request,
        );

        return back()->with('status', __('cms_admin_ui.flash.block_placement_style_republished'));
    }

    private function placement(int $placement): CmsBlockPlacement
    {
        return CmsBlockPlacement::query()->findOrFail($placement);
    }

    private function revision(int $revision): CmsBlockPlacementStyleRevision
    {
        return CmsBlockPlacementStyleRevision::query()->findOrFail($revision);
    }

    private function authorizePlacement(Request $request, CmsBlockPlacement $placement): void
    {
        $user = $request->user();

        abort_unless((bool) ($user?->is_platform_admin || $user?->canAccessRoute('admin.cms.layouts.code-blocks.manage')), 403);

        $placement->loadMissing('section.owner');
        $owner = $placement->section?->owner;
        $locale = is_scalar($owner?->locale ?? null) ? (string) $owner->locale : '';

        abort_unless($locale === '' || app(CmsLocalePermission::class)->canEditLocale($user, $locale), 403);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function revisionPayload(CmsBlockPlacement $placement): array
    {
        return $placement->styleRevisions()
            ->with('author:id,name,email')
            ->get()
            ->map(fn (CmsBlockPlacementStyleRevision $revision): array => [
                'id' => (int) $revision->id,
                'revision_number' => (int) $revision->revision_number,
                'status' => (string) $revision->status,
                'title' => (string) $revision->title,
                'style_config' => $revision->style_config ?? [],
                'css_source' => (string) $revision->css_source,
                'css_preview' => mb_strimwidth((string) $revision->css_source, 0, 160, '...'),
                'author' => $revision->author ? [
                    'id' => (int) $revision->author->id,
                    'name' => (string) ($revision->author->name ?? $revision->author->email),
                ] : null,
                'published_at' => $revision->published_at?->toIso8601String(),
                'is_current' => (int) $placement->published_style_revision_id === (int) $revision->id,
            ])
            ->values()
            ->all();
    }
}
