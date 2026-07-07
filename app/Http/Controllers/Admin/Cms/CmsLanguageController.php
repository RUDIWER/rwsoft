<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\CopySystemCountryFlagToTenantMediaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\CopySystemCountryFlagRequest;
use App\Http\Requests\Admin\Cms\ReorderCmsLanguagesRequest;
use App\Http\Requests\Admin\Cms\StoreCmsLanguageRequest;
use App\Models\Cms\CmsLanguage;
use App\Models\Cms\CmsMediaFolder;
use App\Support\Audit\AuditLogger;
use App\Support\Cms\CmsCountryFlagCatalog;
use App\Support\Cms\CmsMediaLibraryPayload;
use App\Support\PublicSite\CmsPublicTextCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CmsLanguageController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CmsPublicTextCache $publicTextCache,
        private readonly CmsMediaLibraryPayload $mediaLibraryPayload,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Cms/Languages/Index', [
            'languages' => CmsLanguage::query()
                ->with('flagMediaAsset:id,disk,path,filename,original_filename,visibility,width,height,alt_text,caption,metadata')
                ->orderBy('sort_order')
                ->orderBy('locale')
                ->get(['id', 'locale', 'name', 'native_name', 'flag_media_asset_id', 'direction', 'is_active', 'sort_order', 'updated_at'])
                ->map(fn (CmsLanguage $language): array => $this->languagePayload($language))
                ->values(),
        ]);
    }

    public function create(): Response
    {
        return $this->edit(0);
    }

    public function edit(int $id): Response
    {
        $language = $id > 0
            ? CmsLanguage::query()->findOrFail($id)
            : null;

        return Inertia::render('Admin/Cms/Languages/Edit', [
            'language' => $language ? $this->languagePayload($language) : null,
            'mediaOptions' => $this->mediaLibraryPayload->assets(),
            'systemCountryFlags' => $this->systemCountryFlagPayload(),
            'defaultFlagFolderId' => $this->defaultFlagFolderId(),
            'directionOptions' => [
                ['value' => 'ltr', 'label' => __('cms_admin_ui.languages.form.direction_ltr')],
                ['value' => 'rtl', 'label' => __('cms_admin_ui.languages.form.direction_rtl')],
            ],
        ]);
    }

    public function store(StoreCmsLanguageRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();
        $language = $id > 0
            ? CmsLanguage::query()->findOrFail($id)
            : new CmsLanguage;
        $isCreate = ! $language->exists;

        if (! $language->exists) {
            $language->sort_order = ((int) CmsLanguage::query()->max('sort_order')) + 10;
        }

        $language->fill(array_merge(
            Arr::only($validated, ['locale', 'name', 'native_name', 'flag_media_asset_id', 'direction']),
            ['is_active' => (bool) ($validated['is_active'] ?? false)],
        ));
        $language->save();

        $this->publicTextCache->flush();

        $this->auditLogger->success(
            action: $isCreate ? 'cms.language.create' : 'cms.language.update',
            module: 'cms',
            subjectType: 'cms_language',
            subjectKey: (string) $language->id,
            message: __('cms_admin_ui.flash.saved.language'),
            meta: ['locale' => (string) $language->locale],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.languages.edit', ['id' => $language->id])
            ->with('status', __('cms_admin_ui.flash.saved.language'));
    }

    public function reorder(ReorderCmsLanguagesRequest $request): RedirectResponse
    {
        $languageIds = collect($request->validated()['languages'])
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        DB::transaction(function () use ($languageIds): void {
            $languageIds->each(function (int $languageId, int $index): void {
                CmsLanguage::query()
                    ->whereKey($languageId)
                    ->update(['sort_order' => ($index + 1) * 10]);
            });
        });

        $this->publicTextCache->flush();

        $this->auditLogger->success(
            action: 'cms.language.reorder',
            module: 'cms',
            subjectType: 'cms_language',
            subjectKey: 'order',
            message: __('cms_admin_ui.flash.saved.language_order'),
            meta: ['language_ids' => $languageIds->all()],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.languages.index');
    }

    public function previewSystemFlag(string $code, CmsCountryFlagCatalog $catalog): HttpResponse
    {
        $svg = $catalog->svg($code);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function copySystemFlag(
        CopySystemCountryFlagRequest $request,
        CopySystemCountryFlagToTenantMediaAction $copySystemCountryFlagToTenantMedia,
    ): JsonResponse {
        $asset = $copySystemCountryFlagToTenantMedia
            ->handle((string) $request->validated('country_code'), $request->user()?->id)
            ->loadMissing(['folder:id,name', 'translations']);

        return response()->json([
            'asset' => $this->mediaLibraryPayload->asset($asset),
            'folders' => $this->mediaLibraryPayload->folders(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function languagePayload(CmsLanguage $language): array
    {
        return [
            'id' => $language->id,
            'locale' => $language->locale,
            'name' => $language->name,
            'native_name' => $language->native_name,
            'flag_media_asset_id' => $language->flag_media_asset_id,
            'flag' => $this->flagPayload($language),
            'direction' => $language->direction,
            'is_active' => (bool) $language->is_active,
            'sort_order' => $language->sort_order,
            'created_at' => $language->created_at?->toJSON(),
            'updated_at' => $language->updated_at?->toJSON(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function flagPayload(CmsLanguage $language): ?array
    {
        if (! $language->relationLoaded('flagMediaAsset')) {
            $language->load('flagMediaAsset');
        }

        return $language->flagMediaAsset
            ? $this->mediaLibraryPayload->asset($language->flagMediaAsset)
            : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function systemCountryFlagPayload(): array
    {
        return collect(app(CmsCountryFlagCatalog::class)->all())
            ->map(fn (array $country): array => array_merge($country, [
                'preview_url' => route('admin.cms.country-flags.preview', ['code' => $country['code']]),
            ]))
            ->values()
            ->all();
    }

    private function defaultFlagFolderId(): ?int
    {
        $folder = CmsMediaFolder::query()
            ->whereNull('parent_id')
            ->where('slug', (string) config('cms_country_flags.tenant_media.root_folder_slug', 'countries'))
            ->first(['id']);

        return $folder instanceof CmsMediaFolder ? (int) $folder->id : null;
    }
}
