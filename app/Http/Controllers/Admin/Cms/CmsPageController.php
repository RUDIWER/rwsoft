<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\CreateCmsPageTranslationAction;
use App\Actions\Admin\Cms\EnsureCmsTemplateDataUsesContextImagesAction;
use App\Actions\Admin\Cms\GenerateCmsHtmlAnchorAction;
use App\Actions\Admin\Cms\Health\EnsureCmsSlugRedirectAction;
use App\Actions\Admin\Cms\Health\ValidateCmsPublishReadinessAction;
use App\Actions\Admin\Cms\Revisions\BuildCmsPageRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\CmsRevisionPayloadAction;
use App\Actions\Admin\Cms\Revisions\CreateCmsRevisionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\StoreCmsPageRequest;
use App\Http\Requests\Admin\Cms\StoreCmsPageTranslationRequest;
use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsColorPaletteItem;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use App\Support\Audit\AuditLogger;
use App\Support\Cms\CmsBlockRegistry;
use App\Support\Cms\CmsDownloadLibraryPayload;
use App\Support\Cms\CmsMediaLibraryPayload;
use App\Support\Cms\CmsResponsiveLayoutNormalizer;
use App\Support\Cms\CmsStyleTokenOptions;
use App\Support\Cms\CmsTemplateBlockDataContractBuilder;
use App\Support\Cms\CmsTemplateDataContract;
use App\Support\Cms\Seo\CmsSeoSettings;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsStructuredDataBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CmsPageController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CmsStructuredDataBuilder $structuredDataBuilder,
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsMediaLibraryPayload $mediaLibraryPayload,
        private readonly CmsDownloadLibraryPayload $downloadLibraryPayload,
        private readonly CmsStyleTokenOptions $styleTokenOptions,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Cms/Pages/Index', [
            'pages' => CmsPage::query()
                ->with('parent:id,title')
                ->whereNotIn('id', $this->categoryLandingPageIdsQuery())
                ->whereNotIn('id', $this->tagLandingPageIdsQuery())
                ->orderByDesc('updated_at')
                ->get(['id', 'parent_id', 'title', 'slug', 'locale', 'status', 'is_home', 'published_at', 'updated_at']),
        ]);
    }

    public function create(): Response
    {
        return $this->edit(0);
    }

    public function edit(int $id): Response
    {
        $request = request();
        $page = $id > 0
            ? CmsPage::query()->findOrFail($id)
            : null;

        $canManageCodeBlocks = $this->canManageCodeBlocks($request);

        return Inertia::render('Admin/Cms/Pages/Edit', [
            'pageItem' => $page ? $this->pagePayload($page, $canManageCodeBlocks) : null,
            'translations' => $page ? $this->translationPayload($page) : [],
            'revisions' => $page ? app(CmsRevisionPayloadAction::class)->handle($page) : [],
            'missingLanguages' => $page ? $this->missingLanguagePayload($page) : [],
            'activeLanguages' => $this->languageSettings->languages(true),
            'multilingualEnabled' => $this->languageSettings->multilingualEnabled(),
            'availableLocales' => $this->languageSettings->activeLocales(),
            'canManageCodeBlocks' => $canManageCodeBlocks,
            'colorPaletteItems' => CmsColorPaletteItem::activePayload(),
            'styleTokenOptions' => $this->styleTokenOptions->all(),
            'activeThemeFontFaceCss' => $this->styleTokenOptions->activeFontFaceCss(),
            'detailTemplateOptions' => $this->templateOptions(),
            'parentOptions' => CmsPage::query()
                ->when($page, fn ($query) => $query->whereNotIn('id', $this->excludedParentIds($page)))
                ->whereNotIn('id', $this->categoryLandingPageIdsQuery())
                ->whereNotIn('id', $this->tagLandingPageIdsQuery())
                ->orderBy('title')
                ->get(['id', 'title', 'locale']),
            'statusOptions' => $this->statusOptions(),
            'seoSettings' => app(CmsSeoSettings::class)->values(),
            'mediaOptions' => $this->mediaLibraryPayload->assets(),
            'mediaFolders' => $this->mediaLibraryPayload->folders(),
            'downloadOptions' => $this->downloadLibraryPayload->assets(),
            'downloadFolders' => $this->downloadLibraryPayload->folders(),
            'structuredData' => $this->structuredDataPayload($page),
        ]);
    }

    public function store(
        StoreCmsPageRequest $request,
        int $id,
        BuildCmsPageRevisionSnapshotAction $buildRevisionSnapshot,
        CreateCmsRevisionAction $createRevision,
        ValidateCmsPublishReadinessAction $publishReadiness,
        EnsureCmsSlugRedirectAction $ensureSlugRedirect,
        EnsureCmsTemplateDataUsesContextImagesAction $ensureTemplateDataUsesContextImages,
    ): RedirectResponse {
        $validated = $request->validated();

        $readiness = $validated['status'] === 'published'
            ? $publishReadiness->content($validated, 'page')
            : ['errors' => [], 'warnings' => []];

        if ($readiness['errors'] !== []) {
            return back()->withErrors(['status' => implode(' ', $readiness['errors'])])->withInput();
        }

        $page = $id > 0
            ? CmsPage::query()->findOrFail($id)
            : new CmsPage;
        $isCreate = ! $page->exists;
        $oldSlug = $page->exists ? (string) $page->slug : null;
        $oldStatus = $page->exists ? (string) $page->status : null;

        DB::transaction(function () use ($page, $validated, $request, $buildRevisionSnapshot, $createRevision, $ensureTemplateDataUsesContextImages): void {
            if ((bool) ($validated['is_home'] ?? false)) {
                CmsPage::query()
                    ->where('locale', $validated['locale'])
                    ->when($page->exists, fn ($query) => $query->where('id', '!=', $page->id))
                    ->update(['is_home' => false]);
            }

            $page->fill($this->pageData($validated, $page, $this->canManageCodeBlocks($request)));

            if (! $page->exists) {
                $page->author_id = $request->user()?->id;
            }

            if ($page->status === 'published' && blank($page->published_at)) {
                $page->published_at = now();
            }

            if (blank($page->translation_key)) {
                $page->translation_key = (string) Str::ulid();
            }

            $page->save();

            $template = CmsTemplate::query()->find((int) $page->detail_template_id);

            if ($template instanceof CmsTemplate) {
                $page->forceFill([
                    'template_data' => $ensureTemplateDataUsesContextImages->handle(
                        $template,
                        is_array($page->template_data ?? null) ? $page->template_data : [],
                        'page',
                        (int) $page->id,
                    ),
                ])->save();
            }

            $createRevision->handle(
                $page,
                'full',
                $buildRevisionSnapshot->handle($page),
                $request->user()?->id,
                __('cms_admin_ui.revisions.auto_revision_title'),
                metadata: ['change_type' => $page->wasRecentlyCreated ? 'create' : 'update'],
            );
        });

        $this->auditLogger->success(
            action: $isCreate ? 'cms.page.create' : 'cms.page.update',
            module: 'cms',
            subjectType: 'cms_page',
            subjectKey: (string) $page->id,
            message: __('cms_admin_ui.flash.saved.page'),
            meta: [
                'title' => (string) $page->title,
                'slug' => (string) $page->slug,
                'locale' => (string) $page->locale,
                'status' => (string) $page->status,
            ],
            request: $request,
        );

        $ensureSlugRedirect->handle('page', $page->locale, $oldSlug, $page->slug, $oldStatus, $page->status, $page->id, $request);

        $redirect = redirect()
            ->route('admin.cms.pages.edit', ['id' => $page->id])
            ->with('status', __('cms_admin_ui.flash.saved.page'));

        if ($readiness['warnings'] !== []) {
            $redirect->with('warning', __('cms_admin_ui.flash.saved_with_warnings', [
                'message' => implode(' ', $readiness['warnings']),
            ]));
        }

        return $redirect;
    }

    public function storeTranslation(
        StoreCmsPageTranslationRequest $request,
        int $id,
        CreateCmsPageTranslationAction $createTranslation,
    ): RedirectResponse {
        $validated = $request->validated();
        $page = CmsPage::query()->findOrFail($id);
        $useAi = (bool) ($validated['use_ai'] ?? false);

        try {
            $translation = $createTranslation->handle(
                sourcePage: $page,
                targetLocale: (string) $validated['target_locale'],
                authorId: (int) $request->user()->id,
                useAi: $useAi,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $useAi
                ? __('cms_admin_ui.flash.translation_failed_ai')
                : __('cms_admin_ui.flash.translation_failed'));
        }

        $this->auditLogger->success(
            action: 'cms.page.translation.create',
            module: 'cms',
            subjectType: 'cms_page',
            subjectKey: (string) $translation->id,
            message: __('cms_admin_ui.flash.translation_created'),
            meta: [
                'source_page_id' => $page->id,
                'target_page_id' => $translation->id,
                'target_locale' => (string) $translation->locale,
                'use_ai' => $useAi,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.pages.edit', ['id' => $translation->id])
            ->with('status', $useAi
                ? __('cms_admin_ui.flash.translation_created_ai')
                : __('cms_admin_ui.flash.translation_created'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'delete_translations' => ['nullable', 'boolean'],
        ]);
        $page = CmsPage::query()->findOrFail($id);
        $deleteTranslations = (bool) ($validated['delete_translations'] ?? false);
        $deletedPageIds = [];
        $deactivatedMenuItemIds = [];

        DB::transaction(function () use ($page, $deleteTranslations, &$deletedPageIds, &$deactivatedMenuItemIds): void {
            $pages = $deleteTranslations && filled($page->translation_key)
                ? CmsPage::query()->where('translation_key', $page->translation_key)->get()
                : collect([$page]);

            $deletedPageIds = $pages->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

            if ($deleteTranslations) {
                $deactivatedMenuItemIds = CmsMenuItem::query()
                    ->whereIn('cms_page_id', $deletedPageIds)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all();

                if ($deactivatedMenuItemIds !== []) {
                    CmsMenuItem::query()
                        ->whereIn('id', $deactivatedMenuItemIds)
                        ->update(['is_active' => false]);
                }
            }

            if ($deleteTranslations) {
                CmsPage::query()
                    ->whereIn('id', $deletedPageIds)
                    ->update([
                        'translation_key' => null,
                        'translated_from_page_id' => null,
                    ]);
            }

            CmsPage::query()
                ->whereIn('parent_id', $deletedPageIds)
                ->update(['parent_id' => null]);

            $pages->each(fn (CmsPage $pageToDelete): ?bool => $pageToDelete->delete());
        });

        $this->auditLogger->success(
            action: $deleteTranslations ? 'cms.page.translation-set.delete' : 'cms.page.delete',
            module: 'cms',
            subjectType: 'cms_page',
            subjectKey: (string) $page->id,
            message: $deleteTranslations
                ? __('cms_admin_ui.flash.deleted.page_translation_set')
                : __('cms_admin_ui.flash.deleted.page'),
            meta: [
                'deleted_page_ids' => $deletedPageIds,
                'deactivated_menu_item_ids' => $deactivatedMenuItemIds,
                'delete_translations' => $deleteTranslations,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.pages.index')
            ->with('status', $deleteTranslations
                ? __('cms_admin_ui.flash.deleted.page_translation_set')
                : __('cms_admin_ui.flash.deleted.page'));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => 'draft', 'label' => __('cms_admin_ui.common.status.draft')],
            ['value' => 'published', 'label' => __('cms_admin_ui.common.status.published')],
            ['value' => 'archived', 'label' => __('cms_admin_ui.common.status.archived')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pagePayload(CmsPage $page, bool $canManageCodeBlocks): array
    {
        return [
            'id' => $page->id,
            'created_at' => $page->created_at?->toIso8601String(),
            'updated_at' => $page->updated_at?->toIso8601String(),
            'parent_id' => $page->parent_id,
            'detail_template_id' => $page->detail_template_id,
            'title' => $page->title,
            'slug' => $page->slug,
            'locale' => $page->locale,
            'translation_key' => $page->translation_key,
            'translated_from_page_id' => $page->translated_from_page_id,
            'status' => $page->status,
            'template' => $page->template,
            'short_description' => $page->short_description,
            'template_data' => $page->template_data ?? [],
            'seo_title' => $page->seo_title,
            'seo_description' => $page->seo_description,
            'canonical_url' => $page->canonical_url,
            'og_image_path' => $page->og_image_path,
            'noindex' => (bool) $page->noindex,
            'is_home' => (bool) $page->is_home,
            'is_searchable' => (bool) $page->is_searchable,
            'sort_order' => $page->sort_order,
            'published_at' => optional($page->published_at)->format('Y-m-d\TH:i'),
            'pdf_download_enabled' => (bool) ($page->settings['pdf_download_enabled'] ?? false),
            'structured_data_schema_type' => $page->settings['structured_data_schema_type'] ?? 'auto',
            'structured_data_extra' => $page->settings['structured_data_extra'] ?? '',
            'page_style' => $this->pageStylePayload(is_array($page->settings['page_style'] ?? null) ? $page->settings['page_style'] : []),
            'developer' => $canManageCodeBlocks
                ? $this->pageDeveloperPayload(is_array($page->settings['developer'] ?? null) ? $page->settings['developer'] : [])
                : $this->pageDeveloperPayload([]),
            'scroll_mode' => $page->settings['scroll_mode'] ?? 'inherit',
            'ai_translation_review' => $this->aiTranslationReviewPayload($page->settings ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function pageStylePayload(array $settings): array
    {
        $background = app(CmsResponsiveLayoutNormalizer::class)
            ->normalizeBackground(is_array($settings['background'] ?? null) ? $settings['background'] : null);
        $background['media_asset_id'] = $this->activeMediaAssetId($background['media_asset_id']);

        return [
            'foreground_color' => app(CmsResponsiveLayoutNormalizer::class)->normalizeHexColor($settings['foreground_color'] ?? null),
            'width_mode' => in_array($settings['width_mode'] ?? null, ['content', 'display'], true)
                ? $settings['width_mode']
                : 'content',
            'content_gap' => in_array($settings['content_gap'] ?? null, ['none', 'compact', 'normal', 'spacious'], true)
                ? $settings['content_gap']
                : 'normal',
            'css_class' => is_string($settings['css_class'] ?? null) ? trim($settings['css_class']) : '',
            'html_anchor' => is_string($settings['html_anchor'] ?? null) ? trim($settings['html_anchor']) : '',
            'background' => $background,
            'box' => app(CmsResponsiveLayoutNormalizer::class)
                ->normalizeBoxSpacing(is_array($settings['box'] ?? null) ? $settings['box'] : null),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{css_source: string, head_code: string, body_end_code: string}
     */
    private function pageDeveloperPayload(array $settings): array
    {
        return [
            'css_source' => is_string($settings['css_source'] ?? null) ? (string) $settings['css_source'] : '',
            'head_code' => is_string($settings['head_code'] ?? null) ? (string) $settings['head_code'] : '',
            'body_end_code' => is_string($settings['body_end_code'] ?? null) ? (string) $settings['body_end_code'] : '',
        ];
    }

    /**
     * @return array{content: array<int, array<string, mixed>>}
     */
    private function sectionsPayload(CmsPage $page): array
    {
        return [
            'content' => $page->sections
                ->where('zone', 'content')
                ->where('is_active', true)
                ->sortBy('sort_order')
                ->map(fn (CmsSection $section): array => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'is_active' => (bool) $section->is_active,
                    'visible_mobile' => (bool) $section->visible_mobile,
                    'visible_tablet' => (bool) $section->visible_tablet,
                    'visible_desktop' => (bool) $section->visible_desktop,
                    'settings' => $this->sectionSettingsPayload($section->settings ?? []),
                    'placements' => $section->placements
                        ->where('is_active', true)
                        ->sortBy('sort_order')
                        ->map(fn (CmsBlockPlacement $placement): array => $this->placementPayload($placement))
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{layout_type: string, width_mode: string, spacing: string, scroll_behavior: string, background: array<string, mixed>, box: array<string, mixed>}
     */
    private function sectionSettingsPayload(array $settings): array
    {
        $background = app(CmsResponsiveLayoutNormalizer::class)
            ->normalizeBackground(is_array($settings['background'] ?? null) ? $settings['background'] : null);
        $background['media_asset_id'] = $this->activeMediaAssetId($background['media_asset_id']);

        return [
            'html_anchor' => is_string($settings['html_anchor'] ?? null) ? trim($settings['html_anchor']) : null,
            'layout_type' => in_array($settings['layout_type'] ?? null, ['standard', 'hero', 'two_columns', 'grid'], true)
                ? $settings['layout_type']
                : 'standard',
            'width_mode' => in_array($settings['width_mode'] ?? null, ['content', 'display'], true)
                ? $settings['width_mode']
                : 'content',
            'spacing' => in_array($settings['spacing'] ?? null, ['compact', 'normal', 'spacious'], true)
                ? $settings['spacing']
                : 'none',
            'scroll_behavior' => in_array($settings['scroll_behavior'] ?? null, ['normal', 'sticky', 'auto_hide'], true)
                ? $settings['scroll_behavior']
                : 'normal',
            'background' => $background,
            'box' => app(CmsResponsiveLayoutNormalizer::class)
                ->normalizeBoxSpacing(is_array($settings['box'] ?? null) ? $settings['box'] : null),
        ];
    }

    private function activeMediaAssetId(mixed $mediaAssetId): ?int
    {
        $mediaAssetId = (int) $mediaAssetId;

        if ($mediaAssetId <= 0) {
            return null;
        }

        return CmsMediaAsset::query()->whereKey($mediaAssetId)->exists()
            ? $mediaAssetId
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function placementPayload(CmsBlockPlacement $placement): array
    {
        return [
            'id' => $placement->id,
            'is_active' => (bool) $placement->is_active,
            'visible_mobile' => (bool) $placement->visible_mobile,
            'visible_tablet' => (bool) $placement->visible_tablet,
            'visible_desktop' => (bool) $placement->visible_desktop,
            'mobile_span' => (int) $placement->mobile_span,
            'tablet_span' => (int) $placement->tablet_span,
            'desktop_span' => (int) $placement->desktop_span,
            'layout_config' => $placement->layout_config ?? [],
            'style_config' => $placement->style_config ?? [],
            'published_style_revision_id' => $placement->published_style_revision_id,
            'published_style_revision' => $placement->publishedStyleRevision ? [
                'id' => (int) $placement->publishedStyleRevision->id,
                'revision_number' => (int) $placement->publishedStyleRevision->revision_number,
                'css_source' => (string) $placement->publishedStyleRevision->css_source,
                'published_at' => $placement->publishedStyleRevision->published_at?->toIso8601String(),
            ] : null,
            'style_revisions' => $placement->styleRevisions
                ->map(fn ($revision): array => [
                    'id' => (int) $revision->id,
                    'revision_number' => (int) $revision->revision_number,
                    'status' => (string) $revision->status,
                    'title' => (string) $revision->title,
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
                ->all(),
            'height_mode' => $placement->height_mode,
            'height_value' => $placement->height_value,
            'cache_strategy' => $placement->cache_strategy,
            'settings' => [
                'html_anchor' => is_string($placement->settings['html_anchor'] ?? null)
                    ? trim($placement->settings['html_anchor'])
                    : null,
                'content_key' => is_string($placement->settings['content_key'] ?? null)
                    ? trim($placement->settings['content_key'])
                    : null,
                'editor_label' => is_string($placement->settings['editor_label'] ?? null)
                    ? trim($placement->settings['editor_label'])
                    : null,
                'page_editable' => (bool) ($placement->settings['page_editable'] ?? filled($placement->settings['content_key'] ?? null)),
                'page_editable_fields' => is_array($placement->settings['page_editable_fields'] ?? null)
                    ? array_values($placement->settings['page_editable_fields'])
                    : [],
                'page_editable_meta' => is_array($placement->settings['page_editable_meta'] ?? null)
                    ? array_values($placement->settings['page_editable_meta'])
                    : [],
                'alignment' => in_array($placement->settings['alignment'] ?? null, ['left', 'center', 'right'], true)
                    ? $placement->settings['alignment']
                    : null,
                'content_alignment' => in_array($placement->settings['content_alignment'] ?? null, ['left', 'center', 'right'], true)
                    ? $placement->settings['content_alignment']
                    : null,
            ],
            'block' => $this->blockPayload($placement->block),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blockPayload(CmsBlock $block): array
    {
        $content = $block->content ?? [];

        if (array_key_exists('media_asset_id', $content)) {
            $content['media_asset_id'] = $this->activeMediaAssetId($content['media_asset_id']);
        }

        if (array_key_exists('media_asset_ids', $content)) {
            $content['media_asset_ids'] = $this->activeMediaAssetIds($content['media_asset_ids']);
        }

        return array_merge(
            [
                'id' => $block->id,
                'cms_placeable_block_id' => $block->cms_placeable_block_id,
                'placeable_block_revision_id' => $block->placeable_block_revision_id,
                'name' => $block->name,
                'cache_strategy' => $block->cache_strategy,
            ],
            $content,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function placeableBlockOptions(): array
    {
        return CmsPlaceableBlock::query()
            ->with('latestPublishedRevision')
            ->where('status', 'published')
            ->orderBy('name')
            ->get()
            ->filter(fn (CmsPlaceableBlock $block): bool => $block->latestPublishedRevision !== null)
            ->map(fn (CmsPlaceableBlock $block): array => [
                'id' => (int) $block->id,
                'key' => (string) $block->key,
                'name' => (string) $block->name,
                'description' => $block->description,
                'category' => (string) ($block->category ?: 'content'),
                'source' => (string) ($block->source ?: 'user'),
                'status' => (string) $block->status,
                'allowed_zones' => $block->allowed_zones ?? [],
                'rendering_mode' => (string) $block->rendering_mode,
                'renderer_key' => (string) $block->renderer_key,
                'requires_permission' => $block->requires_permission,
                'schema' => $block->schema ?? [],
                'defaults' => $block->defaults ?? [],
                'capabilities' => $block->capabilities ?? [],
                'admin_component_key' => $block->admin_component_key,
                'package_key' => $block->package_key,
                'is_locked' => (bool) $block->is_locked,
                'revision_id' => (int) $block->latestPublishedRevision->id,
                'revision_number' => (int) $block->latestPublishedRevision->revision_number,
                'published_at' => $block->latestPublishedRevision->published_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function canManageCodeBlocks(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user?->is_platform_admin || $user?->canAccessRoute('admin.cms.layouts.code-blocks.manage'));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{is_pending: bool}
     */
    private function aiTranslationReviewPayload(array $settings): array
    {
        return [
            'is_pending' => ($settings['translation_source'] ?? null) === 'ai'
                && ($settings['translation_review_status'] ?? null) === 'pending',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function translationPayload(CmsPage $page): array
    {
        if (blank($page->translation_key)) {
            return [];
        }

        return CmsPage::query()
            ->where('translation_key', $page->translation_key)
            ->orderBy('locale')
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'locale', 'status', 'translated_from_page_id', 'settings', 'updated_at'])
            ->map(fn (CmsPage $translation): array => [
                'id' => $translation->id,
                'title' => $translation->title,
                'slug' => $translation->slug,
                'locale' => $translation->locale,
                'status' => $translation->status,
                'translated_from_page_id' => $translation->translated_from_page_id,
                'structured_data_extra_filled' => filled($translation->settings['structured_data_extra'] ?? null),
                'edit_url' => route('admin.cms.pages.edit', ['id' => $translation->id]),
                'updated_at' => $translation->updated_at?->toIso8601String(),
                'is_current' => $translation->is($page),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{locale: string, name: string, native_name: string}>
     */
    private function missingLanguagePayload(CmsPage $page): array
    {
        $existingLocales = blank($page->translation_key)
            ? collect([(string) $page->locale])
            : CmsPage::query()
                ->where('translation_key', $page->translation_key)
                ->pluck('locale');

        return collect($this->languageSettings->languages(true))
            ->reject(fn (array $language): bool => $existingLocales->contains($language['locale']))
            ->map(fn (array $language): array => [
                'locale' => (string) $language['locale'],
                'name' => (string) $language['name'],
                'native_name' => (string) $language['native_name'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function pageData(array $validated, CmsPage $page, bool $canManageCodeBlocks): array
    {
        return array_merge(
            Arr::only($validated, [
                'parent_id',
                'detail_template_id',
                'title',
                'slug',
                'locale',
                'status',
                'template',
                'short_description',
                'seo_title',
                'seo_description',
                'canonical_url',
                'og_image_path',
                'sort_order',
                'published_at',
            ]),
            [
                'content_blocks' => [],
                'template_data' => $this->templateData($validated),
                'noindex' => (bool) ($validated['noindex'] ?? false),
                'is_home' => (bool) ($validated['is_home'] ?? false),
                'is_searchable' => (bool) ($validated['is_searchable'] ?? false),
                'settings' => array_merge(
                    $this->settingsData($validated, $page, $canManageCodeBlocks),
                    $this->translationReviewSettings($page->settings ?? []),
                ),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function templateData(array $validated): array
    {
        $template = CmsTemplate::query()->find((int) ($validated['detail_template_id'] ?? 0));

        if (! $template instanceof CmsTemplate) {
            return [];
        }

        return app(CmsTemplateBlockDataContractBuilder::class)->cleanTemplateData(
            $template,
            is_array($validated['template_data'] ?? null) ? $validated['template_data'] : [],
        );
    }

    /**
     * @return array<int, array{id: int, name: string, locale: string, label: string}>
     */
    private function templateOptions(): array
    {
        return CmsTemplate::query()
            ->with([
                'sections.placements.block.placeableBlockRevision',
                'sections.placements.block.placeableBlock.latestPublishedRevision',
                'sections.placements.childPlacements.block.placeableBlockRevision',
                'sections.placements.childPlacements.block.placeableBlock.latestPublishedRevision',
            ])
            ->where(function ($query): void {
                $query
                    ->where(function ($query): void {
                        $query
                            ->where('template_class', 'page')
                            ->where('template_key', 'page.detail');
                    })
                    ->orWhere(function ($query): void {
                        $query
                            ->where('template_class', 'system')
                            ->where('template_key', 'like', 'system.account.%');
                    });
            })
            ->where('is_active', true)
            ->orderBy('locale')
            ->orderBy('name')
            ->get(['id', 'name', 'locale', 'template_class', 'template_key', 'data_contract'])
            ->map(fn (CmsTemplate $template): array => [
                'id' => (int) $template->id,
                'name' => (string) $template->name,
                'locale' => (string) $template->locale,
                'label' => sprintf('%s (%s)', $template->name, $template->locale),
                'data_contract' => app(CmsTemplateDataContract::class)->normalize($template->data_contract, (string) $template->template_key),
                'block_data_contract' => app(CmsTemplateBlockDataContractBuilder::class)->handle($template),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function translationReviewSettings(array $settings): array
    {
        return array_filter(
            Arr::only($settings, ['translation_source', 'translation_review_status']),
            fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function settingsData(array $validated, CmsPage $page, bool $canManageCodeBlocks): array
    {
        $settings = array_filter([
            'scroll_mode' => $validated['scroll_mode'] ?? 'inherit',
            'pdf_download_enabled' => (bool) ($validated['pdf_download_enabled'] ?? false),
            'structured_data_schema_type' => $validated['structured_data_schema_type'] ?? 'auto',
            'structured_data_extra' => $validated['structured_data_extra'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $settings['page_style'] = $this->pageStyleData($validated['page_style'] ?? [], $page, $validated);

        if ($canManageCodeBlocks) {
            $settings['developer'] = $this->pageDeveloperData($validated['developer'] ?? []);
        } elseif (is_array($page->settings['developer'] ?? null)) {
            $settings['developer'] = $this->pageDeveloperPayload($page->settings['developer']);
        }

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $style
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function pageStyleData(array $style, CmsPage $page, array $validated): array
    {
        $normalizer = app(CmsResponsiveLayoutNormalizer::class);
        $background = $normalizer->normalizeBackground(is_array($style['background'] ?? null) ? $style['background'] : null);
        $background['media_asset_id'] = $this->activeMediaAssetId($background['media_asset_id']);

        $styleData = [
            'foreground_color' => $normalizer->normalizeHexColor($style['foreground_color'] ?? null),
            'width_mode' => in_array($style['width_mode'] ?? null, ['content', 'display'], true)
                ? $style['width_mode']
                : 'content',
            'content_gap' => in_array($style['content_gap'] ?? null, ['none', 'compact', 'normal', 'spacious'], true)
                ? $style['content_gap']
                : 'normal',
            'css_class' => is_string($style['css_class'] ?? null) ? trim($style['css_class']) : null,
            'background' => $background,
            'box' => $normalizer->normalizeBoxSpacing(is_array($style['box'] ?? null) ? $style['box'] : null),
        ];

        $styleData = app(GenerateCmsHtmlAnchorAction::class)->handle(
            $page,
            array_merge($styleData, ['html_anchor' => $style['html_anchor'] ?? null]),
            ['page', $validated['locale'] ?? null, $validated['slug'] ?? null, $validated['title'] ?? null],
        );

        return array_filter($styleData, fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $developer
     * @return array{css_source: string, head_code: string, body_end_code: string}
     */
    private function pageDeveloperData(array $developer): array
    {
        return [
            'css_source' => is_string($developer['css_source'] ?? null) ? (string) $developer['css_source'] : '',
            'head_code' => is_string($developer['head_code'] ?? null) ? (string) $developer['head_code'] : '',
            'body_end_code' => is_string($developer['body_end_code'] ?? null) ? (string) $developer['body_end_code'] : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredDataPayload(?CmsPage $page): array
    {
        $site = [
            'name' => config('app.name', 'RwSoft'),
            'global_noindex' => false,
        ];

        $automatic = $page instanceof CmsPage
            ? $this->structuredDataBuilder->encode($this->structuredDataBuilder->forPage($page, $site))
            : null;

        return [
            'automatic' => $automatic ?: '{}',
            'final' => $automatic ?: '{}',
            'placeholders' => $this->structuredDataBuilder->placeholders('cms.page.json_ld'),
            'schemaTypeOptions' => [
                ['value' => 'auto', 'label' => __('cms_admin_ui.structured_data.auto')],
                ['value' => 'WebPage', 'label' => 'WebPage'],
                ['value' => 'AboutPage', 'label' => 'AboutPage'],
                ['value' => 'ContactPage', 'label' => 'ContactPage'],
                ['value' => 'FAQPage', 'label' => 'FAQPage'],
                ['value' => 'Service', 'label' => 'Service'],
                ['value' => 'None', 'label' => __('cms_admin_ui.structured_data.none')],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function contentBlocksData(array $blocks): array
    {
        $blockRegistry = app(CmsBlockRegistry::class);
        $placeableBlocks = $this->placeableBlocksForContent($blocks);

        return collect($blocks)
            ->filter(fn ($block): bool => is_array($block))
            ->map(function (array $block) use ($placeableBlocks, $blockRegistry): array {
                $placeableBlock = $placeableBlocks[(int) ($block['cms_placeable_block_id'] ?? 0)] ?? null;

                if (! $placeableBlock instanceof CmsPlaceableBlock) {
                    return [];
                }

                $rendererKey = (string) $placeableBlock->renderer_key;

                $data = match ($rendererKey) {
                    'breadcrumb' => [
                        'show_current' => (bool) ($block['show_current'] ?? true),
                        'compact' => (bool) ($block['compact'] ?? false),
                    ],
                    'list_rows', 'list_grid' => [
                        'title' => $block['title'] ?? null,
                        'source_type' => $block['source_type'] ?? 'category',
                        'category_source' => $block['category_source'] ?? 'all',
                        'category_id' => $block['category_id'] ?? null,
                        'tag_source' => $block['tag_source'] ?? 'all',
                        'tag_id' => $block['tag_id'] ?? null,
                        'show_only_subcategories' => (bool) ($block['show_only_subcategories'] ?? false),
                        'limit' => min(max((int) ($block['limit'] ?? 24), 1), 100),
                        'sort_field' => $block['sort_field'] ?? 'published_at',
                        'sort_direction' => $block['sort_direction'] ?? 'desc',
                        'show_search' => (bool) ($block['show_search'] ?? false),
                        'show_excerpt' => (bool) ($block['show_excerpt'] ?? true),
                        'show_image' => (bool) ($block['show_image'] ?? true),
                        'show_date' => (bool) ($block['show_date'] ?? true),
                        'show_categories' => (bool) ($block['show_categories'] ?? true),
                        'empty_text' => $block['empty_text'] ?? null,
                    ],
                    'quote' => [
                        'text' => $block['text'] ?? null,
                        'source' => $block['source'] ?? null,
                    ],
                    'image' => [
                        'media_asset_id' => $block['media_asset_id'] ?? null,
                        'caption' => $block['caption'] ?? null,
                    ],
                    'stats' => [
                        'value' => $block['value'] ?? null,
                        'suffix' => $block['suffix'] ?? null,
                        'label' => $block['label'] ?? null,
                    ],
                    'video' => [
                        'title' => $block['title'] ?? null,
                        'video_url' => $block['video_url'] ?? null,
                    ],
                    'logo_strip' => [
                        'title' => $block['title'] ?? null,
                        'media_asset_ids' => $this->mediaAssetIds($block['media_asset_ids'] ?? []),
                    ],
                    'button' => [
                        'label' => $block['label'] ?? null,
                        'url' => $block['url'] ?? null,
                    ],
                    'form' => [
                        'form_translation_key' => $block['form_translation_key'] ?? null,
                    ],
                    default => $this->registryBlockContent($rendererKey, $block, $blockRegistry),
                };

                $data['cms_placeable_block_id'] = (int) $placeableBlock->id;
                $data['placeable_block_revision_id'] = $block['placeable_block_revision_id'] ?? $placeableBlock->latestPublishedRevision?->id;
                $data['width_mode'] = ($block['width_mode'] ?? 'content') === 'display' ? 'display' : 'content';

                return array_filter(
                    $data,
                    fn ($value, string $key): bool => $value !== null && $value !== '',
                    ARRAY_FILTER_USE_BOTH
                );
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function registryBlockContent(string $type, array $block, CmsBlockRegistry $blockRegistry): array
    {
        $content = [];

        foreach ($blockRegistry->fieldsFor($type) as $field) {
            $content[$field] = $field === 'media_asset_ids'
                ? $this->mediaAssetIds($block[$field] ?? [])
                : ($blockRegistry->repeaterFieldNamesFor($type, $field) !== []
                    ? $blockRegistry->normalizeRepeaterItems($type, $field, $block[$field] ?? [])
                    : ($block[$field] ?? null));
        }

        if ($type === 'address_block') {
            $content['_contact_defaults_applied'] = (bool) ($block['_contact_defaults_applied'] ?? false);
        }

        return $content;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, CmsPlaceableBlock>
     */
    private function placeableBlocksForContent(array $blocks): array
    {
        $ids = collect($blocks)
            ->filter(fn ($block): bool => is_array($block))
            ->map(fn (array $block): int => (int) ($block['cms_placeable_block_id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return CmsPlaceableBlock::query()
            ->with('latestPublishedRevision')
            ->whereIn('id', $ids)
            ->where('status', 'published')
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function mediaAssetIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function activeMediaAssetIds(mixed $value): array
    {
        $mediaAssetIds = $this->mediaAssetIds($value);

        if ($mediaAssetIds === []) {
            return [];
        }

        return CmsMediaAsset::query()
            ->whereKey($mediaAssetIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function excludedParentIds(CmsPage $page): array
    {
        $pages = CmsPage::query()->get(['id', 'parent_id']);
        $excludedIds = [$page->id];
        $frontier = [$page->id];

        while ($frontier !== []) {
            $children = $pages
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $frontier = array_values(array_diff($children, $excludedIds));
            $excludedIds = array_values(array_unique(array_merge($excludedIds, $frontier)));
        }

        return $excludedIds;
    }

    /**
     * @return array<int, array{id: int, title: string, slug: string, locale: string, parent_id: int|null}>
     */
    private function categoryOptions(): array
    {
        return CmsCategory::query()
            ->where('type', 'post')
            ->where('is_active', true)
            ->orderBy('locale')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'parent_id', 'title', 'slug', 'locale'])
            ->map(fn (CmsCategory $category): array => [
                'id' => (int) $category->id,
                'parent_id' => $category->parent_id ? (int) $category->parent_id : null,
                'title' => (string) $category->title,
                'slug' => (string) $category->slug,
                'locale' => (string) $category->locale,
            ])
            ->values()
            ->all();
    }

    private function categoryLandingPageIdsQuery(): Builder
    {
        return CmsCategory::query()
            ->whereNotNull('landing_page_id')
            ->select('landing_page_id')
            ->toBase();
    }

    private function tagLandingPageIdsQuery(): Builder
    {
        return CmsTag::query()
            ->whereNotNull('landing_page_id')
            ->select('landing_page_id')
            ->toBase();
    }

    /**
     * @return array<int, array{id: int, title: string, slug: string, locale: string}>
     */
    private function tagOptions(): array
    {
        return CmsTag::query()
            ->where('is_active', true)
            ->orderBy('locale')
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'locale'])
            ->map(fn (CmsTag $tag): array => [
                'id' => (int) $tag->id,
                'title' => (string) $tag->title,
                'slug' => (string) $tag->slug,
                'locale' => (string) $tag->locale,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, title: string, placements: array<int, string>, is_active: bool}>
     */
    private function menuOptions(): array
    {
        return CmsMenu::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'placements', 'is_active'])
            ->map(fn (CmsMenu $menu): array => [
                'id' => (int) $menu->id,
                'title' => (string) $menu->title,
                'placements' => array_values((array) ($menu->placements ?? [])),
                'is_active' => (bool) $menu->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $contentBlocks
     */
    private function syncContentBlocksToSitebuilder(CmsPage $page, array $contentBlocks): void
    {
        $sectionImportKey = $this->contentBlockImportKey($page, 'section');

        if ($contentBlocks === []) {
            CmsSection::query()
                ->where('import_key', $sectionImportKey)
                ->update(['is_active' => false]);

            return;
        }

        $section = CmsSection::query()->updateOrCreate(
            ['import_key' => $sectionImportKey],
            [
                'owner_type' => CmsPage::class,
                'owner_id' => $page->id,
                'zone' => 'content',
                'name' => null,
                'sort_order' => 0,
                'is_active' => true,
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => true,
                'settings' => ['source' => 'content_blocks'],
            ],
        );

        $activePlacementImportKeys = [];

        foreach ($contentBlocks as $index => $contentBlock) {
            $blockType = $this->normalizeContentBlockType($contentBlock['type'] ?? null);
            $blockImportKey = $this->contentBlockImportKey($page, 'block', $index);
            $placementImportKey = $this->contentBlockImportKey($page, 'placement', $index);

            $block = CmsBlock::query()->updateOrCreate(
                ['import_key' => $blockImportKey],
                [
                    'type' => $blockType,
                    'name' => $this->nullableContentBlockString($contentBlock['title'] ?? $contentBlock['heading'] ?? null),
                    'content' => $this->contentBlockPayload($contentBlock),
                    'settings' => ['source' => 'content_blocks'],
                    'is_shared' => false,
                    'is_dynamic' => false,
                    'cache_strategy' => 'inherit',
                    'created_by' => $page->author_id,
                ],
            );

            CmsBlockPlacement::query()->updateOrCreate(
                ['import_key' => $placementImportKey],
                [
                    'cms_section_id' => $section->id,
                    'cms_block_id' => $block->id,
                    'sort_order' => $index,
                    'is_active' => true,
                    'visible_mobile' => true,
                    'visible_tablet' => true,
                    'visible_desktop' => true,
                    'mobile_span' => 12,
                    'tablet_span' => 12,
                    'desktop_span' => 12,
                    'height_mode' => 'auto',
                    'height_value' => null,
                    'cache_strategy' => 'inherit',
                    'settings' => ['source' => 'content_blocks'],
                ],
            );

            $activePlacementImportKeys[] = $placementImportKey;
        }

        CmsBlockPlacement::query()
            ->where('cms_section_id', $section->id)
            ->where('import_key', 'like', $this->contentBlockImportKey($page, 'placement').'%')
            ->whereNotIn('import_key', $activePlacementImportKeys)
            ->update(['is_active' => false]);
    }

    private function contentBlockImportKey(CmsPage $page, string $type, ?int $index = null): string
    {
        return collect(['legacy-content-blocks', 'page', $page->id, $type, $index])
            ->filter(fn ($part): bool => $part !== null)
            ->implode('-');
    }

    private function normalizeContentBlockType(mixed $type): string
    {
        return in_array($type, ['address_block', 'breadcrumb', 'text', 'quote', 'image', 'button', 'form', 'list_rows', 'list_grid'], true)
            ? $type
            : 'text';
    }

    /**
     * @param  array<string, mixed>  $contentBlock
     * @return array<string, mixed>
     */
    private function contentBlockPayload(array $contentBlock): array
    {
        unset($contentBlock['type'], $contentBlock['width_mode']);

        return $contentBlock;
    }

    private function nullableContentBlockString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
