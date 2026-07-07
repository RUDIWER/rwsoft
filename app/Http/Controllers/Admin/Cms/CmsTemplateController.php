<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\CreateCmsTemplateTranslationAction;
use App\Actions\Admin\Cms\GenerateCmsHtmlAnchorAction;
use App\Actions\Admin\Cms\Revisions\BuildCmsTemplateRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\CmsRevisionPayloadAction;
use App\Actions\Admin\Cms\Revisions\CreateCmsRevisionAction;
use App\Actions\Admin\Cms\SaveCmsSectionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\StoreCmsTemplateRequest;
use App\Http\Requests\Admin\Cms\StoreCmsTemplateTranslationRequest;
use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsColorPaletteItem;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use App\Support\Audit\AuditLogger;
use App\Support\Cms\CmsBlockRegistry;
use App\Support\Cms\CmsDownloadLibraryPayload;
use App\Support\Cms\CmsMediaLibraryPayload;
use App\Support\Cms\CmsResponsiveLayoutNormalizer;
use App\Support\Cms\CmsStyleTokenOptions;
use App\Support\Cms\CmsTemplateDataContract;
use App\Support\Cms\CmsTemplateFieldRegistry;
use App\Support\Cms\CmsTemplateRegistry;
use App\Support\PublicSite\CmsBlockPayloadBuilder;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use App\Support\PublicSite\CmsNavigationBuilder;
use App\Support\PublicSite\CmsPageCompositionBuilder;
use App\Support\PublicSite\CmsPublicTextResolver;
use App\Support\PublicSite\CmsPublicUrlBuilder;
use App\Support\PublicSite\CmsTemplateCompositionBuilder;
use App\Support\PublicSite\PublicMediaUrl;
use App\Support\PublicSite\PublicStorageUrl;
use App\Support\PublicSite\PublicViewResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CmsTemplateController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsLocalePermission $localePermission,
        private readonly CmsTemplateRegistry $templateRegistry,
        private readonly CmsTemplateFieldRegistry $fieldRegistry,
        private readonly CmsBlockPayloadBuilder $blockPayloadBuilder,
        private readonly CmsPageCompositionBuilder $pageCompositionBuilder,
        private readonly CmsTemplateCompositionBuilder $templateCompositionBuilder,
        private readonly CmsNavigationBuilder $navigationBuilder,
        private readonly CmsPublicTextResolver $publicTextResolver,
        private readonly CmsPublicUrlBuilder $urlBuilder,
        private readonly PublicMediaUrl $mediaUrl,
        private readonly PublicStorageUrl $storageUrl,
        private readonly PublicViewResolver $viewResolver,
        private readonly CmsMediaLibraryPayload $mediaLibraryPayload,
        private readonly CmsDownloadLibraryPayload $downloadLibraryPayload,
        private readonly CmsStyleTokenOptions $styleTokenOptions,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Cms/Templates/Index', [
            'templates' => CmsTemplate::query()
                ->with('layout:id,name,locale')
                ->withCount(['sections'])
                ->orderBy('template_class')
                ->orderBy('template_key')
                ->orderBy('locale')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(fn (CmsTemplate $template): array => $this->templatePayload($template))
                ->values(),
            'templateOptions' => $this->templateRegistry->editorOptions(),
            'layoutOptions' => $this->layoutOptions(),
        ]);
    }

    public function create(): Response
    {
        return $this->edit(0);
    }

    public function edit(int $id): Response
    {
        $request = request();
        $template = $id > 0
            ? CmsTemplate::query()
                ->with([
                    'layout:id,name,locale',
                    'sections.placements.block.placeableBlock.latestPublishedRevision',
                    'sections.placements.publishedStyleRevision',
                    'sections.placements.styleRevisions.author:id,name,email',
                    'sections.placements.childPlacements.block.placeableBlock.latestPublishedRevision',
                    'sections.placements.childPlacements.publishedStyleRevision',
                    'sections.placements.childPlacements.styleRevisions.author:id,name,email',
                ])
                ->withCount(['sections'])
                ->findOrFail($id)
            : null;

        $templateClass = (string) ($template?->template_class ?? request()->query('template_class', 'page'));
        $templateKey = (string) ($template?->template_key ?? request()->query('template_key', 'page.detail'));

        if (! $this->templateRegistry->isValidTemplateKey($templateKey, $templateClass)) {
            $templateClass = 'page';
            $templateKey = 'page.detail';
        }

        return Inertia::render('Admin/Cms/Templates/Edit', [
            'templateItem' => $template ? $this->templatePayload($template) : null,
            'templateOptions' => $this->templateRegistry->editorOptions(),
            'layoutOptions' => $this->layoutOptions(),
            'fieldDefinitions' => $this->fieldRegistry->fieldsFor($templateKey),
            'fieldDefinitionsByContext' => $this->fieldRegistry->all(),
            'availableSystemFieldsByContext' => collect($this->fieldRegistry->all())
                ->map(fn (array $fields, string $key): array => app(CmsTemplateDataContract::class)->availableSystemFields($key))
                ->all(),
            'templateFieldTypes' => CmsTemplateDataContract::TEMPLATE_FIELD_TYPES,
            'placeableBlocks' => $this->placeableBlockOptions(),
            'previewOptions' => $template instanceof CmsTemplate ? $this->previewOptions($template) : [],
            'revisions' => $template instanceof CmsTemplate ? app(CmsRevisionPayloadAction::class)->handle($template) : [],
            'templateImpactCount' => $template instanceof CmsTemplate ? $this->templateUsageCount($template) : 0,
            'translations' => $template instanceof CmsTemplate ? $this->translationPayload($template) : [],
            'missingLanguages' => $template instanceof CmsTemplate ? $this->missingLanguagePayload($template) : [],
            'activeLanguages' => $this->languageSettings->languages(true),
            'availableLocales' => $this->languageSettings->activeLocales(),
            'canManageCodeBlocks' => $this->canManageCodeBlocks($request),
            'colorPaletteItems' => CmsColorPaletteItem::activePayload(),
            'styleTokenOptions' => $this->styleTokenOptions->all(),
            'activeThemeFontFaceCss' => $this->styleTokenOptions->activeFontFaceCss(),
            'cacheStrategyOptions' => $this->cacheStrategyOptions(),
            'formOptions' => CmsForm::query()
                ->where('is_active', true)
                ->orderBy('locale')
                ->orderBy('title')
                ->get(['translation_key', 'title', 'locale']),
            'menuOptions' => $this->menuOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'tagOptions' => $this->tagOptions(),
            'contactSettings' => CmsSetting::contactPayload(),
            'mediaOptions' => $this->mediaLibraryPayload->assets(),
            'mediaFolders' => $this->mediaLibraryPayload->folders(),
            'downloadOptions' => $this->downloadLibraryPayload->assets(),
            'downloadFolders' => $this->downloadLibraryPayload->folders(),
        ]);
    }

    public function store(
        StoreCmsTemplateRequest $request,
        int $id,
        SaveCmsSectionsAction $saveSections,
        GenerateCmsHtmlAnchorAction $htmlAnchorAction,
        BuildCmsTemplateRevisionSnapshotAction $buildRevisionSnapshot,
        CreateCmsRevisionAction $createRevision,
    ): RedirectResponse {
        $validated = $request->validated();
        $template = $id > 0 ? CmsTemplate::query()->findOrFail($id) : new CmsTemplate;
        $isCreate = ! $template->exists;

        DB::transaction(function () use ($template, $validated, $request, $saveSections, $htmlAnchorAction, $buildRevisionSnapshot, $createRevision): void {
            $isDefault = (bool) ($validated['is_default'] ?? false);
            $settings = $htmlAnchorAction->handle(
                $template,
                $validated['settings'] ?? [],
                [$validated['name'], $validated['template_key'], $validated['locale'], 'template'],
            );

            if ($isDefault) {
                CmsTemplate::query()
                    ->where('template_key', $validated['template_key'])
                    ->where('locale', $validated['locale'])
                    ->when($template->exists, fn ($query) => $query->where('id', '!=', $template->id))
                    ->update(['is_default' => false]);
            }

            $template->fill([
                'name' => $validated['name'],
                'locale' => $validated['locale'],
                'layout_id' => $validated['layout_id'],
                'template_class' => $validated['template_class'],
                'template_key' => $validated['template_key'],
                'module_key' => $validated['template_class'] === 'module' ? Str::before((string) $validated['template_key'], '.') : null,
                'is_default' => $isDefault,
                'is_active' => $isDefault ? true : (bool) ($validated['is_active'] ?? false),
                'cache_strategy' => $validated['cache_strategy'],
                'settings' => $settings,
                'data_contract' => app(CmsTemplateDataContract::class)->normalize(
                    is_array($validated['data_contract'] ?? null) ? $validated['data_contract'] : [],
                    (string) $validated['template_key'],
                ),
            ])->save();

            if (blank($template->translation_key)) {
                $template->forceFill(['translation_key' => (string) Str::ulid()])->save();
            }

            $saveSections->handle($template, $validated['sections'] ?? [], ['content']);

            $createRevision->handle(
                $template,
                'full',
                $buildRevisionSnapshot->handle($template),
                $request->user()?->id,
                __('cms_admin_ui.revisions.auto_revision_title'),
                metadata: [
                    'change_type' => $template->wasRecentlyCreated ? 'create' : 'update',
                    'template_usage_count' => $this->templateUsageCount($template),
                ],
            );
        });

        $this->auditLogger->success(
            action: $isCreate ? 'cms.template.create' : 'cms.template.update',
            module: 'cms',
            subjectType: 'cms_template',
            subjectKey: (string) $template->id,
            message: __('cms_admin_ui.flash.saved.template'),
            meta: [
                'name' => (string) $template->name,
                'template_class' => (string) $template->template_class,
                'template_key' => (string) $template->template_key,
                'locale' => (string) $template->locale,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.templates.edit', ['id' => $template->id])
            ->with('status', __('cms_admin_ui.flash.saved.template'));
    }

    public function storeTranslation(
        StoreCmsTemplateTranslationRequest $request,
        int $id,
        CreateCmsTemplateTranslationAction $createTranslation,
    ): RedirectResponse {
        $validated = $request->validated();
        $template = CmsTemplate::query()->with('layout')->findOrFail($id);
        $useAi = (bool) ($validated['use_ai'] ?? false);

        try {
            $translation = $createTranslation->handle(
                sourceTemplate: $template,
                targetLocale: (string) $validated['target_locale'],
                useAi: $useAi,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $useAi
                ? __('cms_admin_ui.flash.translation_failed_ai')
                : __('cms_admin_ui.flash.translation_failed'));
        }

        $this->auditLogger->success(
            action: 'cms.template.translation.create',
            module: 'cms',
            subjectType: 'cms_template',
            subjectKey: (string) $translation->id,
            message: $useAi
                ? __('cms_admin_ui.flash.template_translation_created_ai')
                : __('cms_admin_ui.flash.template_translation_created'),
            meta: [
                'source_template_id' => $template->id,
                'target_template_id' => $translation->id,
                'target_locale' => (string) $translation->locale,
                'use_ai' => $useAi,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.templates.edit', ['id' => $translation->id])
            ->with('status', $useAi
                ? __('cms_admin_ui.flash.template_translation_created_ai')
                : __('cms_admin_ui.flash.template_translation_created'));
    }

    public function preview(Request $request, int $id): View
    {
        $template = CmsTemplate::query()
            ->with(['layout', 'sections.placements.block.placeableBlock.latestPublishedRevision'])
            ->findOrFail($id);

        if (! $this->localePermission->canEditLocale($request->user(), (string) $template->locale)) {
            abort(403);
        }

        App::setLocale((string) $template->locale);

        $sampleId = $request->integer('sample_id') ?: null;
        $sampleId = $this->previewOptionExists($template, $sampleId) ? $sampleId : null;
        $preview = $this->previewData($template, $sampleId);
        $context = $preview['context'];
        $context['__template'] = [
            'template_class' => $template->template_class,
            'template_key' => $template->template_key,
            'locale' => $template->locale,
        ];
        $composition = $this->templateCompositionBuilder->handle(
            $template,
            $preview['contentItem'],
            $context,
            $preview['contentSlots'],
        );

        return view($this->viewResolver->template(), [
            'pageItem' => $composition['page'],
            'composition' => $composition,
            'site' => $this->sitePayload($request, (string) $template->locale),
            'navigation' => $this->navigationBuilder->handle((string) $template->locale),
            'translations' => [],
            'seo' => $this->previewSeo($template, $preview['contentItem']),
        ]);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $template = CmsTemplate::query()->findOrFail($id);

        if (! $this->localePermission->canEditLocale($request->user(), (string) $template->locale)) {
            abort(403);
        }

        if ($template->is_default) {
            return back()->with('error', __('cms_admin_ui.flash.template_default_delete_blocked'));
        }

        if ($this->templateUsageCount($template) > 0) {
            return back()->with('error', __('cms_admin_ui.flash.template_in_use_delete_blocked'));
        }

        $template->delete();

        $this->auditLogger->success(
            action: 'cms.template.delete',
            module: 'cms',
            subjectType: 'cms_template',
            subjectKey: (string) $template->id,
            message: __('cms_admin_ui.flash.deleted.template'),
            meta: ['name' => (string) $template->name],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.templates.index')
            ->with('status', __('cms_admin_ui.flash.deleted.template'));
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(CmsTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'locale' => $template->locale,
            'translation_key' => $template->translation_key,
            'translated_from_template_id' => $template->translated_from_template_id,
            'layout_id' => $template->layout_id,
            'layout_name' => $template->layout?->name,
            'template_class' => $template->template_class,
            'template_key' => $template->template_key,
            'module_key' => $template->module_key,
            'is_default' => (bool) $template->is_default,
            'is_active' => (bool) $template->is_active,
            'cache_strategy' => $template->cache_strategy,
            'settings' => $template->settings ?? [],
            'data_contract' => app(CmsTemplateDataContract::class)->normalize($template->data_contract, (string) $template->template_key),
            'sections_count' => (int) ($template->sections_count ?? 0),
            'usage_count' => $this->templateUsageCount($template),
            'created_at' => $template->created_at?->toDateTimeString(),
            'updated_at' => $template->updated_at?->toDateTimeString(),
            'sections' => $template->relationLoaded('sections')
                ? $this->sectionsPayload($template)
                : $this->emptySectionsPayload(),
        ];
    }

    /**
     * @return array{content: array<int, array<string, mixed>>}
     */
    private function sectionsPayload(CmsTemplate $template): array
    {
        return [
            'content' => $template->sections
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
     * @return array{content: array<int, array<string, mixed>>}
     */
    private function emptySectionsPayload(): array
    {
        return ['content' => []];
    }

    /**
     * @return array<int, array{id: int, name: string, locale: string, label: string, is_default: bool}>
     */
    private function layoutOptions(): array
    {
        return CmsLayout::query()
            ->active()
            ->orderBy('locale')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'locale', 'is_default'])
            ->map(fn (CmsLayout $layout): array => [
                'id' => (int) $layout->id,
                'name' => (string) $layout->name,
                'locale' => (string) $layout->locale,
                'label' => sprintf('%s (%s)', $layout->name, $layout->locale),
                'is_default' => (bool) $layout->is_default,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function translationPayload(CmsTemplate $template): array
    {
        if (blank($template->translation_key)) {
            return [];
        }

        return CmsTemplate::query()
            ->with(['layout:id,name'])
            ->withCount(['sections'])
            ->where('translation_key', $template->translation_key)
            ->orderBy('locale')
            ->orderBy('name')
            ->get(['id', 'name', 'locale', 'layout_id', 'is_active', 'is_default', 'translated_from_template_id', 'settings', 'updated_at'])
            ->map(fn (CmsTemplate $translation): array => [
                'id' => $translation->id,
                'name' => $translation->name,
                'locale' => $translation->locale,
                'layout_name' => $translation->layout?->name,
                'is_active' => (bool) $translation->is_active,
                'is_default' => (bool) $translation->is_default,
                'translated_from_template_id' => $translation->translated_from_template_id,
                'sections_count' => (int) ($translation->sections_count ?? 0),
                'updated_at' => $translation->updated_at?->toIso8601String(),
                'is_current' => $translation->is($template),
                'ai_translation_review' => $this->aiTranslationReviewPayload($translation->settings ?? []),
                'edit_url' => route('admin.cms.templates.edit', ['id' => $translation->id]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{locale: string, name: string, native_name: string}>
     */
    private function missingLanguagePayload(CmsTemplate $template): array
    {
        $existingLocales = blank($template->translation_key)
            ? collect([(string) $template->locale])
            : CmsTemplate::query()
                ->where('translation_key', $template->translation_key)
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
            'slots' => $this->childSlotsPayload($placement),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function childSlotsPayload(CmsBlockPlacement $placement): array
    {
        return $placement->childPlacements
            ->where('is_active', true)
            ->groupBy('slot_key')
            ->map(fn ($placements, string $slotKey): array => [
                'key' => $slotKey,
                'placements' => $placements
                    ->sortBy('sort_order')
                    ->values()
                    ->map(fn (CmsBlockPlacement $childPlacement): array => $this->placementPayload($childPlacement))
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function blockPayload(CmsBlock $block): array
    {
        return array_merge(
            [
                'id' => $block->id,
                'cms_placeable_block_id' => $block->cms_placeable_block_id,
                'placeable_block_revision_id' => $block->placeable_block_revision_id,
                'name' => $block->name,
                'cache_strategy' => $block->cache_strategy,
            ],
            $block->content ?? [],
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
     * @return array<int, array<string, mixed>>
     */
    private function blockDefinitions(string $templateKey): array
    {
        $fieldOptions = collect($this->fieldRegistry->fieldsFor($templateKey))
            ->map(fn (array $field): array => [
                'value' => $field['key'],
                'label_key' => $field['label_key'],
                'label' => $field['key'],
            ])
            ->values()
            ->all();

        return collect(app(CmsBlockRegistry::class)->editorDefinitions())
            ->filter(fn (array $definition): bool => ($definition['category'] ?? null) === 'content')
            ->map(function (array $definition) use ($fieldOptions): array {
                if (($definition['type'] ?? null) !== 'dynamic_field') {
                    return $definition;
                }

                $definition['editor_fields'] = collect($definition['editor_fields'] ?? [])
                    ->map(function (array $field) use ($fieldOptions): array {
                        if (($field['name'] ?? null) === 'field_key') {
                            $field['options'] = $fieldOptions;
                        }

                        return $field;
                    })
                    ->all();

                return $definition;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function cacheStrategyOptions(): array
    {
        return [
            ['value' => 'inherit', 'label' => __('cms_admin_ui.layouts.cache.inherit')],
            ['value' => 'none', 'label' => __('cms_admin_ui.layouts.cache.none')],
            ['value' => 'block', 'label' => __('cms_admin_ui.layouts.cache.block')],
            ['value' => 'layout', 'label' => __('cms_admin_ui.layouts.cache.layout')],
        ];
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
            'system' => CmsPage::query()->where('detail_template_id', $template->id)->count(),
            default => 0,
        };
    }

    /**
     * @return array{contentItem: array<string, mixed>, context: array<string, mixed>, contentSlots: array<string, mixed>}
     */
    private function previewData(CmsTemplate $template, ?int $sampleId): array
    {
        return match ($template->template_key) {
            'page.detail' => $this->pageDetailPreviewData($template, $sampleId),
            'blog.index' => $this->blogIndexPreviewData($template),
            'blog.detail' => $this->blogDetailPreviewData($template, $sampleId),
            'category.index' => $this->categoryIndexPreviewData($template),
            'category.archive' => $this->categoryArchivePreviewData($template, $sampleId),
            'category.detail' => $this->categoryDetailPreviewData($template, $sampleId),
            'tag.index' => $this->tagIndexPreviewData($template),
            'tag.archive' => $this->tagArchivePreviewData($template, $sampleId),
            'tag.detail' => $this->tagDetailPreviewData($template, $sampleId),
            default => $this->emptyPreviewData($template),
        };
    }

    /**
     * @return array{contentItem: array<string, mixed>, context: array<string, mixed>, contentSlots: array<string, mixed>}
     */
    private function pageDetailPreviewData(CmsTemplate $template, ?int $sampleId): array
    {
        $page = $this->previewPage((string) $template->locale, $sampleId);

        if ($page instanceof CmsPage) {
            $page->loadMissing(['sections.placements.block.placeableBlock.latestPublishedRevision']);
        }

        if ($page instanceof CmsPage) {
            $composition = $this->pageCompositionBuilder->handle($page);
            $contentItem = array_merge($composition['page'], [
                'template_class' => 'page',
                'template_key' => 'page.detail',
            ]);

            return [
                'contentItem' => $contentItem,
                'context' => [
                    'page' => array_merge($composition['page'], [
                        'content' => $composition['sections']['content'] ?? [],
                    ]),
                ],
                'contentSlots' => [
                    'content' => [
                        'sections' => $composition['sections']['content'] ?? [],
                    ],
                ],
            ];
        }

        $contentItem = $this->syntheticContentItem($template, public_text('page.preview_title', 'Voorbeeldpagina', (string) $template->locale));

        return [
            'contentItem' => $contentItem,
            'context' => [
                'page' => array_merge($contentItem, [
                    'content' => $this->syntheticContentBlocks($template),
                    'breadcrumbs' => [],
                ]),
            ],
            'contentSlots' => ['content' => $this->syntheticContentBlocks($template)],
        ];
    }

    private function previewPage(string $locale, ?int $sampleId): ?CmsPage
    {
        return $this->publishedPageQuery()
            ->where('locale', $locale)
            ->when($sampleId !== null, fn (Builder $query): Builder => $query->whereKey($sampleId))
            ->with(['sections.placements.block.placeableBlock.latestPublishedRevision'])
            ->orderByDesc('is_home')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->first();
    }

    /**
     * @return array{contentItem: array<string, mixed>, context: array<string, mixed>, contentSlots: array<string, mixed>}
     */
    private function blogIndexPreviewData(CmsTemplate $template): array
    {
        $locale = (string) $template->locale;
        $blogs = $this->latestPostPayloads($locale);

        return [
            'contentItem' => $this->syntheticContentItem($template, public_text('post_index.title', 'Blogs', $locale)),
            'context' => [
                'blog_index' => [
                    'title' => public_text('post_index.title', 'Blogs', $locale),
                    'lead' => public_text('post_index.lead', 'Laatste gepubliceerde blogs en updates.', $locale),
                ],
                'blogs' => $blogs !== [] ? $blogs : $this->syntheticPostPayloads($locale),
                'categories' => $this->activeCategoryPayloads($locale),
                'tags' => $this->activeTagPayloads($locale),
            ],
            'contentSlots' => [],
        ];
    }

    /**
     * @return array{contentItem: array<string, mixed>, context: array<string, mixed>, contentSlots: array<string, mixed>}
     */
    private function blogDetailPreviewData(CmsTemplate $template, ?int $sampleId): array
    {
        $post = $this->publishedPostQuery()
            ->where('locale', $template->locale)
            ->when($sampleId !== null, fn (Builder $query): Builder => $query->whereKey($sampleId))
            ->with(['author', 'featuredMedia', 'categories', 'tags'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();

        if ($post instanceof CmsPost) {
            $postItem = $this->postDetailPayload($post);
            $blocks = $this->blockPayloadBuilder->handle($post->content_blocks ?? [], post: $post);

            return [
                'contentItem' => $postItem,
                'context' => [
                    'blog' => array_merge($postItem, ['content' => $blocks]),
                ],
                'contentSlots' => ['content' => $blocks],
            ];
        }

        $contentItem = array_merge($this->syntheticContentItem($template, public_text('blog.preview_title', 'Voorbeeldblog', (string) $template->locale)), [
            'published_at' => now()->toDateString(),
            'author' => ['name' => __('cms_admin_ui.templates.preview.sample_author')],
            'featured_media' => null,
            'categories' => [],
            'tags' => [],
        ]);

        return [
            'contentItem' => $contentItem,
            'context' => [
                'blog' => array_merge($contentItem, ['content' => $this->syntheticContentBlocks($template)]),
            ],
            'contentSlots' => ['content' => $this->syntheticContentBlocks($template)],
        ];
    }

    /**
     * @return array{contentItem: array<string, mixed>, context: array<string, mixed>, contentSlots: array<string, mixed>}
     */
    private function categoryIndexPreviewData(CmsTemplate $template): array
    {
        $locale = (string) $template->locale;
        $categories = $this->activeCategoryPayloads($locale);

        return [
            'contentItem' => $this->syntheticContentItem($template, public_text('taxonomy.category_index_title', 'Categorieen', $locale)),
            'context' => [
                'category_index' => [
                    'title' => public_text('taxonomy.category_index_title', 'Categorieen', $locale),
                ],
                'categories' => $categories,
                'root_categories' => collect($categories)->whereNull('parent_id')->values()->all(),
                'category_count' => count($categories),
            ],
            'contentSlots' => [],
        ];
    }

    /**
     * @return array{contentItem: array<string, mixed>, context: array<string, mixed>, contentSlots: array<string, mixed>}
     */
    private function categoryArchivePreviewData(CmsTemplate $template, ?int $sampleId): array
    {
        $category = $this->previewCategory((string) $template->locale, $sampleId);

        if (! $category instanceof CmsCategory) {
            return $this->syntheticCategoryPreviewData($template, 'archive');
        }

        $children = CmsCategory::query()
            ->where('parent_id', $category->id)
            ->where('type', $category->type)
            ->where('locale', $category->locale)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'parent_id', 'landing_page_id', 'type', 'title', 'slug', 'locale', 'description', 'is_active']);
        $posts = $this->publishedPostQuery()
            ->with(['featuredMedia', 'categories'])
            ->where('locale', $category->locale)
            ->whereHas('categories', fn (Builder $query): Builder => $query->whereKey($category->id))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(24)
            ->get();

        return [
            'contentItem' => $this->categoryContentItem($category, 'archive'),
            'context' => [
                'category' => array_merge($this->categoryPayload($category), [
                    'parent' => $category->parent instanceof CmsCategory ? $this->categoryPayload($category->parent) : null,
                    'children' => $children->map(fn (CmsCategory $child): array => $this->categoryPayload($child))->values()->all(),
                    'blogs' => $posts->map(fn (CmsPost $post): array => $this->postListItemPayload($post))->all(),
                ]),
            ],
            'contentSlots' => $this->contentSlotsFor($category->landingPage),
        ];
    }

    /**
     * @return array{contentItem: array<string, mixed>, context: array<string, mixed>, contentSlots: array<string, mixed>}
     */
    private function categoryDetailPreviewData(CmsTemplate $template, ?int $sampleId): array
    {
        $category = $this->previewCategory((string) $template->locale, $sampleId);

        if (! $category instanceof CmsCategory) {
            return $this->syntheticCategoryPreviewData($template, 'detail');
        }

        return [
            'contentItem' => $this->categoryContentItem($category, 'detail'),
            'context' => [
                'category' => array_merge($this->categoryPayload($category), [
                    'forms' => [],
                ]),
            ],
            'contentSlots' => $this->contentSlotsFor($category->landingPage),
        ];
    }

    /**
     * @return array{contentItem: array<string, mixed>, context: array<string, mixed>, contentSlots: array<string, mixed>}
     */
    private function tagIndexPreviewData(CmsTemplate $template): array
    {
        $locale = (string) $template->locale;
        $tags = $this->activeTagPayloads($locale);

        return [
            'contentItem' => $this->syntheticContentItem($template, public_text('taxonomy.tag_index_title', 'Tags', $locale)),
            'context' => [
                'tag_index' => [
                    'title' => public_text('taxonomy.tag_index_title', 'Tags', $locale),
                ],
                'tags' => $tags,
                'tag_count' => count($tags),
            ],
            'contentSlots' => [],
        ];
    }

    /**
     * @return array{contentItem: array<string, mixed>, context: array<string, mixed>, contentSlots: array<string, mixed>}
     */
    private function tagArchivePreviewData(CmsTemplate $template, ?int $sampleId): array
    {
        $tag = $this->previewTag((string) $template->locale, $sampleId);

        if (! $tag instanceof CmsTag) {
            return $this->syntheticTagPreviewData($template, 'archive');
        }

        $posts = $this->publishedPostQuery()
            ->with(['featuredMedia'])
            ->where('locale', $tag->locale)
            ->whereHas('tags', fn (Builder $query): Builder => $query->whereKey($tag->id))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(24)
            ->get();

        return [
            'contentItem' => $this->tagContentItem($tag, 'archive'),
            'context' => [
                'tag' => array_merge($this->tagPayload($tag), [
                    'blogs' => $posts->map(fn (CmsPost $post): array => $this->postListItemPayload($post, $tag))->all(),
                ]),
            ],
            'contentSlots' => $this->contentSlotsFor($tag->landingPage),
        ];
    }

    /**
     * @return array{contentItem: array<string, mixed>, context: array<string, mixed>, contentSlots: array<string, mixed>}
     */
    private function tagDetailPreviewData(CmsTemplate $template, ?int $sampleId): array
    {
        $tag = $this->previewTag((string) $template->locale, $sampleId);

        if (! $tag instanceof CmsTag) {
            return $this->syntheticTagPreviewData($template, 'detail');
        }

        return [
            'contentItem' => $this->tagContentItem($tag, 'detail'),
            'context' => [
                'tag' => array_merge($this->tagPayload($tag), [
                    'forms' => [],
                ]),
            ],
            'contentSlots' => $this->contentSlotsFor($tag->landingPage),
        ];
    }

    /**
     * @return array{contentItem: array<string, mixed>, context: array<string, mixed>, contentSlots: array<string, mixed>}
     */
    private function emptyPreviewData(CmsTemplate $template): array
    {
        $contentItem = $this->syntheticContentItem($template, $template->name);

        return [
            'contentItem' => $contentItem,
            'context' => [],
            'contentSlots' => ['content' => $this->syntheticContentBlocks($template)],
        ];
    }

    /**
     * @return array{contentItem: array<string, mixed>, context: array<string, mixed>, contentSlots: array<string, mixed>}
     */
    private function syntheticCategoryPreviewData(CmsTemplate $template, string $templateKey): array
    {
        $category = array_merge($this->syntheticContentItem($template, public_text('taxonomy.category_index_title', 'Categorieen', (string) $template->locale)), [
            'description' => __('cms_admin_ui.templates.preview.sample_description'),
            'parent' => null,
            'children' => [],
            'blogs' => $this->syntheticPostPayloads((string) $template->locale),
            'forms' => [],
        ]);

        return [
            'contentItem' => array_merge($category, ['template_key' => $templateKey]),
            'context' => ['category' => $category],
            'contentSlots' => ['content' => $this->syntheticContentBlocks($template)],
        ];
    }

    /**
     * @return array{contentItem: array<string, mixed>, context: array<string, mixed>, contentSlots: array<string, mixed>}
     */
    private function syntheticTagPreviewData(CmsTemplate $template, string $templateKey): array
    {
        $tag = array_merge($this->syntheticContentItem($template, public_text('taxonomy.tag_index_title', 'Tags', (string) $template->locale)), [
            'description' => __('cms_admin_ui.templates.preview.sample_description'),
            'blogs' => $this->syntheticPostPayloads((string) $template->locale),
            'forms' => [],
        ]);

        return [
            'contentItem' => array_merge($tag, ['template_key' => $templateKey]),
            'context' => ['tag' => $tag],
            'contentSlots' => ['content' => $this->syntheticContentBlocks($template)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syntheticContentItem(CmsTemplate $template, string $title): array
    {
        return [
            'id' => 'template-preview-'.$template->id,
            'title' => $title,
            'slug' => 'template-preview',
            'locale' => $template->locale,
            'url' => url('/template-preview'),
            'short_description' => __('cms_admin_ui.templates.preview.sample_excerpt'),
            'seo_title' => __('cms_admin_ui.templates.preview.sample_title'),
            'seo_description' => __('cms_admin_ui.templates.preview.sample_excerpt'),
            'published_at' => now()->toDateString(),
            'updated_at' => now()->toDateString(),
            'template_class' => $template->template_class,
            'template_key' => $template->template_key,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function syntheticContentBlocks(CmsTemplate $template): array
    {
        return $this->blockPayloadBuilder->handle([
            [
                'type' => 'text',
                'title' => __('cms_admin_ui.templates.preview.sample_content_title'),
                'text' => __('cms_admin_ui.templates.preview.sample_content_text'),
            ],
        ], templateContext: [
            '__template' => [
                'template_class' => $template->template_class,
                'template_key' => $template->template_key,
                'locale' => $template->locale,
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function syntheticPostPayloads(string $locale): array
    {
        return [
            [
                'id' => 'preview-blog-1',
                'title' => __('cms_admin_ui.templates.preview.sample_blog_title'),
                'slug' => 'voorbeeldblog',
                'url' => '#',
                'excerpt' => __('cms_admin_ui.templates.preview.sample_excerpt'),
                'published_at' => now()->toDateString(),
                'featured_media' => null,
                'categories' => [],
                'taxonomy_items' => [],
                'taxonomy_prefix' => '',
                'locale' => $locale,
            ],
        ];
    }

    /**
     * @return Builder<CmsPage>
     */
    private function publishedPageQuery(): Builder
    {
        return CmsPage::query()
            ->where('status', 'published')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    /**
     * @return Builder<CmsPost>
     */
    private function publishedPostQuery(): Builder
    {
        return CmsPost::query()
            ->where('status', 'published')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    private function previewCategory(string $locale, ?int $sampleId): ?CmsCategory
    {
        return CmsCategory::query()
            ->with([
                'parent:id,parent_id,landing_page_id,type,title,slug,locale,description,is_active',
                'landingPage:id,parent_id,title,slug,locale,status,is_home,published_at,content_blocks',
            ])
            ->where('type', 'post')
            ->where('locale', $locale)
            ->where('is_active', true)
            ->when($sampleId !== null, fn (Builder $query): Builder => $query->whereKey($sampleId))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->first(['id', 'parent_id', 'landing_page_id', 'type', 'title', 'slug', 'locale', 'description', 'is_active']);
    }

    private function previewTag(string $locale, ?int $sampleId): ?CmsTag
    {
        return CmsTag::query()
            ->with('landingPage:id,parent_id,title,slug,locale,status,is_home,published_at,content_blocks')
            ->where('locale', $locale)
            ->where('is_active', true)
            ->when($sampleId !== null, fn (Builder $query): Builder => $query->whereKey($sampleId))
            ->orderBy('title')
            ->first(['id', 'landing_page_id', 'title', 'slug', 'locale', 'description', 'is_active']);
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function previewOptions(CmsTemplate $template): array
    {
        return match ($template->template_key) {
            'page.detail' => $this->previewPageOptions((string) $template->locale),
            'blog.detail' => $this->previewPostOptions((string) $template->locale),
            'category.archive', 'category.detail' => $this->previewCategoryOptions((string) $template->locale),
            'tag.archive', 'tag.detail' => $this->previewTagOptions((string) $template->locale),
            default => [],
        };
    }

    private function previewOptionExists(CmsTemplate $template, ?int $sampleId): bool
    {
        if ($sampleId === null) {
            return false;
        }

        return collect($this->previewOptions($template))->contains(
            fn (array $option): bool => (int) $option['id'] === $sampleId,
        );
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function previewPageOptions(string $locale): array
    {
        return $this->publishedPageQuery()
            ->where('locale', $locale)
            ->orderByDesc('is_home')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->limit(50)
            ->get(['id', 'title', 'slug', 'is_home'])
            ->map(fn (CmsPage $page): array => [
                'id' => (int) $page->id,
                'label' => trim((string) $page->title).($page->is_home ? ' ('.__('cms_admin_ui.templates.preview.home_suffix').')' : ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function previewPostOptions(string $locale): array
    {
        return $this->publishedPostQuery()
            ->where('locale', $locale)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'title', 'published_at'])
            ->map(fn (CmsPost $post): array => [
                'id' => (int) $post->id,
                'label' => trim((string) $post->title).($post->published_at ? ' ('.$post->published_at->toDateString().')' : ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function previewCategoryOptions(string $locale): array
    {
        return CmsCategory::query()
            ->where('type', 'post')
            ->where('locale', $locale)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->limit(50)
            ->get(['id', 'title', 'slug'])
            ->map(fn (CmsCategory $category): array => [
                'id' => (int) $category->id,
                'label' => trim((string) $category->title),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function previewTagOptions(string $locale): array
    {
        return CmsTag::query()
            ->where('locale', $locale)
            ->where('is_active', true)
            ->orderBy('title')
            ->limit(50)
            ->get(['id', 'title', 'slug'])
            ->map(fn (CmsTag $tag): array => [
                'id' => (int) $tag->id,
                'label' => trim((string) $tag->title),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function postDetailPayload(CmsPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'locale' => $post->locale,
            'excerpt' => $post->excerpt,
            'published_at' => $post->published_at?->toDateString(),
            'featured_media' => $this->mediaUrl->payload($post->featuredMedia, (string) $post->locale),
            'author' => ['name' => $post->author?->name],
            'categories' => $this->taxonomyPayload($post->categories),
            'tags' => $this->tagTaxonomyPayload($post->tags),
            'template_class' => 'blog',
            'template_key' => 'blog.detail',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestPostPayloads(string $locale): array
    {
        return $this->publishedPostQuery()
            ->with(['featuredMedia', 'categories'])
            ->where('locale', $locale)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(24)
            ->get()
            ->map(fn (CmsPost $post): array => $this->postListItemPayload($post))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function postListItemPayload(CmsPost $post, ?CmsTag $activeTag = null): array
    {
        $taxonomyItems = $activeTag instanceof CmsTag
            ? [[
                'id' => $activeTag->id,
                'title' => $activeTag->title,
                'slug' => $activeTag->slug,
            ]]
            : $this->taxonomyPayload($post->categories);

        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'url' => $this->urlBuilder->postPath($post),
            'excerpt' => $post->excerpt,
            'published_at' => $post->published_at?->toDateString(),
            'featured_media' => $this->mediaUrl->payload($post->featuredMedia, (string) $post->locale),
            'categories' => $activeTag instanceof CmsTag ? [] : $taxonomyItems,
            'taxonomy_items' => $taxonomyItems,
            'taxonomy_prefix' => $activeTag instanceof CmsTag ? '#' : '',
        ];
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function taxonomyPayload(iterable $items): array
    {
        return collect($items)
            ->where('is_active', true)
            ->values()
            ->map(fn ($item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
            ])
            ->all();
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function tagTaxonomyPayload(iterable $items): array
    {
        return collect($items)
            ->where('is_active', true)
            ->values()
            ->map(fn ($item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'url' => $this->urlBuilder->tagPath($item),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeCategoryPayloads(string $locale): array
    {
        $categories = CmsCategory::query()
            ->where('type', 'post')
            ->where('locale', $locale)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'parent_id', 'type', 'title', 'slug', 'locale', 'description', 'is_active'])
            ->keyBy('id');

        return $categories
            ->map(fn (CmsCategory $category): array => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'title' => $category->title,
                'slug' => $category->slug,
                'url' => $this->urlBuilder->categoryPath($category, $categories),
                'description' => $category->description,
                'excerpt' => $category->description,
                'published_at' => null,
                'featured_media' => null,
                'categories' => [],
                'taxonomy_items' => [],
                'taxonomy_prefix' => '',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeTagPayloads(string $locale): array
    {
        return CmsTag::query()
            ->where('locale', $locale)
            ->where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'locale', 'description', 'is_active'])
            ->map(fn (CmsTag $tag): array => $this->tagPayload($tag))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryContentItem(CmsCategory $category, string $templateKey): array
    {
        return [
            'id' => $category->id,
            'title' => $category->title,
            'slug' => $category->slug,
            'locale' => $category->locale,
            'excerpt' => $category->description,
            'template_class' => 'category',
            'template_key' => $templateKey,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tagContentItem(CmsTag $tag, string $templateKey): array
    {
        return [
            'id' => $tag->id,
            'title' => $tag->title,
            'slug' => $tag->slug,
            'locale' => $tag->locale,
            'excerpt' => $tag->description,
            'template_class' => 'tag',
            'template_key' => $templateKey,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryPayload(CmsCategory $category): array
    {
        $categories = CmsCategory::query()
            ->where('type', $category->type)
            ->where('locale', $category->locale)
            ->get(['id', 'parent_id', 'slug', 'locale'])
            ->keyBy('id');

        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'title' => $category->title,
            'slug' => $category->slug,
            'url' => $this->urlBuilder->categoryPath($category, $categories),
            'detail_url' => $this->urlBuilder->categoryDetailPath($category, $categories),
            'description' => $category->description,
            'excerpt' => $category->description,
            'published_at' => null,
            'featured_media' => null,
            'categories' => [],
            'taxonomy_items' => [],
            'taxonomy_prefix' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tagPayload(CmsTag $tag): array
    {
        return [
            'id' => $tag->id,
            'title' => $tag->title,
            'slug' => $tag->slug,
            'url' => $this->urlBuilder->tagPath($tag),
            'detail_url' => $this->urlBuilder->tagDetailPath($tag),
            'description' => $tag->description,
            'excerpt' => $tag->description,
            'published_at' => null,
            'featured_media' => null,
            'categories' => [],
            'taxonomy_items' => [[
                'id' => $tag->id,
                'title' => $tag->title,
                'slug' => $tag->slug,
            ]],
            'taxonomy_prefix' => '#',
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>|array<string, mixed>>
     */
    private function contentSlotsFor(?CmsPage $page): array
    {
        if (! $page instanceof CmsPage) {
            return ['content' => []];
        }

        return [
            'content' => $this->blockPayloadBuilder->handle($page->content_blocks ?? [], page: $page),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sitePayload(Request $request, string $locale): array
    {
        return [
            'name' => $this->settingValue('general', 'site_name', config('app.name', 'RwSoft'), $locale),
            'tagline' => $this->settingValue('general', 'site_tagline', null, $locale),
            'default_locale' => $this->languageSettings->defaultLocale(),
            'current_locale' => $locale,
            'multilingual_enabled' => $this->languageSettings->multilingualEnabled(),
            'available_languages' => $this->languageSettings->languages(true),
            'available_locales' => $this->languageSettings->activeLocales(),
            'global_noindex' => true,
            'seo_default_title' => $this->settingValue('seo', 'default_title', null, $locale),
            'seo_default_description' => $this->settingValue('seo', 'default_description', null, $locale),
            'texts' => $this->publicTextResolver->all($locale),
            'active_theme_css_url' => $this->themeCssUrl($request),
            'favicon' => $this->faviconPayload(),
            'logo_url' => $this->versionedPublicUrl(
                $this->settingValue('branding', 'logo_path'),
                $this->settingValue('branding', 'logo_version'),
            ),
            'logo_show_tagline' => (bool) $this->settingValue('branding', 'logo_show_tagline', false),
        ];
    }

    /**
     * @return array{favicon_32_url: ?string, favicon_192_url: ?string, apple_touch_icon_url: ?string}
     */
    private function faviconPayload(): array
    {
        $version = $this->settingValue('branding', 'favicon_version');

        return [
            'favicon_32_url' => $this->versionedPublicUrl($this->settingValue('branding', 'favicon_32_path'), $version),
            'favicon_192_url' => $this->versionedPublicUrl($this->settingValue('branding', 'favicon_192_path'), $version),
            'apple_touch_icon_url' => $this->versionedPublicUrl($this->settingValue('branding', 'apple_touch_icon_path'), $version),
        ];
    }

    private function versionedPublicUrl(mixed $path, mixed $version): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        return $this->storageUrl->versionedUrl($path, $version);
    }

    private function themeCssUrl(Request $request): ?string
    {
        if (! Schema::hasTable('cms_themes')) {
            return null;
        }

        if ($request->filled(['theme_preview', 'theme_version'])) {
            $theme = CmsTheme::query()->find((int) $request->query('theme_preview'));

            if ($theme instanceof CmsTheme) {
                return route('cms.theme.preview', [
                    'theme' => $theme->id,
                    'hash' => (string) $request->query('theme_version'),
                ]);
            }
        }

        $theme = CmsTheme::query()
            ->with('activeVersion')
            ->where('is_active', true)
            ->first();

        if (! $theme instanceof CmsTheme || ! $theme->activeVersion instanceof CmsThemeVersion) {
            return null;
        }

        return route('cms.theme.active', ['hash' => $theme->activeVersion->version_hash]);
    }

    private function settingValue(string $group, string $key, mixed $default = null, ?string $locale = null): mixed
    {
        $setting = CmsSetting::query()
            ->with('translations')
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if (! $setting instanceof CmsSetting) {
            return $default;
        }

        if ($locale !== null) {
            $translation = $setting->translations->firstWhere('locale', $locale)
                ?? $setting->translations->firstWhere('locale', $this->languageSettings->defaultLocale());
            $value = $translation?->value['value'] ?? null;

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $setting->value['value'] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $contentItem
     * @return array<string, mixed>
     */
    private function previewSeo(CmsTemplate $template, array $contentItem): array
    {
        $title = __('cms_admin_ui.templates.preview.meta_title', [
            'template' => $template->name,
        ]);

        return [
            'title' => $title,
            'description' => (string) ($contentItem['excerpt'] ?? ''),
            'robots' => 'noindex,nofollow',
            'og_type' => 'website',
            'og_title' => $title,
            'og_description' => (string) ($contentItem['excerpt'] ?? ''),
            'twitter_card' => 'summary',
            'twitter_title' => $title,
            'twitter_description' => (string) ($contentItem['excerpt'] ?? ''),
        ];
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
}
