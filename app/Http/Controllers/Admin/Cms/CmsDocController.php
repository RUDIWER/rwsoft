<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\CreateOrUpdateCmsDocPageTranslationAction;
use App\Actions\Admin\Cms\Revisions\CreateCmsRevisionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\StoreCmsDocCollectionRequest;
use App\Http\Requests\Admin\Cms\StoreCmsDocPageRequest;
use App\Http\Requests\Admin\Cms\StoreCmsDocPageTranslationRequest;
use App\Http\Requests\Admin\Cms\StoreCmsDocVersionRequest;
use App\Models\Cms\CmsDocCollection;
use App\Models\Cms\CmsDocPage;
use App\Models\Cms\CmsDocVersion;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsModule;
use App\Models\Cms\CmsTemplate;
use App\Support\Cms\Docs\CmsDocsMarkdownRenderer;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CmsDocController extends Controller
{
    public function __construct(
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsDocsMarkdownRenderer $markdownRenderer,
    ) {}

    public function index(): Response
    {
        $this->assertDocsModuleInstalled();

        return Inertia::render('Admin/Cms/Docs/Index', [
            'collections' => $this->collectionDashboardPayload(),
            'templateInfo' => $this->docsTemplateInfo(),
        ]);
    }

    public function collectionPages(int $collection): Response
    {
        $this->assertDocsModuleInstalled();

        $collection = CmsDocCollection::query()->findOrFail($collection);
        $versionIds = CmsDocVersion::query()
            ->where('cms_doc_collection_id', $collection->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return Inertia::render('Admin/Cms/Docs/CollectionPages', [
            'collection' => $this->collectionPayload($collection),
            'templateInfo' => $this->docsTemplateInfo(),
            'versions' => CmsDocVersion::query()
                ->where('cms_doc_collection_id', $collection->id)
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->get(['id', 'cms_doc_collection_id', 'label', 'slug', 'is_default', 'is_active', 'sort_order']),
            'pages' => CmsDocPage::query()
                ->with(['version.collection:id,name', 'parent:id,title'])
                ->whereIn('cms_doc_version_id', $versionIds)
                ->orderByDesc('updated_at')
                ->get(['id', 'cms_doc_version_id', 'parent_id', 'title', 'path', 'locale', 'status', 'sort_order', 'published_at', 'updated_at']),
        ]);
    }

    public function createCollection(): Response
    {
        $this->assertDocsModuleInstalled();

        return $this->renderCollectionForm(new CmsDocCollection);
    }

    public function editCollection(int $collection): Response
    {
        $this->assertDocsModuleInstalled();

        return $this->renderCollectionForm(CmsDocCollection::query()->findOrFail($collection));
    }

    private function renderCollectionForm(CmsDocCollection $collection): Response
    {
        return Inertia::render('Admin/Cms/Docs/CollectionEdit', [
            'collection' => $collection->exists ? $collection : null,
        ]);
    }

    public function storeCollection(StoreCmsDocCollectionRequest $request, int $collection): RedirectResponse
    {
        $this->assertDocsModuleInstalled();

        $collection = $collection > 0 ? CmsDocCollection::query()->findOrFail($collection) : new CmsDocCollection;
        $collection->fill($request->validated());
        $collection->is_active = (bool) $request->boolean('is_active', true);
        $collection->sort_order = (int) $request->integer('sort_order', 0);
        $collection->save();

        return redirect()
            ->route('admin.cms.docs.collections.edit', ['collection' => $collection->id])
            ->with('status', __('cms_admin_ui.docs.flash.collection_saved'));
    }

    public function createVersion(): Response
    {
        $this->assertDocsModuleInstalled();

        return $this->renderVersionForm(new CmsDocVersion);
    }

    public function editVersion(int $version): Response
    {
        $this->assertDocsModuleInstalled();

        return $this->renderVersionForm(CmsDocVersion::query()->findOrFail($version));
    }

    private function renderVersionForm(CmsDocVersion $version): Response
    {
        return Inertia::render('Admin/Cms/Docs/VersionEdit', [
            'version' => $version->exists ? $version->load('collection:id,name') : null,
            'collectionOptions' => $this->collectionOptions(),
        ]);
    }

    public function storeVersion(StoreCmsDocVersionRequest $request, int $version): RedirectResponse
    {
        $this->assertDocsModuleInstalled();

        $version = $version > 0 ? CmsDocVersion::query()->findOrFail($version) : new CmsDocVersion;
        $validated = $request->validated();

        DB::transaction(function () use ($version, $validated, $request): void {
            $version->fill($validated);
            $version->is_default = (bool) $request->boolean('is_default');
            $version->is_active = (bool) $request->boolean('is_active', true);
            $version->sort_order = (int) $request->integer('sort_order', 0);
            $version->save();

            if ($version->is_default) {
                CmsDocVersion::query()
                    ->where('cms_doc_collection_id', $version->cms_doc_collection_id)
                    ->whereKeyNot($version->id)
                    ->update(['is_default' => false]);
            }
        });

        return redirect()
            ->route('admin.cms.docs.versions.edit', ['version' => $version->id])
            ->with('status', __('cms_admin_ui.docs.flash.version_saved'));
    }

    public function createPage(Request $request): Response
    {
        $this->assertDocsModuleInstalled();

        return $this->renderPageForm(new CmsDocPage, (int) $request->integer('collection', 0));
    }

    public function editPage(int $page): Response
    {
        $this->assertDocsModuleInstalled();

        return $this->renderPageForm(CmsDocPage::query()->findOrFail($page));
    }

    private function renderPageForm(CmsDocPage $page, int $collectionId = 0): Response
    {
        $page->loadMissing(['version.collection:id,name', 'parent:id,title']);

        return Inertia::render('Admin/Cms/Docs/PageEdit', [
            'docPage' => $page->exists ? $page : null,
            'versionOptions' => $this->versionOptions($page->exists ? 0 : $collectionId),
            'parentOptions' => $this->parentOptions($page),
            'activeLanguages' => $this->languageSettings->languages(true),
            'availableLocales' => $this->languageSettings->activeLocales(),
            'translations' => $page->exists ? $this->translationPayload($page) : [],
            'missingLanguages' => $page->exists ? $this->missingLanguagePayload($page) : [],
            'mediaOptions' => $this->mediaOptions(),
        ]);
    }

    public function storePage(StoreCmsDocPageRequest $request, int $page, CreateCmsRevisionAction $createRevision): RedirectResponse
    {
        $this->assertDocsModuleInstalled();

        $page = $page > 0 ? CmsDocPage::query()->findOrFail($page) : new CmsDocPage;
        $validated = $request->validated();
        $rendered = $this->markdownRenderer->render((string) ($validated['body'] ?? ''), (string) $validated['locale']);

        DB::transaction(function () use ($page, $validated, $request, $rendered, $createRevision): void {
            $page->fill($validated);
            $page->path = trim((string) $validated['path'], '/');
            $page->plain_text = $rendered['plain_text'];
            $page->noindex = (bool) $request->boolean('noindex');
            $page->sort_order = (int) $request->integer('sort_order', 0);

            if (! $page->exists) {
                $page->author_id = $request->user()?->id;
            }

            if (blank($page->translation_key)) {
                $page->translation_key = (string) Str::ulid();
            }

            if ($page->status === 'published' && blank($page->published_at)) {
                $page->published_at = now();
            }

            $page->save();

            $createRevision->handle(
                $page,
                'full',
                $this->revisionSnapshot($page),
                $request->user()?->id,
                __('cms_admin_ui.revisions.auto_revision_title'),
                metadata: ['change_type' => $page->wasRecentlyCreated ? 'create' : 'update'],
            );
        });

        return redirect()
            ->route('admin.cms.docs.pages.edit', ['page' => $page->id])
            ->with('status', __('cms_admin_ui.docs.flash.page_saved'));
    }

    public function storePageTranslation(
        StoreCmsDocPageTranslationRequest $request,
        int $page,
        CreateOrUpdateCmsDocPageTranslationAction $createOrUpdateTranslation,
    ): RedirectResponse {
        $this->assertDocsModuleInstalled();

        $page = CmsDocPage::query()->findOrFail($page);
        $validated = $request->validated();
        $targetLocales = $request->targetLocales();
        $useAi = (bool) ($validated['use_ai'] ?? true);

        try {
            $result = $createOrUpdateTranslation->handle(
                sourcePage: $page,
                sourceData: [
                    'title' => $validated['source_title'] ?? $page->title,
                    'body' => $validated['source_body'] ?? $page->body,
                    'seo_title' => $validated['source_seo_title'] ?? $page->seo_title,
                    'seo_description' => $validated['source_seo_description'] ?? $page->seo_description,
                ],
                targetLocales: $targetLocales,
                authorId: (int) $request->user()->id,
                useAi: $useAi,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $useAi
                ? __('cms_admin_ui.docs.flash.translation_failed_ai')
                : __('cms_admin_ui.docs.flash.translation_failed'));
        }

        $targetPage = count($targetLocales) === 1 ? ($result['pages'][0] ?? null) : null;

        return redirect()
            ->route('admin.cms.docs.pages.edit', ['page' => $targetPage?->id ?? $page->id])
            ->with('status', $useAi
                ? trans_choice('cms_admin_ui.docs.flash.translations_synced_ai', count($targetLocales), ['count' => count($targetLocales)])
                : __('cms_admin_ui.docs.flash.translation_created'));
    }

    public function bulkStatus(Request $request): RedirectResponse
    {
        $this->assertDocsModuleInstalled();

        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['publish', 'unpublish'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', Rule::exists((new CmsDocPage)->getTable(), 'id')],
            'collection_id' => ['nullable', 'integer', Rule::exists((new CmsDocCollection)->getTable(), 'id')],
        ]);

        $ids = collect($validated['ids'])->map(fn ($id): int => (int) $id)->all();
        $collectionId = (int) ($validated['collection_id'] ?? 0);

        $pages = CmsDocPage::query()
            ->whereIn('id', $ids)
            ->when($collectionId > 0, function ($query) use ($collectionId): void {
                $query->whereHas('version', fn ($versionQuery) => $versionQuery->where('cms_doc_collection_id', $collectionId));
            })
            ->get(['id', 'locale', 'status', 'published_at']);

        abort_unless($pages->count() === count($ids), 422);

        foreach ($pages as $page) {
            abort_unless(app(CmsLocalePermission::class)->canEditLocale($request->user(), (string) $page->locale), 403);
        }

        $publishedAt = now();
        $action = (string) $validated['action'];
        $updated = 0;

        DB::transaction(function () use ($pages, $action, $publishedAt, &$updated): void {
            foreach ($pages as $page) {
                if ($action === 'publish') {
                    $page->forceFill([
                        'status' => 'published',
                        'published_at' => $page->published_at ?: $publishedAt,
                    ]);
                } else {
                    $page->forceFill([
                        'status' => 'draft',
                        'published_at' => null,
                    ]);
                }

                if ($page->isDirty(['status', 'published_at'])) {
                    $page->save();
                    $updated++;
                }
            }
        });

        return redirect()
            ->route($collectionId > 0 ? 'admin.cms.docs.collections.pages' : 'admin.cms.docs.index', $collectionId > 0 ? ['collection' => $collectionId] : [])
            ->with('status', trans_choice(
                $action === 'publish'
                    ? 'cms_admin_ui.docs.flash.pages_published'
                    : 'cms_admin_ui.docs.flash.pages_unpublished',
                $updated,
                ['count' => $updated],
            ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectionDashboardPayload(): array
    {
        $collections = CmsDocCollection::query()
            ->withCount('versions')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $counts = DB::table('cms_doc_pages')
            ->join('cms_doc_versions', 'cms_doc_versions.id', '=', 'cms_doc_pages.cms_doc_version_id')
            ->selectRaw('cms_doc_versions.cms_doc_collection_id as collection_id')
            ->selectRaw('count(*) as pages_count')
            ->selectRaw("sum(case when cms_doc_pages.status = 'published' then 1 else 0 end) as published_pages_count")
            ->selectRaw("sum(case when cms_doc_pages.status = 'draft' then 1 else 0 end) as draft_pages_count")
            ->groupBy('cms_doc_versions.cms_doc_collection_id')
            ->get()
            ->keyBy('collection_id');

        return $collections
            ->map(fn (CmsDocCollection $collection): array => array_merge(
                $this->collectionPayload($collection),
                [
                    'versions_count' => (int) $collection->versions_count,
                    'pages_count' => (int) ($counts->get($collection->id)?->pages_count ?? 0),
                    'published_pages_count' => (int) ($counts->get($collection->id)?->published_pages_count ?? 0),
                    'draft_pages_count' => (int) ($counts->get($collection->id)?->draft_pages_count ?? 0),
                ],
            ))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function docsTemplateInfo(): array
    {
        return CmsTemplate::query()
            ->with('layout:id,name,locale')
            ->where('module_key', 'docs')
            ->where('template_key', 'docs.detail')
            ->orderBy('locale')
            ->orderBy('template_key')
            ->get(['id', 'name', 'locale', 'layout_id', 'template_key', 'is_default', 'is_active'])
            ->map(fn (CmsTemplate $template): array => [
                'id' => (int) $template->id,
                'name' => (string) $template->name,
                'locale' => (string) $template->locale,
                'template_key' => (string) $template->template_key,
                'is_default' => (bool) $template->is_default,
                'is_active' => (bool) $template->is_active,
                'layout' => $template->layout ? [
                    'id' => (int) $template->layout->id,
                    'name' => (string) $template->layout->name,
                    'locale' => (string) $template->layout->locale,
                ] : null,
                'edit_url' => route('admin.cms.templates.edit', ['id' => $template->id]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function collectionPayload(CmsDocCollection $collection): array
    {
        return [
            'id' => (int) $collection->id,
            'name' => (string) $collection->name,
            'slug' => (string) $collection->slug,
            'description' => $collection->description,
            'is_active' => (bool) $collection->is_active,
            'sort_order' => (int) $collection->sort_order,
            'preview_url' => route('cms.public.docs.collection', [
                'locale' => $this->languageSettings->defaultLocale(),
                'collection' => $collection->slug,
            ]),
            'created_at' => $collection->created_at?->toISOString(),
            'updated_at' => $collection->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function collectionOptions(): array
    {
        return CmsDocCollection::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CmsDocCollection $collection): array => ['id' => (int) $collection->id, 'name' => (string) $collection->name])
            ->all();
    }

    private function assertDocsModuleInstalled(): void
    {
        abort_unless(
            CmsModule::query()->where('key', 'docs')->where('status', 'active')->exists(),
            404,
        );
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function versionOptions(int $collectionId = 0): array
    {
        return CmsDocVersion::query()
            ->with('collection:id,name')
            ->when($collectionId > 0, fn ($query) => $query->where('cms_doc_collection_id', $collectionId))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CmsDocVersion $version): array => [
                'id' => (int) $version->id,
                'label' => trim(($version->collection?->name ? $version->collection->name.' - ' : '').$version->label),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, title: string}>
     */
    private function parentOptions(CmsDocPage $page): array
    {
        return CmsDocPage::query()
            ->when($page->exists, fn ($query) => $query->whereKeyNot($page->id))
            ->when($page->exists, fn ($query) => $query->where('cms_doc_version_id', $page->cms_doc_version_id)->where('locale', $page->locale))
            ->orderBy('path')
            ->get(['id', 'title', 'path'])
            ->map(fn (CmsDocPage $item): array => ['id' => (int) $item->id, 'title' => $item->path.' - '.$item->title])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function translationPayload(CmsDocPage $page): array
    {
        if (blank($page->translation_key)) {
            return [];
        }

        return CmsDocPage::query()
            ->where('translation_key', $page->translation_key)
            ->orderBy('locale')
            ->get(['id', 'locale', 'title', 'status', 'settings'])
            ->map(fn (CmsDocPage $translation): array => [
                'id' => (int) $translation->id,
                'locale' => (string) $translation->locale,
                'title' => (string) $translation->title,
                'status' => (string) $translation->status,
                'updated_at' => $translation->updated_at?->toIso8601String(),
                'is_current' => $translation->is($page),
                'is_pending' => ($translation->settings['translation_source'] ?? null) === 'ai'
                    && ($translation->settings['translation_review_status'] ?? null) === 'pending',
                'edit_url' => route('admin.cms.docs.pages.edit', ['page' => $translation->id]),
            ])
            ->all();
    }

    /**
     * @return array<int, array{locale: string, name: string, native_name: string}>
     */
    private function missingLanguagePayload(CmsDocPage $page): array
    {
        $existingLocales = blank($page->translation_key)
            ? collect([(string) $page->locale])
            : CmsDocPage::query()
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
     * @return array<int, array{id: int, label: string}>
     */
    private function mediaOptions(): array
    {
        return CmsMediaAsset::query()
            ->where('visibility', 'public')
            ->where('disk', 'public')
            ->orderByDesc('id')
            ->limit(250)
            ->get(['id', 'alt_text', 'original_filename', 'filename', 'path'])
            ->map(fn (CmsMediaAsset $asset): array => [
                'id' => (int) $asset->id,
                'label' => (string) ($asset->alt_text ?: $asset->original_filename ?: $asset->filename ?: $asset->path ?: '#'.$asset->id),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function revisionSnapshot(CmsDocPage $page): array
    {
        return $page->only([
            'cms_doc_version_id',
            'parent_id',
            'title',
            'slug',
            'path',
            'locale',
            'status',
            'body_format',
            'body',
            'seo_title',
            'seo_description',
            'noindex',
            'sort_order',
            'settings',
        ]);
    }
}
