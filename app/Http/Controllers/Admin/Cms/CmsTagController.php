<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\CreateCmsTagTranslationAction;
use App\Actions\Admin\Cms\EnsureCmsTemplateDataUsesContextImagesAction;
use App\Actions\Admin\Cms\Health\EnsureCmsSlugRedirectAction;
use App\Actions\Admin\Cms\Health\ValidateCmsPublishReadinessAction;
use App\Actions\Admin\Cms\Revisions\BuildCmsTagRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\CmsRevisionPayloadAction;
use App\Actions\Admin\Cms\Revisions\CreateCmsRevisionAction;
use App\Actions\Admin\Cms\SyncCmsTagLandingPageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\StoreCmsTagRequest;
use App\Http\Requests\Admin\Cms\StoreCmsTagTranslationRequest;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use App\Support\Audit\AuditLogger;
use App\Support\Cms\CmsBlockRegistry;
use App\Support\Cms\CmsDownloadLibraryPayload;
use App\Support\Cms\CmsMediaLibraryPayload;
use App\Support\Cms\Seo\CmsSeoSettings;
use App\Support\PublicSite\CmsLanguageSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CmsTagController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsMediaLibraryPayload $mediaLibraryPayload,
        private readonly CmsDownloadLibraryPayload $downloadLibraryPayload,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()
            ->route('admin.cms.taxonomy.index', ['tab' => 'tags']);
    }

    public function create(): Response
    {
        return $this->edit(0);
    }

    public function edit(int $id): Response
    {
        $tag = $id > 0
            ? CmsTag::query()->with('landingPage')->findOrFail($id)
            : null;

        return Inertia::render('Admin/Cms/Tags/Edit', [
            'tag' => $tag ? $this->tagPayload($tag) : null,
            'translations' => $tag ? $this->translationPayload($tag) : [],
            'revisions' => $tag ? app(CmsRevisionPayloadAction::class)->handle($tag) : [],
            'missingLanguages' => $tag ? $this->missingLanguagePayload($tag) : [],
            'activeLanguages' => $this->languageSettings->languages(true),
            'availableLocales' => $this->languageSettings->activeLocales(),
            'placeableBlocks' => $this->placeableBlockOptions(),
            'statusOptions' => $this->statusOptions(),
            'formOptions' => CmsForm::query()
                ->where('is_active', true)
                ->orderBy('locale')
                ->orderBy('title')
                ->get(['translation_key', 'title', 'locale']),
            'mediaOptions' => $this->mediaLibraryPayload->assets(),
            'mediaFolders' => $this->mediaLibraryPayload->folders(),
            'downloadOptions' => $this->downloadLibraryPayload->assets(),
            'downloadFolders' => $this->downloadLibraryPayload->folders(),
            'categoryOptions' => $this->categoryOptions(),
            'tagOptions' => $this->tagOptions(),
            'contactSettings' => CmsSetting::contactPayload(),
            'archiveTemplateOptions' => $this->templateOptions('tag.archive'),
            'detailTemplateOptions' => $this->templateOptions('tag.detail'),
            'seoSettings' => app(CmsSeoSettings::class)->values(),
        ]);
    }

    public function store(
        StoreCmsTagRequest $request,
        int $id,
        SyncCmsTagLandingPageAction $syncLandingPage,
        BuildCmsTagRevisionSnapshotAction $buildRevisionSnapshot,
        CreateCmsRevisionAction $createRevision,
        ValidateCmsPublishReadinessAction $publishReadiness,
        EnsureCmsSlugRedirectAction $ensureSlugRedirect,
        EnsureCmsTemplateDataUsesContextImagesAction $ensureTemplateDataUsesContextImages,
    ): RedirectResponse {
        $validated = $request->validated();

        $readiness = $validated['status'] === 'published'
            ? $publishReadiness->content($validated, 'tag')
            : ['errors' => [], 'warnings' => []];

        if ($readiness['errors'] !== []) {
            return back()->withErrors(['status' => implode(' ', $readiness['errors'])])->withInput();
        }

        $tag = $id > 0
            ? CmsTag::query()->with('landingPage')->findOrFail($id)
            : new CmsTag;
        $isCreate = ! $tag->exists;
        $oldSlug = $tag->exists ? (string) $tag->slug : null;
        $oldStatus = $tag->landingPage?->status;

        DB::transaction(function () use ($tag, $validated, $request, $syncLandingPage, $buildRevisionSnapshot, $createRevision, $ensureTemplateDataUsesContextImages): void {
            $tag->fill($this->tagData($validated, $tag));

            if (blank($tag->translation_key)) {
                $tag->translation_key = (string) Str::ulid();
            }

            $tag->save();
            $contentBlocks = $ensureTemplateDataUsesContextImages->contentBlocks(
                $this->contentBlocksData($validated['content_blocks'] ?? []),
                'tag',
                (int) $tag->id,
            );

            $syncLandingPage->handle($tag, array_merge($validated, [
                'content_blocks' => $contentBlocks,
            ]), $request->user()?->id);

            $createRevision->handle(
                $tag,
                'full',
                $buildRevisionSnapshot->handle($tag),
                $request->user()?->id,
                __('cms_admin_ui.revisions.auto_revision_title'),
                metadata: [
                    'change_type' => $tag->wasRecentlyCreated ? 'create' : 'update',
                    'taxonomy_relations_count' => $tag->posts()->count(),
                ],
            );
        });

        $this->auditLogger->success(
            action: $isCreate ? 'cms.tag.create' : 'cms.tag.update',
            module: 'cms',
            subjectType: 'cms_tag',
            subjectKey: (string) $tag->id,
            message: __('cms_admin_ui.flash.saved.tag'),
            meta: [
                'title' => (string) $tag->title,
                'slug' => (string) $tag->slug,
                'locale' => (string) $tag->locale,
            ],
            request: $request,
        );

        $ensureSlugRedirect->handle('tag', $tag->locale, $oldSlug, $tag->slug, $oldStatus, (string) $validated['status'], $tag->id, $request);

        $redirect = redirect()
            ->route('admin.cms.tags.edit', ['id' => $tag->id])
            ->with('status', __('cms_admin_ui.flash.saved.tag'));

        if ($readiness['warnings'] !== []) {
            $redirect->with('warning', implode(' ', $readiness['warnings']));
        }

        return $redirect;
    }

    public function storeTranslation(
        StoreCmsTagTranslationRequest $request,
        int $id,
        CreateCmsTagTranslationAction $createTranslation,
    ): RedirectResponse {
        $validated = $request->validated();
        $tag = CmsTag::query()->with('landingPage')->findOrFail($id);
        $useAi = (bool) ($validated['use_ai'] ?? false);

        try {
            $translation = $createTranslation->handle(
                sourceTag: $tag,
                targetLocale: (string) $validated['target_locale'],
                authorId: $request->user()?->id,
                useAi: $useAi,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $useAi
                ? __('cms_admin_ui.flash.translation_failed_ai')
                : __('cms_admin_ui.flash.translation_failed'));
        }

        $this->auditLogger->success(
            action: 'cms.tag.translation.create',
            module: 'cms',
            subjectType: 'cms_tag',
            subjectKey: (string) $translation->id,
            message: __('cms_admin_ui.flash.translation_created'),
            meta: [
                'source_tag_id' => $tag->id,
                'target_tag_id' => $translation->id,
                'target_locale' => (string) $translation->locale,
                'use_ai' => $useAi,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.tags.edit', ['id' => $translation->id])
            ->with('status', $useAi
                ? __('cms_admin_ui.flash.translation_created_ai')
                : __('cms_admin_ui.flash.translation_created'));
    }

    /**
     * @return array<string, mixed>
     */
    private function tagPayload(CmsTag $tag): array
    {
        $page = $tag->landingPage;

        return [
            'id' => $tag->id,
            'title' => $tag->title,
            'slug' => $tag->slug,
            'locale' => $tag->locale,
            'translation_key' => $tag->translation_key,
            'translated_from_tag_id' => $tag->translated_from_tag_id,
            'landing_page_id' => $tag->landing_page_id,
            'archive_template_id' => $tag->archive_template_id,
            'detail_template_id' => $tag->detail_template_id,
            'description' => $tag->description,
            'is_active' => (bool) $tag->is_active,
            'status' => $page?->status ?? 'draft',
            'template' => $page?->template,
            'excerpt' => $page?->short_description ?? $tag->description,
            'content_blocks' => $page?->content_blocks ?? $this->defaultListBlocks(),
            'seo_title' => $page?->seo_title,
            'seo_description' => $page?->seo_description,
            'canonical_url' => $page?->canonical_url,
            'og_image_path' => $page?->og_image_path,
            'noindex' => (bool) ($page?->noindex ?? false),
            'is_searchable' => (bool) ($page?->is_searchable ?? true),
            'published_at' => optional($page?->published_at)->format('Y-m-d\TH:i'),
            'pdf_download_enabled' => (bool) ($tag->settings['pdf_download_enabled'] ?? false),
            'structured_data_schema_type' => $page?->settings['structured_data_schema_type'] ?? 'auto',
            'structured_data_extra' => $page?->settings['structured_data_extra'] ?? '',
            'ai_translation_review' => $this->aiTranslationReviewPayload($tag->settings ?? []),
            'created_at' => $tag->created_at?->toJSON(),
            'updated_at' => $tag->updated_at?->toJSON(),
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
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function tagData(array $validated, CmsTag $tag): array
    {
        return array_merge(
            Arr::only($validated, [
                'title',
                'slug',
                'locale',
                'archive_template_id',
                'detail_template_id',
                'description',
            ]),
            [
                'is_active' => (bool) ($validated['is_active'] ?? false),
                'settings' => array_merge(
                    [
                        'pdf_download_enabled' => (bool) ($validated['pdf_download_enabled'] ?? false),
                    ],
                    $this->translationReviewSettings($tag->settings ?? []),
                ),
            ],
        );
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
     * @return array<int, array{id: int, name: string, locale: string, label: string}>
     */
    private function templateOptions(string $templateKey): array
    {
        return CmsTemplate::query()
            ->where('template_class', 'tag')
            ->where('template_key', $templateKey)
            ->where('is_active', true)
            ->orderBy('locale')
            ->orderBy('name')
            ->get(['id', 'name', 'locale'])
            ->map(fn (CmsTemplate $template): array => [
                'id' => (int) $template->id,
                'name' => (string) $template->name,
                'locale' => (string) $template->locale,
                'label' => sprintf('%s (%s)', $template->name, $template->locale),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function translationPayload(CmsTag $tag): array
    {
        if (blank($tag->translation_key)) {
            return [];
        }

        return CmsTag::query()
            ->with('landingPage:id,settings')
            ->where('translation_key', $tag->translation_key)
            ->orderBy('locale')
            ->orderBy('title')
            ->get(['id', 'landing_page_id', 'title', 'slug', 'locale', 'is_active', 'translated_from_tag_id', 'updated_at'])
            ->map(fn (CmsTag $translation): array => [
                'id' => $translation->id,
                'title' => $translation->title,
                'slug' => $translation->slug,
                'locale' => $translation->locale,
                'is_active' => (bool) $translation->is_active,
                'translated_from_tag_id' => $translation->translated_from_tag_id,
                'structured_data_extra_filled' => filled($translation->landingPage?->settings['structured_data_extra'] ?? null),
                'edit_url' => route('admin.cms.tags.edit', ['id' => $translation->id]),
                'updated_at' => $translation->updated_at?->toIso8601String(),
                'is_current' => $translation->is($tag),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{locale: string, name: string, native_name: string}>
     */
    private function missingLanguagePayload(CmsTag $tag): array
    {
        $existingLocales = blank($tag->translation_key)
            ? collect([(string) $tag->locale])
            : CmsTag::query()
                ->where('translation_key', $tag->translation_key)
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
    private function defaultListBlocks(): array
    {
        return [$this->defaultContentBlock('list_grid', [
            'source_type' => 'tag',
            'tag_source' => 'current',
            'limit' => 24,
            'sort_field' => 'published_at',
            'sort_direction' => 'desc',
            'show_excerpt' => true,
            'show_image' => true,
            'show_date' => true,
            'show_categories' => true,
        ])];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function defaultContentBlock(string $rendererKey, array $data): array
    {
        $block = CmsPlaceableBlock::query()
            ->with('latestPublishedRevision')
            ->where('renderer_key', $rendererKey)
            ->where('status', 'published')
            ->firstOrFail();

        return [
            'cms_placeable_block_id' => (int) $block->id,
            'placeable_block_revision_id' => $block->latestPublishedRevision?->id,
            ...$data,
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
                        'source_type' => $block['source_type'] ?? 'tag',
                        'category_source' => $block['category_source'] ?? 'all',
                        'category_id' => $block['category_id'] ?? null,
                        'tag_source' => $block['tag_source'] ?? 'current',
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
