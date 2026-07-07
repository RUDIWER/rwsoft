<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\CreateCmsPostTranslationAction;
use App\Actions\Admin\Cms\EnsureCmsTemplateDataUsesContextImagesAction;
use App\Actions\Admin\Cms\Health\EnsureCmsSlugRedirectAction;
use App\Actions\Admin\Cms\Health\ValidateCmsPublishReadinessAction;
use App\Actions\Admin\Cms\Revisions\BuildCmsPostRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\CmsRevisionPayloadAction;
use App\Actions\Admin\Cms\Revisions\CreateCmsRevisionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\StoreCmsPostRequest;
use App\Http\Requests\Admin\Cms\StoreCmsPostTranslationRequest;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use App\Support\Audit\AuditLogger;
use App\Support\Cms\CmsBlockRegistry;
use App\Support\Cms\CmsDownloadLibraryPayload;
use App\Support\Cms\CmsMediaLibraryPayload;
use App\Support\Cms\Seo\CmsSeoSettings;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsStructuredDataBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CmsPostController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CmsStructuredDataBuilder $structuredDataBuilder,
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsMediaLibraryPayload $mediaLibraryPayload,
        private readonly CmsDownloadLibraryPayload $downloadLibraryPayload,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Cms/Posts/Index', [
            'posts' => CmsPost::query()
                ->orderByDesc('updated_at')
                ->get(['id', 'title', 'slug', 'locale', 'status', 'is_featured', 'published_at', 'updated_at']),
        ]);
    }

    public function create(): Response
    {
        return $this->edit(0);
    }

    public function edit(int $id): Response
    {
        $post = $id > 0
            ? CmsPost::query()->findOrFail($id)
            : null;

        return Inertia::render('Admin/Cms/Posts/Edit', [
            'postItem' => $post ? $this->postPayload($post) : null,
            'translations' => $post ? $this->translationPayload($post) : [],
            'revisions' => $post ? app(CmsRevisionPayloadAction::class)->handle($post) : [],
            'missingLanguages' => $post ? $this->missingLanguagePayload($post) : [],
            'activeLanguages' => $this->languageSettings->languages(true),
            'multilingualEnabled' => $this->languageSettings->multilingualEnabled(),
            'availableLocales' => $this->languageSettings->activeLocales(),
            'statusOptions' => $this->statusOptions(),
            'placeableBlocks' => $this->placeableBlockOptions(),
            'categoryOptions' => CmsCategory::query()
                ->where('type', 'post')
                ->where('is_active', true)
                ->orderBy('locale')
                ->orderBy('title')
                ->get(['id', 'title', 'locale']),
            'tagOptions' => CmsTag::query()
                ->where('is_active', true)
                ->orderBy('locale')
                ->orderBy('title')
                ->get(['id', 'title', 'locale']),
            'detailTemplateOptions' => $this->templateOptions(),
            'formOptions' => CmsForm::query()
                ->where('is_active', true)
                ->orderBy('locale')
                ->orderBy('title')
                ->get(['translation_key', 'title', 'locale']),
            'contactSettings' => CmsSetting::contactPayload(),
            'seoSettings' => app(CmsSeoSettings::class)->values(),
            'mediaOptions' => $this->mediaLibraryPayload->assets(),
            'mediaFolders' => $this->mediaLibraryPayload->folders(),
            'downloadOptions' => $this->downloadLibraryPayload->assets(),
            'downloadFolders' => $this->downloadLibraryPayload->folders(),
            'structuredData' => $this->structuredDataPayload($post),
        ]);
    }

    public function store(
        StoreCmsPostRequest $request,
        int $id,
        BuildCmsPostRevisionSnapshotAction $buildRevisionSnapshot,
        CreateCmsRevisionAction $createRevision,
        ValidateCmsPublishReadinessAction $publishReadiness,
        EnsureCmsSlugRedirectAction $ensureSlugRedirect,
        EnsureCmsTemplateDataUsesContextImagesAction $ensureTemplateDataUsesContextImages,
    ): RedirectResponse {
        $validated = $request->validated();

        $readiness = $validated['status'] === 'published'
            ? $publishReadiness->content($validated, 'post')
            : ['errors' => [], 'warnings' => []];

        if ($readiness['errors'] !== []) {
            return back()->withErrors(['status' => implode(' ', $readiness['errors'])])->withInput();
        }

        $post = $id > 0
            ? CmsPost::query()->findOrFail($id)
            : new CmsPost;
        $isCreate = ! $post->exists;
        $oldSlug = $post->exists ? (string) $post->slug : null;
        $oldStatus = $post->exists ? (string) $post->status : null;

        DB::transaction(function () use ($post, $validated, $request, $buildRevisionSnapshot, $createRevision, $ensureTemplateDataUsesContextImages): void {
            $post->fill($this->postData($validated, $post));

            if (! $post->exists) {
                $post->author_id = $request->user()?->id;
            }

            if ($post->status === 'published' && blank($post->published_at)) {
                $post->published_at = now();
            }

            if (blank($post->translation_key)) {
                $post->translation_key = (string) Str::ulid();
            }

            $post->save();
            $post->forceFill([
                'featured_media_asset_id' => $ensureTemplateDataUsesContextImages->mediaAssetId($post->featured_media_asset_id, 'post', (int) $post->id),
                'content_blocks' => $ensureTemplateDataUsesContextImages->contentBlocks(
                    is_array($post->content_blocks ?? null) ? $post->content_blocks : [],
                    'post',
                    (int) $post->id,
                ),
            ])->save();
            $post->categories()->sync($validated['category_ids'] ?? []);
            $post->tags()->sync($validated['tag_ids'] ?? []);

            $createRevision->handle(
                $post,
                'full',
                $buildRevisionSnapshot->handle($post),
                $request->user()?->id,
                __('cms_admin_ui.revisions.auto_revision_title'),
                metadata: [
                    'change_type' => $post->wasRecentlyCreated ? 'create' : 'update',
                    'content_blocks_count' => count($post->content_blocks ?? []),
                    'taxonomy_relations_count' => count($validated['category_ids'] ?? []) + count($validated['tag_ids'] ?? []),
                ],
            );
        });

        $this->auditLogger->success(
            action: $isCreate ? 'cms.post.create' : 'cms.post.update',
            module: 'cms',
            subjectType: 'cms_post',
            subjectKey: (string) $post->id,
            message: __('cms_admin_ui.flash.saved.post'),
            meta: [
                'title' => (string) $post->title,
                'slug' => (string) $post->slug,
                'locale' => (string) $post->locale,
                'status' => (string) $post->status,
            ],
            request: $request,
        );

        $ensureSlugRedirect->handle('post', $post->locale, $oldSlug, $post->slug, $oldStatus, $post->status, $post->id, $request);

        $redirect = redirect()
            ->route('admin.cms.posts.index')
            ->with('status', __('cms_admin_ui.flash.saved.post'));

        if ($readiness['warnings'] !== []) {
            $redirect->with('warning', implode(' ', $readiness['warnings']));
        }

        return $redirect;
    }

    public function storeTranslation(
        StoreCmsPostTranslationRequest $request,
        int $id,
        CreateCmsPostTranslationAction $createTranslation,
    ): RedirectResponse {
        $validated = $request->validated();
        $post = CmsPost::query()->findOrFail($id);
        $useAi = (bool) ($validated['use_ai'] ?? false);

        try {
            $translation = $createTranslation->handle(
                sourcePost: $post,
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
            action: 'cms.post.translation.create',
            module: 'cms',
            subjectType: 'cms_post',
            subjectKey: (string) $translation->id,
            message: __('cms_admin_ui.flash.translation_created'),
            meta: [
                'source_post_id' => $post->id,
                'target_post_id' => $translation->id,
                'target_locale' => (string) $translation->locale,
                'use_ai' => $useAi,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.posts.edit', ['id' => $translation->id])
            ->with('status', $useAi
                ? __('cms_admin_ui.flash.translation_created_ai')
                : __('cms_admin_ui.flash.translation_created'));
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
    private function postPayload(CmsPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'locale' => $post->locale,
            'translation_key' => $post->translation_key,
            'translated_from_post_id' => $post->translated_from_post_id,
            'status' => $post->status,
            'detail_template_id' => $post->detail_template_id,
            'excerpt' => $post->excerpt,
            'content_blocks' => $post->content_blocks ?? [],
            'featured_media_asset_id' => $post->featured_media_asset_id,
            'seo_title' => $post->seo_title,
            'seo_description' => $post->seo_description,
            'canonical_url' => $post->canonical_url,
            'og_image_path' => $post->og_image_path,
            'noindex' => (bool) $post->noindex,
            'is_featured' => (bool) $post->is_featured,
            'is_searchable' => (bool) $post->is_searchable,
            'published_at' => optional($post->published_at)->format('Y-m-d\TH:i'),
            'category_ids' => $post->categories()->pluck('cms_categories.id')->values(),
            'tag_ids' => $post->tags()->pluck('cms_tags.id')->values(),
            'pdf_download_enabled' => (bool) ($post->settings['pdf_download_enabled'] ?? false),
            'structured_data_schema_type' => $post->settings['structured_data_schema_type'] ?? 'auto',
            'structured_data_extra' => $post->settings['structured_data_extra'] ?? '',
            'ai_translation_review' => $this->aiTranslationReviewPayload($post->settings ?? []),
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
     * @return array<int, array<string, mixed>>
     */
    private function translationPayload(CmsPost $post): array
    {
        if (blank($post->translation_key)) {
            return [];
        }

        return CmsPost::query()
            ->where('translation_key', $post->translation_key)
            ->orderBy('locale')
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'locale', 'status', 'translated_from_post_id', 'settings', 'updated_at'])
            ->map(fn (CmsPost $translation): array => [
                'id' => $translation->id,
                'title' => $translation->title,
                'slug' => $translation->slug,
                'locale' => $translation->locale,
                'status' => $translation->status,
                'translated_from_post_id' => $translation->translated_from_post_id,
                'structured_data_extra_filled' => filled($translation->settings['structured_data_extra'] ?? null),
                'edit_url' => route('admin.cms.posts.edit', ['id' => $translation->id]),
                'updated_at' => $translation->updated_at?->toIso8601String(),
                'is_current' => $translation->is($post),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{locale: string, name: string, native_name: string}>
     */
    private function missingLanguagePayload(CmsPost $post): array
    {
        $existingLocales = blank($post->translation_key)
            ? collect([(string) $post->locale])
            : CmsPost::query()
                ->where('translation_key', $post->translation_key)
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
    private function postData(array $validated, CmsPost $post): array
    {
        return array_merge(
            Arr::only($validated, [
                'title',
                'slug',
                'locale',
                'status',
                'detail_template_id',
                'excerpt',
                'content_blocks',
                'featured_media_asset_id',
                'seo_title',
                'seo_description',
                'canonical_url',
                'og_image_path',
                'published_at',
            ]),
            [
                'content_blocks' => $this->contentBlocksData($validated['content_blocks'] ?? []),
                'noindex' => (bool) ($validated['noindex'] ?? false),
                'is_featured' => (bool) ($validated['is_featured'] ?? false),
                'is_searchable' => (bool) ($validated['is_searchable'] ?? false),
                'settings' => array_merge(
                    $this->settingsData($validated),
                    $this->translationReviewSettings($post->settings ?? []),
                ),
            ],
        );
    }

    /**
     * @return array<int, array{id: int, name: string, locale: string, label: string}>
     */
    private function templateOptions(): array
    {
        return CmsTemplate::query()
            ->where('template_class', 'blog')
            ->where('template_key', 'blog.detail')
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
    private function settingsData(array $validated): array
    {
        return array_filter([
            'pdf_download_enabled' => (bool) ($validated['pdf_download_enabled'] ?? false),
            'structured_data_schema_type' => $validated['structured_data_schema_type'] ?? 'auto',
            'structured_data_extra' => $validated['structured_data_extra'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredDataPayload(?CmsPost $post): array
    {
        $site = [
            'name' => config('app.name', 'RwSoft'),
            'global_noindex' => false,
        ];

        $automatic = $post instanceof CmsPost
            ? $this->structuredDataBuilder->encode($this->structuredDataBuilder->forPost($post, $site))
            : null;

        return [
            'automatic' => $automatic ?: '{}',
            'final' => $automatic ?: '{}',
            'placeholders' => $this->structuredDataBuilder->placeholders('cms.post.json_ld'),
            'schemaTypeOptions' => [
                ['value' => 'auto', 'label' => __('cms_admin_ui.structured_data.auto')],
                ['value' => 'BlogPosting', 'label' => 'BlogPosting'],
                ['value' => 'NewsArticle', 'label' => 'NewsArticle'],
                ['value' => 'Article', 'label' => 'Article'],
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
}
