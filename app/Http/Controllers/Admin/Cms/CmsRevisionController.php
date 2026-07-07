<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\Revisions\CmsRevisionPayloadAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsCategoryRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsEmailRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsFormRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsLayoutRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsMailTemplateRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsMenuRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsPageRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsPostRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsTagRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsTemplateRevisionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\RestoreCmsRevisionRequest;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsEmail;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMailTemplate;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsRevision;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use App\Support\Audit\AuditLogger;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CmsRevisionController extends Controller
{
    public function __construct(
        private readonly CmsRevisionPayloadAction $revisionPayload,
        private readonly CmsLocalePermission $localePermission,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function pageIndex(Request $request, int $page): JsonResponse
    {
        $pageModel = CmsPage::query()->findOrFail($page);
        $this->authorizePage($request, $pageModel);

        return response()->json([
            'revisions' => $this->revisionPayload->handle($pageModel),
        ]);
    }

    public function layoutIndex(Request $request, int $layout): JsonResponse
    {
        $layoutModel = CmsLayout::query()->findOrFail($layout);
        $this->authorizeLayout($request, $layoutModel);

        return response()->json([
            'revisions' => $this->revisionPayload->handle($layoutModel),
            'impact' => [
                'pages_count' => (int) $layoutModel->templates()->count(),
                'templates_count' => (int) $layoutModel->templates()->count(),
            ],
        ]);
    }

    public function postIndex(Request $request, int $post): JsonResponse
    {
        $postModel = CmsPost::query()->findOrFail($post);
        $this->authorizePost($request, $postModel);

        return response()->json([
            'revisions' => $this->revisionPayload->handle($postModel),
        ]);
    }

    public function menuIndex(int $menu): JsonResponse
    {
        $menuModel = CmsMenu::query()->findOrFail($menu);

        return response()->json([
            'revisions' => $this->revisionPayload->handle($menuModel),
        ]);
    }

    public function formIndex(Request $request, int $form): JsonResponse
    {
        $formModel = CmsForm::query()->findOrFail($form);
        $this->authorizeForm($request, $formModel);

        return response()->json([
            'revisions' => $this->revisionPayload->handle($formModel),
        ]);
    }

    public function categoryIndex(Request $request, int $category): JsonResponse
    {
        $categoryModel = CmsCategory::query()->findOrFail($category);
        $this->authorizeCategory($request, $categoryModel);

        return response()->json([
            'revisions' => $this->revisionPayload->handle($categoryModel),
        ]);
    }

    public function tagIndex(Request $request, int $tag): JsonResponse
    {
        $tagModel = CmsTag::query()->findOrFail($tag);
        $this->authorizeTag($request, $tagModel);

        return response()->json([
            'revisions' => $this->revisionPayload->handle($tagModel),
        ]);
    }

    public function templateIndex(Request $request, int $template): JsonResponse
    {
        $templateModel = CmsTemplate::query()->findOrFail($template);
        $this->authorizeTemplate($request, $templateModel);

        return response()->json([
            'revisions' => $this->revisionPayload->handle($templateModel),
            'impact' => [
                'usage_count' => $this->templateUsageCount($templateModel),
            ],
        ]);
    }

    public function mailTemplateIndex(int $mailTemplate): JsonResponse
    {
        $templateModel = CmsMailTemplate::query()->findOrFail($mailTemplate);

        return response()->json([
            'revisions' => $this->revisionPayload->handle($templateModel),
            'impact' => [
                'usage_count' => (int) $templateModel->emails()->count(),
            ],
        ]);
    }

    public function emailIndex(Request $request, int $email): JsonResponse
    {
        $emailModel = CmsEmail::query()->findOrFail($email);
        $this->authorizeEmail($request, $emailModel);

        return response()->json([
            'revisions' => $this->revisionPayload->handle($emailModel),
        ]);
    }

    public function pageRestore(
        RestoreCmsRevisionRequest $request,
        int $page,
        int $revision,
        RestoreCmsPageRevisionAction $restoreRevision,
    ): RedirectResponse {
        $pageModel = CmsPage::query()->findOrFail($page);
        $this->authorizePage($request, $pageModel);

        $revisionModel = CmsRevision::query()->findOrFail($revision);
        $warnings = $restoreRevision->handle(
            $pageModel,
            $revisionModel,
            (string) $request->validated('mode'),
            $request->user()?->id,
        );

        $this->auditLogger->success(
            action: 'cms.page.revision.restore',
            module: 'cms',
            subjectType: 'cms_page',
            subjectKey: (string) $pageModel->id,
            message: __('cms_admin_ui.revisions.restored'),
            meta: [
                'revision_id' => $revisionModel->id,
                'revision_number' => $revisionModel->revision_number,
                'mode' => $request->validated('mode'),
                'warnings' => $warnings,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.pages.edit', ['id' => $pageModel->id])
            ->with($this->flashKey($warnings), $this->flashMessage($warnings))
            ->with('flash_details', $this->flashDetails($warnings));
    }

    /**
     * @throws AuthorizationException
     */
    public function layoutRestore(
        RestoreCmsRevisionRequest $request,
        int $layout,
        int $revision,
        RestoreCmsLayoutRevisionAction $restoreRevision,
    ): RedirectResponse {
        $layoutModel = CmsLayout::query()->withCount('templates')->findOrFail($layout);
        $this->authorizeLayout($request, $layoutModel);

        if (
            (string) $request->validated('mode') === 'full'
            && $layoutModel->templates_count > 0
            && ! (bool) $request->validated('confirm_layout_impact')
        ) {
            throw ValidationException::withMessages([
                'revision' => __('cms_admin_ui.revisions.layout_impact_confirmation_required'),
            ]);
        }

        $revisionModel = CmsRevision::query()->findOrFail($revision);
        $warnings = $restoreRevision->handle(
            $layoutModel,
            $revisionModel,
            (string) $request->validated('mode'),
            $this->canManageCodeBlocks($request),
            $request->user()?->id,
        );

        $this->auditLogger->success(
            action: 'cms.layout.revision.restore',
            module: 'cms',
            subjectType: 'cms_layout',
            subjectKey: (string) $layoutModel->id,
            message: __('cms_admin_ui.revisions.restored'),
            meta: [
                'revision_id' => $revisionModel->id,
                'revision_number' => $revisionModel->revision_number,
                'mode' => $request->validated('mode'),
                'warnings' => $warnings,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.layouts.edit', ['id' => $layoutModel->id])
            ->with($this->flashKey($warnings), $this->flashMessage($warnings))
            ->with('flash_details', $this->flashDetails($warnings));
    }

    public function postRestore(
        RestoreCmsRevisionRequest $request,
        int $post,
        int $revision,
        RestoreCmsPostRevisionAction $restoreRevision,
    ): RedirectResponse {
        $postModel = CmsPost::query()->findOrFail($post);
        $this->authorizePost($request, $postModel);

        $revisionModel = CmsRevision::query()->findOrFail($revision);
        $warnings = $restoreRevision->handle(
            $postModel,
            $revisionModel,
            (string) $request->validated('mode'),
            $request->user()?->id,
        );

        $this->logRestore($request, 'cms.post.revision.restore', 'cms_post', (string) $postModel->id, $revisionModel, $warnings);

        return redirect()
            ->route('admin.cms.posts.edit', ['id' => $postModel->id])
            ->with($this->flashKey($warnings), $this->flashMessage($warnings))
            ->with('flash_details', $this->flashDetails($warnings));
    }

    public function menuRestore(
        RestoreCmsRevisionRequest $request,
        int $menu,
        int $revision,
        RestoreCmsMenuRevisionAction $restoreRevision,
    ): RedirectResponse {
        $menuModel = CmsMenu::query()->findOrFail($menu);
        $revisionModel = CmsRevision::query()->findOrFail($revision);
        $warnings = $restoreRevision->handle(
            $menuModel,
            $revisionModel,
            (string) $request->validated('mode'),
            $request->user()?->id,
        );

        $this->logRestore($request, 'cms.menu.revision.restore', 'cms_menu', (string) $menuModel->id, $revisionModel, $warnings);

        return redirect()
            ->route('admin.cms.menus.edit', ['id' => $menuModel->id])
            ->with($this->flashKey($warnings), $this->flashMessage($warnings))
            ->with('flash_details', $this->flashDetails($warnings));
    }

    public function formRestore(
        RestoreCmsRevisionRequest $request,
        int $form,
        int $revision,
        RestoreCmsFormRevisionAction $restoreRevision,
    ): RedirectResponse {
        $formModel = CmsForm::query()->findOrFail($form);
        $this->authorizeForm($request, $formModel);

        $revisionModel = CmsRevision::query()->findOrFail($revision);
        $warnings = $restoreRevision->handle(
            $formModel,
            $revisionModel,
            (string) $request->validated('mode'),
            $request->user()?->id,
        );

        $this->logRestore($request, 'cms.form.revision.restore', 'cms_form', (string) $formModel->id, $revisionModel, $warnings);

        return redirect()
            ->route('admin.cms.forms.edit', ['id' => $formModel->id])
            ->with($this->flashKey($warnings), $this->flashMessage($warnings))
            ->with('flash_details', $this->flashDetails($warnings));
    }

    public function categoryRestore(
        RestoreCmsRevisionRequest $request,
        int $category,
        int $revision,
        RestoreCmsCategoryRevisionAction $restoreRevision,
    ): RedirectResponse {
        $categoryModel = CmsCategory::query()->findOrFail($category);
        $this->authorizeCategory($request, $categoryModel);

        $revisionModel = CmsRevision::query()->findOrFail($revision);
        $warnings = $restoreRevision->handle(
            $categoryModel,
            $revisionModel,
            (string) $request->validated('mode'),
            $request->user()?->id,
        );

        $this->logRestore($request, 'cms.category.revision.restore', 'cms_category', (string) $categoryModel->id, $revisionModel, $warnings);

        return redirect()
            ->route('admin.cms.categories.edit', ['id' => $categoryModel->id])
            ->with($this->flashKey($warnings), $this->flashMessage($warnings))
            ->with('flash_details', $this->flashDetails($warnings));
    }

    public function tagRestore(
        RestoreCmsRevisionRequest $request,
        int $tag,
        int $revision,
        RestoreCmsTagRevisionAction $restoreRevision,
    ): RedirectResponse {
        $tagModel = CmsTag::query()->findOrFail($tag);
        $this->authorizeTag($request, $tagModel);

        $revisionModel = CmsRevision::query()->findOrFail($revision);
        $warnings = $restoreRevision->handle(
            $tagModel,
            $revisionModel,
            (string) $request->validated('mode'),
            $request->user()?->id,
        );

        $this->logRestore($request, 'cms.tag.revision.restore', 'cms_tag', (string) $tagModel->id, $revisionModel, $warnings);

        return redirect()
            ->route('admin.cms.tags.edit', ['id' => $tagModel->id])
            ->with($this->flashKey($warnings), $this->flashMessage($warnings))
            ->with('flash_details', $this->flashDetails($warnings));
    }

    public function templateRestore(
        RestoreCmsRevisionRequest $request,
        int $template,
        int $revision,
        RestoreCmsTemplateRevisionAction $restoreRevision,
    ): RedirectResponse {
        $templateModel = CmsTemplate::query()->findOrFail($template);
        $this->authorizeTemplate($request, $templateModel);

        if (
            (string) $request->validated('mode') === 'full'
            && $this->templateUsageCount($templateModel) > 0
            && ! (bool) $request->validated('confirm_template_impact')
        ) {
            throw ValidationException::withMessages([
                'revision' => __('cms_admin_ui.revisions.template_impact_confirmation_required'),
            ]);
        }

        $revisionModel = CmsRevision::query()->findOrFail($revision);
        $warnings = $restoreRevision->handle(
            $templateModel,
            $revisionModel,
            (string) $request->validated('mode'),
            $request->user()?->id,
        );

        $this->logRestore($request, 'cms.template.revision.restore', 'cms_template', (string) $templateModel->id, $revisionModel, $warnings);

        return redirect()
            ->route('admin.cms.templates.edit', ['id' => $templateModel->id])
            ->with($this->flashKey($warnings), $this->flashMessage($warnings))
            ->with('flash_details', $this->flashDetails($warnings));
    }

    public function mailTemplateRestore(
        RestoreCmsRevisionRequest $request,
        int $mailTemplate,
        int $revision,
        RestoreCmsMailTemplateRevisionAction $restoreRevision,
    ): RedirectResponse {
        $templateModel = CmsMailTemplate::query()->withCount('emails')->findOrFail($mailTemplate);

        if (
            (string) $request->validated('mode') === 'full'
            && $templateModel->emails_count > 0
            && ! (bool) $request->validated('confirm_template_impact')
        ) {
            throw ValidationException::withMessages([
                'revision' => __('cms_admin_ui.revisions.mail_template_impact_confirmation_required'),
            ]);
        }

        $revisionModel = CmsRevision::query()->findOrFail($revision);
        $warnings = $restoreRevision->handle(
            $templateModel,
            $revisionModel,
            (string) $request->validated('mode'),
            $request->user()?->id,
        );

        $this->auditLogger->success(
            action: 'cms.mail_template.revision.restore',
            module: 'cms',
            subjectType: 'cms_mail_template',
            subjectKey: (string) $templateModel->id,
            message: __('cms_admin_ui.revisions.restored'),
            meta: [
                'revision_id' => $revisionModel->id,
                'revision_number' => $revisionModel->revision_number,
                'mode' => $request->validated('mode'),
                'warnings' => $warnings,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.mail-templates.edit', ['id' => $templateModel->id])
            ->with($this->flashKey($warnings), $this->flashMessage($warnings))
            ->with('flash_details', $this->flashDetails($warnings));
    }

    public function emailRestore(
        RestoreCmsRevisionRequest $request,
        int $email,
        int $revision,
        RestoreCmsEmailRevisionAction $restoreRevision,
    ): RedirectResponse {
        $emailModel = CmsEmail::query()->findOrFail($email);
        $this->authorizeEmail($request, $emailModel);

        $revisionModel = CmsRevision::query()->findOrFail($revision);
        $warnings = $restoreRevision->handle(
            $emailModel,
            $revisionModel,
            (string) $request->validated('mode'),
            $request->user()?->id,
        );

        $this->auditLogger->success(
            action: 'cms.email.revision.restore',
            module: 'cms',
            subjectType: 'cms_email',
            subjectKey: (string) $emailModel->id,
            message: __('cms_admin_ui.revisions.restored'),
            meta: [
                'revision_id' => $revisionModel->id,
                'revision_number' => $revisionModel->revision_number,
                'mode' => $request->validated('mode'),
                'warnings' => $warnings,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.emails.edit', ['id' => $emailModel->id])
            ->with($this->flashKey($warnings), $this->flashMessage($warnings))
            ->with('flash_details', $this->flashDetails($warnings));
    }

    private function authorizePage(Request $request, CmsPage $page): void
    {
        abort_unless($this->localePermission->canEditLocale($request->user(), (string) $page->locale), 403);
    }

    private function authorizeLayout(Request $request, CmsLayout $layout): void
    {
        abort_unless($this->localePermission->canEditLocale($request->user(), (string) $layout->locale), 403);
    }

    private function authorizePost(Request $request, CmsPost $post): void
    {
        abort_unless($this->localePermission->canEditLocale($request->user(), (string) $post->locale), 403);
    }

    private function authorizeForm(Request $request, CmsForm $form): void
    {
        abort_unless($this->localePermission->canEditLocale($request->user(), (string) $form->locale), 403);
    }

    private function authorizeCategory(Request $request, CmsCategory $category): void
    {
        abort_unless($this->localePermission->canEditLocale($request->user(), (string) $category->locale), 403);
    }

    private function authorizeTag(Request $request, CmsTag $tag): void
    {
        abort_unless($this->localePermission->canEditLocale($request->user(), (string) $tag->locale), 403);
    }

    private function authorizeTemplate(Request $request, CmsTemplate $template): void
    {
        abort_unless($this->localePermission->canEditLocale($request->user(), (string) $template->locale), 403);
    }

    private function authorizeEmail(Request $request, CmsEmail $email): void
    {
        abort_unless($this->localePermission->canEditLocale($request->user(), (string) $email->locale), 403);
    }

    private function templateUsageCount(CmsTemplate $template): int
    {
        return match ($template->template_class) {
            'page' => CmsPage::query()->where('detail_template_id', $template->id)->count(),
            'blog' => CmsPost::query()->where('detail_template_id', $template->id)->count(),
            'category' => CmsCategory::query()
                ->where('archive_template_id', $template->id)
                ->orWhere('detail_template_id', $template->id)
                ->count(),
            'tag' => CmsTag::query()
                ->where('archive_template_id', $template->id)
                ->orWhere('detail_template_id', $template->id)
                ->count(),
            default => 0,
        };
    }

    private function canManageCodeBlocks(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user?->is_platform_admin || $user?->canAccessRoute('admin.cms.layouts.code-blocks.manage'));
    }

    /**
     * @param  array<string, mixed>  $warnings
     */
    private function logRestore(Request $request, string $action, string $subjectType, string $subjectKey, CmsRevision $revision, array $warnings): void
    {
        $this->auditLogger->success(
            action: $action,
            module: 'cms',
            subjectType: $subjectType,
            subjectKey: $subjectKey,
            message: __('cms_admin_ui.revisions.restored'),
            meta: [
                'revision_id' => $revision->id,
                'revision_number' => $revision->revision_number,
                'mode' => $request->validated('mode'),
                'warnings' => $warnings,
            ],
            request: $request,
        );
    }

    /**
     * @param  array<string, mixed>  $warnings
     */
    private function flashKey(array $warnings): string
    {
        return collect($warnings)
            ->only($this->warningDetailKeys())
            ->filter(fn (mixed $value): bool => (int) $value > 0)
            ->isNotEmpty()
            ? 'warning'
            : 'status';
    }

    /**
     * @param  array<string, mixed>  $warnings
     */
    private function flashMessage(array $warnings): string
    {
        return $this->flashKey($warnings) === 'warning'
            ? __('cms_admin_ui.revisions.restored_with_warnings')
            : __('cms_admin_ui.revisions.restored');
    }

    /**
     * @param  array<string, mixed>  $warnings
     * @return array<int, array{message: string}>
     */
    private function flashDetails(array $warnings): array
    {
        $detailTranslationKeys = $this->detailTranslationKeys();
        $details = [
            ['message' => __('cms_admin_ui.revisions.details.audit_versions_created')],
        ];

        foreach ($warnings as $key => $value) {
            $count = (int) $value;

            if ($count <= 0 || ! array_key_exists($key, $detailTranslationKeys)) {
                continue;
            }

            $details[] = [
                'message' => __($detailTranslationKeys[$key], ['count' => $count]),
            ];
        }

        return $details;
    }

    /**
     * @return array<int, string>
     */
    private function warningDetailKeys(): array
    {
        return [
            'missing_blocks',
            'missing_categories',
            'missing_tags',
            'deactivated_sections',
            'deactivated_placements',
            'deactivated_fields',
            'deactivated_items',
            'blocked_relations',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function detailTranslationKeys(): array
    {
        return [
            'updated_fields' => 'cms_admin_ui.revisions.details.updated_fields',
            'missing_blocks' => 'cms_admin_ui.revisions.details.missing_blocks',
            'missing_categories' => 'cms_admin_ui.revisions.details.missing_categories',
            'missing_tags' => 'cms_admin_ui.revisions.details.missing_tags',
            'deactivated_sections' => 'cms_admin_ui.revisions.details.deactivated_sections',
            'deactivated_placements' => 'cms_admin_ui.revisions.details.deactivated_placements',
            'deactivated_fields' => 'cms_admin_ui.revisions.details.deactivated_fields',
            'deactivated_items' => 'cms_admin_ui.revisions.details.deactivated_items',
            'blocked_relations' => 'cms_admin_ui.revisions.details.blocked_relations',
        ];
    }
}
