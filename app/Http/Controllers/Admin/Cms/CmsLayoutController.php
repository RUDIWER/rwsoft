<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\CreateCmsLayoutTranslationAction;
use App\Actions\Admin\Cms\GenerateCmsHtmlAnchorAction;
use App\Actions\Admin\Cms\RenderCmsPlacementPreviewsAction;
use App\Actions\Admin\Cms\Revisions\BuildCmsLayoutRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\CmsRevisionPayloadAction;
use App\Actions\Admin\Cms\Revisions\CreateCmsRevisionAction;
use App\Actions\Admin\Cms\SaveCmsLayoutSectionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\StoreCmsLayoutRequest;
use App\Http\Requests\Admin\Cms\StoreCmsLayoutTranslationRequest;
use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsColorPaletteItem;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use App\Support\Audit\AuditLogger;
use App\Support\Cms\CmsMediaLibraryPayload;
use App\Support\Cms\CmsResponsiveLayoutNormalizer;
use App\Support\Cms\CmsStyleTokenOptions;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use App\Support\PublicSite\CmsNavigationBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CmsLayoutController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsLocalePermission $localePermission,
        private readonly CmsMediaLibraryPayload $mediaLibraryPayload,
        private readonly CmsStyleTokenOptions $styleTokenOptions,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Cms/Layouts/Index', [
            'layouts' => CmsLayout::query()
                ->withCount(['templates', 'sections'])
                ->orderBy('locale')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(fn (CmsLayout $layout): array => $this->layoutPayload($layout))
                ->values(),
        ]);
    }

    public function create(): Response
    {
        return $this->edit(0);
    }

    public function edit(int $id): Response
    {
        $request = request();
        $layout = $id > 0
            ? CmsLayout::query()
                ->with([
                    'sections.placements.block.placeableBlock.latestPublishedRevision',
                    'sections.placements.publishedStyleRevision',
                    'sections.placements.styleRevisions.author:id,name,email',
                    'sections.placements.childPlacements.block.placeableBlock.latestPublishedRevision',
                    'sections.placements.childPlacements.publishedStyleRevision',
                    'sections.placements.childPlacements.styleRevisions.author:id,name,email',
                ])
                ->withCount(['templates', 'sections'])
                ->findOrFail($id)
            : null;

        return Inertia::render('Admin/Cms/Layouts/Edit', [
            'layoutItem' => $layout ? $this->layoutPayload($layout) : null,
            'placeableBlocks' => $this->placeableBlockOptions(),
            'translations' => $layout ? $this->translationPayload($layout) : [],
            'revisions' => $layout ? app(CmsRevisionPayloadAction::class)->handle($layout) : [],
            'missingLanguages' => $layout ? $this->missingLanguagePayload($layout) : [],
            'activeLanguages' => $this->languageSettings->languages(true),
            'availableLocales' => $this->languageSettings->activeLocales(),
            'multilingualEnabled' => $this->languageSettings->multilingualEnabled(),
            'canManageCodeBlocks' => $this->canManageCodeBlocks($request),
            'colorPaletteItems' => CmsColorPaletteItem::activePayload(),
            'styleTokenOptions' => $this->styleTokenOptions->all(),
            'activeThemeFontFaceCss' => $this->styleTokenOptions->activeFontFaceCss(),
            'headSystemBlockPreviews' => $this->headSystemBlockPreviews(),
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
        ]);
    }

    public function previewSection(Request $request, RenderCmsPlacementPreviewsAction $renderPreviews, CmsNavigationBuilder $navigationBuilder): JsonResponse
    {
        $validated = $request->validate([
            'zone' => ['required', 'string', Rule::in(['header', 'footer', 'content'])],
            'locale' => ['nullable', 'string', 'max:12'],
            'device' => ['nullable', 'string', Rule::in(['desktop', 'tablet', 'mobile'])],
            'section' => ['required', 'array'],
            'section.uid' => ['nullable', 'string', 'max:120'],
            'section.id' => ['nullable', 'integer'],
            'section.name' => ['nullable', 'string', 'max:255'],
            'section.is_active' => ['nullable', 'boolean'],
            'section.visible_mobile' => ['nullable', 'boolean'],
            'section.visible_tablet' => ['nullable', 'boolean'],
            'section.visible_desktop' => ['nullable', 'boolean'],
            'section.settings' => ['nullable', 'array'],
            'section.placements' => ['nullable', 'array', 'max:60'],
            'section.placements.*.uid' => ['required', 'string', 'max:120'],
            'section.placements.*.id' => ['nullable', 'integer'],
            'section.placements.*.is_active' => ['nullable', 'boolean'],
            'section.placements.*.visible_mobile' => ['nullable', 'boolean'],
            'section.placements.*.visible_tablet' => ['nullable', 'boolean'],
            'section.placements.*.visible_desktop' => ['nullable', 'boolean'],
            'section.placements.*.mobile_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'section.placements.*.tablet_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'section.placements.*.desktop_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'section.placements.*.layout_config' => ['nullable', 'array'],
            'section.placements.*.style_config' => ['nullable', 'array'],
            'section.placements.*.published_style_revision' => ['nullable', 'array'],
            'section.placements.*.height_mode' => ['nullable', 'string', Rule::in(['auto', 'fixed', 'min'])],
            'section.placements.*.height_value' => ['nullable', 'string', 'max:32'],
            'section.placements.*.cache_strategy' => ['nullable', 'string', Rule::in(['inherit', 'none', 'block', 'layout'])],
            'section.placements.*.settings' => ['nullable', 'array'],
            'section.placements.*.block' => ['required', 'array'],
            'section.placements.*.block.cms_placeable_block_id' => ['required', 'integer', Rule::exists('cms_placeable_blocks', 'id')->whereNull('deleted_at')],
            'section.placements.*.block.placeable_block_revision_id' => ['nullable', 'integer', Rule::exists('cms_placeable_block_revisions', 'id')],
        ]);

        $locale = (string) ($validated['locale'] ?: app()->getLocale());

        if (! $this->localePermission->canEditLocale($request->user(), $locale)) {
            abort(403);
        }

        app()->setLocale($locale);

        return response()->json([
            'previews' => $renderPreviews->handle(
                $validated['section'],
                (string) $validated['zone'],
                $locale,
                (string) ($validated['device'] ?? 'desktop'),
                $this->previewSitePayload($locale),
                $navigationBuilder->handle($locale),
                $this->themeCssUrl(),
            ),
        ]);
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

    public function store(
        StoreCmsLayoutRequest $request,
        int $id,
        GenerateCmsHtmlAnchorAction $htmlAnchorAction,
        SaveCmsLayoutSectionsAction $saveSections,
        BuildCmsLayoutRevisionSnapshotAction $buildRevisionSnapshot,
        CreateCmsRevisionAction $createRevision,
    ): RedirectResponse {
        $validated = $request->validated();
        $layout = $id > 0 ? CmsLayout::query()->findOrFail($id) : new CmsLayout;
        $isCreate = ! $layout->exists;

        DB::transaction(function () use ($layout, $validated, $request, $htmlAnchorAction, $saveSections, $buildRevisionSnapshot, $createRevision): void {
            $isDefault = (bool) ($validated['is_default'] ?? false);

            if ($isDefault) {
                CmsLayout::query()
                    ->where('locale', $validated['locale'])
                    ->when($layout->exists, fn ($query) => $query->where('id', '!=', $layout->id))
                    ->update(['is_default' => false]);
            }

            $layout->fill([
                'name' => $validated['name'],
                'locale' => $validated['locale'],
                'is_default' => $isDefault,
                'is_active' => $isDefault ? true : (bool) ($validated['is_active'] ?? false),
                'cache_strategy' => $validated['cache_strategy'],
                'settings' => $htmlAnchorAction->handle(
                    $layout,
                    $this->layoutSettings($validated['settings'] ?? []),
                    [$validated['name'], $validated['locale'], 'layout'],
                ),
            ])->save();

            if (blank($layout->translation_key)) {
                $layout->forceFill(['translation_key' => (string) Str::ulid()])->save();
            }

            $saveSections->handle($layout, $validated['sections'] ?? []);

            $createRevision->handle(
                $layout,
                'full',
                $buildRevisionSnapshot->handle($layout),
                $request->user()?->id,
                __('cms_admin_ui.revisions.auto_revision_title'),
                metadata: ['change_type' => $layout->wasRecentlyCreated ? 'create' : 'update'],
            );
        });

        $this->auditLogger->success(
            action: $isCreate ? 'cms.layout.create' : 'cms.layout.update',
            module: 'cms',
            subjectType: 'cms_layout',
            subjectKey: (string) $layout->id,
            message: __('cms_admin_ui.flash.saved.layout'),
            meta: [
                'name' => (string) $layout->name,
                'locale' => (string) $layout->locale,
                'is_default' => (bool) $layout->is_default,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.layouts.edit', ['id' => $layout->id])
            ->with('status', __('cms_admin_ui.flash.saved.layout'));
    }

    public function storeTranslation(
        StoreCmsLayoutTranslationRequest $request,
        int $id,
        CreateCmsLayoutTranslationAction $createTranslation,
    ): RedirectResponse {
        $validated = $request->validated();
        $layout = CmsLayout::query()->findOrFail($id);
        $useAi = (bool) ($validated['use_ai'] ?? false);

        try {
            $translation = $createTranslation->handle(
                sourceLayout: $layout,
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
            action: 'cms.layout.translation.create',
            module: 'cms',
            subjectType: 'cms_layout',
            subjectKey: (string) $translation->id,
            message: __('cms_admin_ui.flash.layout_translation_created'),
            meta: [
                'source_layout_id' => $layout->id,
                'target_layout_id' => $translation->id,
                'target_locale' => (string) $translation->locale,
                'use_ai' => $useAi,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.layouts.edit', ['id' => $translation->id])
            ->with('status', $useAi
                ? __('cms_admin_ui.flash.layout_translation_created_ai')
                : __('cms_admin_ui.flash.layout_translation_created'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $layout = CmsLayout::query()->withCount('templates')->findOrFail($id);

        if (! $this->localePermission->canEditLocale($request->user(), (string) $layout->locale)) {
            abort(403);
        }

        if ($layout->is_default) {
            return back()->with('error', __('cms_admin_ui.flash.layout_default_delete_blocked'));
        }

        if ($layout->templates_count > 0) {
            return back()->with('error', __('cms_admin_ui.flash.layout_in_use_delete_blocked'));
        }

        $layout->delete();

        $this->auditLogger->success(
            action: 'cms.layout.delete',
            module: 'cms',
            subjectType: 'cms_layout',
            subjectKey: (string) $layout->id,
            message: __('cms_admin_ui.flash.deleted.layout'),
            meta: ['name' => (string) $layout->name],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.layouts.index')
            ->with('status', __('cms_admin_ui.flash.deleted.layout'));
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutPayload(CmsLayout $layout): array
    {
        return [
            'id' => $layout->id,
            'name' => $layout->name,
            'locale' => $layout->locale,
            'translation_key' => $layout->translation_key,
            'translated_from_layout_id' => $layout->translated_from_layout_id,
            'is_default' => (bool) $layout->is_default,
            'is_active' => (bool) $layout->is_active,
            'cache_strategy' => $layout->cache_strategy,
            'settings' => $layout->settings ?? [],
            'pages_count' => (int) ($layout->templates_count ?? 0),
            'templates_count' => (int) ($layout->templates_count ?? 0),
            'sections_count' => (int) ($layout->sections_count ?? 0),
            'updated_at' => $layout->updated_at?->toDateTimeString(),
            'created_at' => $layout->created_at?->toDateTimeString(),
            'sections' => $layout->relationLoaded('sections')
                ? $this->sectionsPayload($layout)
                : $this->emptySectionsPayload(),
            'ai_translation_review' => $this->aiTranslationReviewPayload($layout->settings ?? []),
        ];
    }

    /**
     * @return array{head: array<int, array<string, mixed>>, header: array<int, array<string, mixed>>, footer: array<int, array<string, mixed>>, body_end: array<int, array<string, mixed>>}
     */
    private function sectionsPayload(CmsLayout $layout): array
    {
        return [
            'head' => $this->zoneSectionsPayload($layout, 'head'),
            'header' => $this->zoneSectionsPayload($layout, 'header'),
            'footer' => $this->zoneSectionsPayload($layout, 'footer'),
            'body_end' => $this->zoneSectionsPayload($layout, 'body_end'),
        ];
    }

    /**
     * @return array{head: array<int, array<string, mixed>>, header: array<int, array<string, mixed>>, footer: array<int, array<string, mixed>>, body_end: array<int, array<string, mixed>>}
     */
    private function emptySectionsPayload(): array
    {
        return [
            'head' => [],
            'header' => [],
            'footer' => [],
            'body_end' => [],
        ];
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
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function layoutSettings(array $settings): array
    {
        return [
            ...$settings,
            'html_anchor' => is_string($settings['html_anchor'] ?? null) ? trim($settings['html_anchor']) : null,
            'scroll_mode' => in_array($settings['scroll_mode'] ?? null, ['browser', 'internal'], true)
                ? $settings['scroll_mode']
                : 'browser',
            'background' => app(CmsResponsiveLayoutNormalizer::class)->normalizeBackground(
                is_array($settings['background'] ?? null) ? $settings['background'] : null,
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function translationPayload(CmsLayout $layout): array
    {
        if (blank($layout->translation_key)) {
            return [];
        }

        return CmsLayout::query()
            ->where('translation_key', $layout->translation_key)
            ->withCount(['templates', 'sections'])
            ->orderBy('locale')
            ->orderBy('name')
            ->get(['id', 'name', 'locale', 'is_active', 'is_default', 'translation_key', 'translated_from_layout_id', 'settings', 'updated_at'])
            ->map(fn (CmsLayout $translation): array => [
                'id' => $translation->id,
                'name' => $translation->name,
                'locale' => $translation->locale,
                'is_active' => (bool) $translation->is_active,
                'is_default' => (bool) $translation->is_default,
                'translated_from_layout_id' => $translation->translated_from_layout_id,
                'pages_count' => (int) ($translation->templates_count ?? 0),
                'templates_count' => (int) ($translation->templates_count ?? 0),
                'sections_count' => (int) ($translation->sections_count ?? 0),
                'updated_at' => $translation->updated_at?->toIso8601String(),
                'is_current' => $translation->is($layout),
                'ai_translation_review' => $this->aiTranslationReviewPayload($translation->settings ?? []),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{locale: string, name: string, native_name: string}>
     */
    private function missingLanguagePayload(CmsLayout $layout): array
    {
        $existingLocales = blank($layout->translation_key)
            ? collect([(string) $layout->locale])
            : CmsLayout::query()
                ->where('translation_key', $layout->translation_key)
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
     * @return array<int, array<string, mixed>>
     */
    private function zoneSectionsPayload(CmsLayout $layout, string $zone): array
    {
        return $layout->sections
            ->where('zone', $zone)
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
                    ->map(fn (CmsBlockPlacement $placement): array => $this->placementPayload($placement, $zone))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
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
    private function placementPayload(CmsBlockPlacement $placement, string $zone): array
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
                    ->map(fn (CmsBlockPlacement $childPlacement): array => $this->placementPayload($childPlacement, 'slot'))
                    ->all(),
            ])
            ->all();
    }

    private function canManageCodeBlocks(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user?->is_platform_admin || $user?->canAccessRoute('admin.cms.layouts.code-blocks.manage'));
    }

    /**
     * @return array<string, string>
     */
    private function headSystemBlockPreviews(): array
    {
        $paths = [
            'site_head_meta' => resource_path('views/public/system/partials/head-meta.blade.php'),
            'site_head_favicons' => resource_path('views/public/system/partials/head-favicons.blade.php'),
            'site_head_system_assets' => resource_path('views/public/system/partials/head-system-assets.blade.php'),
            'site_head_theme' => resource_path('views/public/system/partials/head-theme.blade.php'),
        ];

        return collect($paths)
            ->mapWithKeys(fn (string $path, string $type): array => [
                $type => File::exists($path) ? File::get($path) : '',
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function previewSitePayload(string $locale): array
    {
        return [
            'name' => (string) $this->settingValue('general', 'site_name', config('app.name', 'RwSoft'), $locale),
            'tagline' => $this->settingValue('general', 'site_tagline', null, $locale),
            'default_locale' => $this->languageSettings->defaultLocale(),
            'current_locale' => $locale,
            'multilingual_enabled' => $this->languageSettings->multilingualEnabled(),
            'available_languages' => $this->languageSettings->languages(true),
            'available_locales' => $this->languageSettings->activeLocales(),
            'active_theme_css_url' => $this->themeCssUrl(),
            'logo_url' => null,
            'logo_show_tagline' => (bool) $this->settingValue('branding', 'logo_show_tagline', false),
        ];
    }

    private function themeCssUrl(): ?string
    {
        if (! Schema::hasTable('cms_themes')) {
            return null;
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
}
