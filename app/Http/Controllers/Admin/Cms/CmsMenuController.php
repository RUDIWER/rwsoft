<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\CreateCmsMenuItemTranslationAction;
use App\Actions\Admin\Cms\Revisions\BuildCmsMenuRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\CmsRevisionPayloadAction;
use App\Actions\Admin\Cms\Revisions\CreateCmsRevisionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\StoreCmsMenuItemRequest;
use App\Http\Requests\Admin\Cms\StoreCmsMenuItemTranslationRequest;
use App\Http\Requests\Admin\Cms\StoreCmsMenuRequest;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsTag;
use App\Support\Audit\AuditLogger;
use App\Support\PublicSite\CmsLanguageSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CmsMenuController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CmsLanguageSettings $languageSettings,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Cms/Menus/Index', [
            'menus' => CmsMenu::query()
                ->with(['translations'])
                ->withCount(['items as item_groups_count' => function ($query): void {
                    $query->select(DB::raw("count(distinct coalesce(translation_key, concat('item-', id)))"));
                }])
                ->orderBy('title')
                ->get(['id', 'title', 'placements', 'is_active', 'updated_at'])
                ->map(fn (CmsMenu $menu): array => array_merge($this->menuPayload($menu), [
                    'items_count' => $menu->item_groups_count,
                    'updated_at' => $menu->updated_at,
                ])),
            'menuPlacementOptions' => $this->menuPlacementOptions(),
        ]);
    }

    public function create(): Response
    {
        return $this->edit(0, request());
    }

    public function edit(int $id, Request $request): Response
    {
        $menu = $id > 0
            ? CmsMenu::query()->with(['items.page', 'items.post', 'translations'])->findOrFail($id)
            : null;
        $editingItem = null;

        if ($menu instanceof CmsMenu && $request->integer('item') > 0) {
            $editingItem = $menu->items()->with(['page', 'post'])->findOrFail($request->integer('item'));
        }

        return Inertia::render('Admin/Cms/Menus/Edit', [
            'menu' => $menu ? $this->menuPayload($menu) : null,
            'revisions' => $menu ? app(CmsRevisionPayloadAction::class)->handle($menu) : [],
            'items' => $menu ? $this->flatItemTree($this->menuItemListItems($menu->items)) : [],
            'parentItemOptions' => $menu ? $this->flatItemTree($menu->items) : [],
            'editingItem' => $editingItem ? $this->itemPayload($editingItem) : null,
            'itemTranslations' => $editingItem ? $this->itemTranslationPayload($editingItem) : [],
            'itemMissingLanguages' => $editingItem ? $this->itemMissingLanguagePayload($editingItem) : [],
            'activeLanguages' => $this->languageSettings->languages(true),
            'defaultLocale' => $this->languageSettings->defaultLocale(),
            'pageOptions' => CmsPage::query()
                ->whereNotIn('id', CmsCategory::query()
                    ->select('landing_page_id')
                    ->whereNotNull('landing_page_id'))
                ->whereNotIn('id', CmsTag::query()
                    ->select('landing_page_id')
                    ->whereNotNull('landing_page_id'))
                ->orderBy('locale')
                ->orderBy('title')
                ->get(['id', 'title', 'slug', 'locale', 'translation_key', 'status', 'published_at'])
                ->map(fn (CmsPage $page): array => $this->targetOptionPayload($page)),
            'categoryOptions' => CmsCategory::query()
                ->with('landingPage:id,title,slug,locale,translation_key,status,published_at')
                ->whereNotNull('landing_page_id')
                ->orderBy('locale')
                ->orderBy('title')
                ->get(['id', 'title', 'slug', 'locale', 'landing_page_id'])
                ->map(fn (CmsCategory $category): array => $this->categoryOptionPayload($category))
                ->filter(fn (array $category): bool => (int) $category['id'] > 0)
                ->values(),
            'postOptions' => CmsPost::query()
                ->orderBy('locale')
                ->orderBy('title')
                ->get(['id', 'title', 'slug', 'locale', 'translation_key', 'status', 'published_at'])
                ->map(fn (CmsPost $post): array => $this->targetOptionPayload($post)),
            'typeOptions' => $this->typeOptions(),
            'targetOptions' => $this->targetOptions(),
            'menuPlacementOptions' => $this->menuPlacementOptions(),
        ]);
    }

    public function store(
        StoreCmsMenuRequest $request,
        int $id,
        BuildCmsMenuRevisionSnapshotAction $buildRevisionSnapshot,
        CreateCmsRevisionAction $createRevision,
    ): RedirectResponse {
        $validated = $request->validated();
        $menu = $id > 0 ? CmsMenu::query()->findOrFail($id) : new CmsMenu;
        $isCreate = ! $menu->exists;
        $defaultLocale = $this->languageSettings->defaultLocale();
        $defaultTranslation = $this->defaultTranslation((array) ($validated['translations'] ?? []), $defaultLocale);

        DB::transaction(function () use ($menu, $validated, $defaultLocale, $defaultTranslation, $request, $buildRevisionSnapshot, $createRevision): void {
            $menu->fill([
                'title' => $defaultTranslation['title'] ?? $validated['title'] ?? '',
                'placements' => array_values(array_unique((array) ($validated['placements'] ?? []))),
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]);
            $menu->save();
            $this->saveMenuTranslations($menu, (array) ($validated['translations'] ?? []), $defaultLocale);

            $createRevision->handle(
                $menu,
                'full',
                $buildRevisionSnapshot->handle($menu),
                $request->user()?->id,
                __('cms_admin_ui.revisions.auto_revision_title'),
                metadata: [
                    'change_type' => $menu->wasRecentlyCreated ? 'create' : 'update',
                    'menu_items_count' => $menu->items()->count(),
                ],
            );
        });

        $this->auditLogger->success(
            action: $isCreate ? 'cms.menu.create' : 'cms.menu.update',
            module: 'cms',
            subjectType: 'cms_menu',
            subjectKey: (string) $menu->id,
            message: __('cms_admin_ui.flash.saved.menu'),
            meta: ['title' => (string) $menu->title],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.menus.edit', ['id' => $menu->id])
            ->with('status', __('cms_admin_ui.flash.saved.menu'));
    }

    public function storeItem(
        StoreCmsMenuItemRequest $request,
        int $menu,
        int $item,
        CreateCmsMenuItemTranslationAction $createTranslation,
    ): RedirectResponse {
        $validated = $request->validated();
        $menuModel = CmsMenu::query()->findOrFail($menu);
        $menuItem = $item > 0
            ? $menuModel->items()->findOrFail($item)
            : new CmsMenuItem(['cms_menu_id' => $menuModel->id]);
        $isCreate = ! $menuItem->exists;

        if ($menuItem->exists) {
            $validated['locale'] = $menuItem->locale;
            $validated['translation_key'] = $menuItem->translation_key;
        }

        $parentId = $validated['parent_id'] ?? null;

        if ($parentId && ! $menuModel->items()->whereKey($parentId)->exists()) {
            return back()->withErrors(['parent_id' => __('cms_admin_ui.flash.parent_not_in_menu')])->withInput();
        }

        if ($parentId && ! $menuModel->items()->whereKey($parentId)->where('locale', $validated['locale'])->exists()) {
            return back()->withErrors(['parent_id' => __('cms_admin_ui.flash.parent_wrong_locale')])->withInput();
        }

        if ($menuItem->exists && $parentId && $this->wouldCreateCycle($menuItem, (int) $parentId)) {
            return back()->withErrors(['parent_id' => __('cms_admin_ui.flash.parent_cycle')])->withInput();
        }

        DB::transaction(function () use ($menuItem, $menuModel, $validated, $request): void {
            $menuItem->fill($this->itemData($menuModel, $menuItem, $validated));
            $menuItem->save();

            app(CreateCmsRevisionAction::class)->handle(
                $menuModel,
                'full',
                app(BuildCmsMenuRevisionSnapshotAction::class)->handle($menuModel),
                $request->user()?->id,
                __('cms_admin_ui.revisions.auto_revision_title'),
                metadata: [
                    'change_type' => $menuItem->wasRecentlyCreated ? 'item_create' : 'item_update',
                    'menu_items_count' => $menuModel->items()->count(),
                ],
            );
        });

        if ($isCreate && in_array($menuItem->type, ['page', 'category', 'post'], true)) {
            $this->createAvailableItemTranslations($menuItem, $createTranslation);
        }

        $this->auditLogger->success(
            action: $menuItem->wasRecentlyCreated ? 'cms.menu-item.create' : 'cms.menu-item.update',
            module: 'cms',
            subjectType: 'cms_menu_item',
            subjectKey: (string) $menuItem->id,
            message: __('cms_admin_ui.flash.saved.menu_item'),
            meta: ['label' => (string) $menuItem->label, 'menu_id' => $menuModel->id],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.menus.edit', ['id' => $menuModel->id, 'item' => $menuItem->id])
            ->with('status', __('cms_admin_ui.flash.saved.menu_item'));
    }

    public function storeItemTranslation(
        StoreCmsMenuItemTranslationRequest $request,
        int $menu,
        int $item,
        CreateCmsMenuItemTranslationAction $createTranslation,
    ): RedirectResponse {
        $menuModel = CmsMenu::query()->findOrFail($menu);
        $itemModel = $menuModel->items()->findOrFail($item);

        $validated = $request->validated();
        $useAi = (bool) ($validated['use_ai'] ?? false);

        try {
            $translation = $createTranslation->handle(
                sourceItem: $itemModel,
                targetLocale: (string) $validated['target_locale'],
                useAi: $useAi,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $useAi
                ? __('cms_admin_ui.flash.menu_item_translation_failed_ai')
                : __('cms_admin_ui.flash.menu_item_translation_failed'));
        }

        $this->auditLogger->success(
            action: 'cms.menu-item.translation.create',
            module: 'cms',
            subjectType: 'cms_menu_item',
            subjectKey: (string) $translation->id,
            message: __('cms_admin_ui.flash.menu_item_translation_created'),
            meta: [
                'source_menu_item_id' => $itemModel->id,
                'target_menu_item_id' => $translation->id,
                'target_locale' => (string) $translation->locale,
                'use_ai' => $useAi,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.menus.edit', ['id' => $menuModel->id, 'item' => $translation->id])
            ->with('status', $useAi
                ? __('cms_admin_ui.flash.menu_item_translation_created_ai')
                : __('cms_admin_ui.flash.menu_item_translation_created'));
    }

    public function destroyItem(Request $request, int $menu, int $item): RedirectResponse
    {
        $menuModel = CmsMenu::query()->findOrFail($menu);
        $itemModel = $menuModel->items()->findOrFail($item);

        DB::transaction(function () use ($menuModel, $itemModel, $request): void {
            $itemModel->delete();

            app(CreateCmsRevisionAction::class)->handle(
                $menuModel,
                'full',
                app(BuildCmsMenuRevisionSnapshotAction::class)->handle($menuModel),
                $request->user()?->id,
                __('cms_admin_ui.revisions.auto_revision_title'),
                metadata: [
                    'change_type' => 'item_delete',
                    'menu_items_count' => $menuModel->items()->count(),
                ],
            );
        });

        return redirect()
            ->route('admin.cms.menus.edit', ['id' => $menuModel->id])
            ->with('status', __('cms_admin_ui.flash.deleted.menu_item'));
    }

    /**
     * @return array<string, mixed>
     */
    private function menuPayload(CmsMenu $menu): array
    {
        return [
            'id' => $menu->id,
            'title' => $menu->title,
            'placements' => array_values((array) ($menu->placements ?? [])),
            'is_active' => (bool) $menu->is_active,
            'translations' => $this->menuTranslationsPayload($menu),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(CmsMenuItem $item, int $depth = 0): array
    {
        return [
            'id' => $item->id,
            'locale' => $item->locale,
            'translation_key' => $item->translation_key,
            'translated_from_menu_item_id' => $item->translated_from_menu_item_id,
            'parent_id' => $item->parent_id,
            'type' => $item->type,
            'label' => $item->label,
            'url' => $item->url,
            'target' => $item->target,
            'rel' => $item->rel,
            'sort_order' => $item->sort_order,
            'is_active' => (bool) $item->is_active,
            'cms_page_id' => $item->cms_page_id,
            'cms_post_id' => $item->cms_post_id,
            'page_title' => $item->page?->title,
            'page_is_public' => $item->page instanceof CmsPage ? $this->targetIsPublic($item->page) : null,
            'page_public_status_label' => $item->page instanceof CmsPage ? $this->targetPublicStatusLabel($item->page) : null,
            'category_title' => $item->type === 'category' ? $this->categoryTitleForPageId($item->cms_page_id) : null,
            'post_title' => $item->post?->title,
            'post_is_public' => $item->post instanceof CmsPost ? $this->targetIsPublic($item->post) : null,
            'post_public_status_label' => $item->post instanceof CmsPost ? $this->targetPublicStatusLabel($item->post) : null,
            'depth' => $depth,
            'ai_translation_review' => $this->aiTranslationReviewPayload($item->metadata ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{is_pending: bool}
     */
    private function aiTranslationReviewPayload(array $metadata): array
    {
        return [
            'is_pending' => ($metadata['translation_source'] ?? null) === 'ai'
                && ($metadata['translation_review_status'] ?? null) === 'pending',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function targetOptionPayload(CmsPage|CmsPost $target): array
    {
        return [
            'id' => $target->id,
            'title' => $target->title,
            'slug' => $target->slug,
            'locale' => $target->locale,
            'translation_key' => $target->translation_key,
            'status' => $target->status,
            'published_at' => $target->published_at?->toDateTimeString(),
            'is_public' => $this->targetIsPublic($target),
            'public_status_label' => $this->targetPublicStatusLabel($target),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryOptionPayload(CmsCategory $category): array
    {
        $page = $category->landingPage;

        return [
            'id' => $page?->id,
            'category_id' => $category->id,
            'title' => $category->title,
            'slug' => $category->slug,
            'locale' => $category->locale,
            'status' => $page?->status,
            'published_at' => $page?->published_at?->toDateTimeString(),
            'is_public' => $page instanceof CmsPage && $this->targetIsPublic($page),
            'public_status_label' => $page instanceof CmsPage ? $this->targetPublicStatusLabel($page) : 'Geen pagina',
        ];
    }

    private function categoryTitleForPageId(?int $pageId): ?string
    {
        if (! $pageId) {
            return null;
        }

        return CmsCategory::query()
            ->where('landing_page_id', $pageId)
            ->value('title');
    }

    private function targetIsPublic(CmsPage|CmsPost $target): bool
    {
        return $target->status === 'published'
            && ($target->published_at === null || $target->published_at->isPast());
    }

    private function targetPublicStatusLabel(CmsPage|CmsPost $target): string
    {
        if ($target->status === 'draft') {
            return 'Concept';
        }

        if ($target->status === 'archived') {
            return 'Gearchiveerd';
        }

        if ($target->published_at !== null && $target->published_at->isFuture()) {
            return 'Gepland';
        }

        return 'Gepubliceerd';
    }

    /**
     * @param  iterable<int, CmsMenuItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function flatItemTree(iterable $items, ?int $parentId = null, int $depth = 0): array
    {
        $rows = [];

        foreach (collect($items)->where('parent_id', $parentId)->sortBy('sort_order') as $item) {
            $rows[] = $this->itemPayload($item, $depth);
            $rows = array_merge($rows, $this->flatItemTree($items, $item->id, $depth + 1));
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function itemData(CmsMenu $menu, CmsMenuItem $menuItem, array $validated): array
    {
        $type = (string) $validated['type'];
        $target = $this->targetModel($type, $validated);
        $locale = (string) ($validated['locale'] ?? $target?->locale ?? $this->languageSettings->defaultLocale());

        return [
            'cms_menu_id' => $menu->id,
            'locale' => $locale,
            'translation_key' => $menuItem->translation_key ?: ($validated['translation_key'] ?? (string) Str::ulid()),
            'parent_id' => $validated['parent_id'] ?? null,
            'type' => $type,
            'label' => $validated['label'] ?? $target?->title ?? '',
            'url' => in_array($type, ['custom', 'external'], true)
                ? ($validated['url'] ?? null)
                : null,
            'cms_page_id' => in_array($type, ['page', 'category'], true) ? $validated['cms_page_id'] : null,
            'cms_post_id' => $type === 'post' ? $validated['cms_post_id'] : null,
            'target' => blank($validated['target'] ?? null) ? null : $validated['target'],
            'rel' => blank($validated['rel'] ?? null) ? null : $validated['rel'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }

    private function targetModel(string $type, array $validated): CmsPage|CmsPost|null
    {
        if (in_array($type, ['page', 'category'], true) && filled($validated['cms_page_id'] ?? null)) {
            return CmsPage::query()->find((int) $validated['cms_page_id']);
        }

        if ($type === 'post' && filled($validated['cms_post_id'] ?? null)) {
            return CmsPost::query()->find((int) $validated['cms_post_id']);
        }

        return null;
    }

    /**
     * @param  iterable<int, CmsMenuItem>  $items
     * @return Collection<int, CmsMenuItem>
     */
    private function menuItemListItems(iterable $items): Collection
    {
        $defaultLocale = $this->languageSettings->defaultLocale();

        return collect($items)
            ->groupBy(fn (CmsMenuItem $item): string => (string) ($item->translation_key ?: 'item-'.$item->id))
            ->map(fn (Collection $group): CmsMenuItem => $group->firstWhere('translated_from_menu_item_id', null)
                ?? $group->firstWhere('locale', $defaultLocale)
                ?? $group->first())
            ->filter()
            ->sortBy('sort_order')
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemTranslationPayload(CmsMenuItem $item): array
    {
        if (blank($item->translation_key)) {
            return [$this->itemPayload($item) + ['is_current' => true]];
        }

        return CmsMenuItem::query()
            ->with(['page', 'post'])
            ->where('cms_menu_id', $item->cms_menu_id)
            ->where('translation_key', $item->translation_key)
            ->orderBy('locale')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CmsMenuItem $translation): array => $this->itemPayload($translation) + [
                'is_current' => $translation->is($item),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemMissingLanguagePayload(CmsMenuItem $item): array
    {
        $existingLocales = filled($item->translation_key)
            ? CmsMenuItem::query()
                ->where('cms_menu_id', $item->cms_menu_id)
                ->where('translation_key', $item->translation_key)
                ->pluck('locale')
                ->filter()
                ->map(fn (string $locale): string => $locale)
                ->values()
                ->all()
            : array_filter([(string) $item->locale]);

        return collect($this->languageSettings->languages(true))
            ->reject(fn (array $language): bool => in_array((string) $language['locale'], $existingLocales, true))
            ->map(fn (array $language): array => array_merge($language, [
                'can_create' => $this->targetAvailableForLocale($item, (string) $language['locale']),
            ]))
            ->values()
            ->all();
    }

    private function createAvailableItemTranslations(
        CmsMenuItem $sourceItem,
        CreateCmsMenuItemTranslationAction $createTranslation,
    ): void {
        foreach ($this->languageSettings->languages(true) as $language) {
            $locale = (string) $language['locale'];

            if ($locale === (string) $sourceItem->locale || ! $this->targetAvailableForLocale($sourceItem, $locale)) {
                continue;
            }

            try {
                $createTranslation->handle($sourceItem, $locale, false);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function targetAvailableForLocale(CmsMenuItem $item, string $locale): bool
    {
        if (in_array($item->type, ['custom', 'external'], true)) {
            return true;
        }

        if (in_array($item->type, ['page', 'category'], true)) {
            $page = $item->page instanceof CmsPage ? $item->page : CmsPage::query()->find($item->cms_page_id);

            return $page instanceof CmsPage
                && filled($page->translation_key)
                && CmsPage::query()->where('translation_key', $page->translation_key)->where('locale', $locale)->exists();
        }

        if ($item->type === 'post') {
            $post = $item->post instanceof CmsPost ? $item->post : CmsPost::query()->find($item->cms_post_id);

            return $post instanceof CmsPost
                && filled($post->translation_key)
                && CmsPost::query()->where('translation_key', $post->translation_key)->where('locale', $locale)->exists();
        }

        return false;
    }

    private function wouldCreateCycle(CmsMenuItem $item, int $parentId): bool
    {
        if ((int) $item->id === $parentId) {
            return true;
        }

        $current = CmsMenuItem::query()->find($parentId);

        while ($current instanceof CmsMenuItem) {
            if ((int) $current->parent_id === (int) $item->id) {
                return true;
            }

            $current = $current->parent_id
                ? CmsMenuItem::query()->find($current->parent_id)
                : null;
        }

        return false;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return [
            ['value' => 'external', 'label' => __('cms_admin_ui.menus.form.external_url')],
            ['value' => 'page', 'label' => __('cms_admin_ui.menus.form.page')],
            ['value' => 'category', 'label' => __('cms_admin_ui.menus.form.category')],
            ['value' => 'post', 'label' => __('cms_admin_ui.menus.form.post')],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function targetOptions(): array
    {
        return [
            ['value' => '', 'label' => __('cms_admin_ui.menus.form.same_window')],
            ['value' => '_blank', 'label' => __('cms_admin_ui.menus.form.new_window')],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function menuPlacementOptions(): array
    {
        return collect((array) config('cms_menus.placements', []))
            ->map(fn (array $placement, string $key): array => [
                'value' => $key,
                'label' => __('cms_admin_ui.'.(string) ($placement['label_key'] ?? 'menus.placements.'.$key)),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{title: string|null}>
     */
    private function menuTranslationsPayload(CmsMenu $menu): array
    {
        $translations = $menu->relationLoaded('translations')
            ? $menu->translations
            : $menu->translations()->get();
        $payload = [];

        foreach ($this->languageSettings->languages(true) as $language) {
            $locale = (string) $language['locale'];
            $translation = $translations->firstWhere('locale', $locale);
            $payload[$locale] = [
                'title' => $translation?->title ?? ($locale === $this->languageSettings->defaultLocale() ? $menu->title : null),
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed>
     */
    private function defaultTranslation(array $translations, string $defaultLocale): array
    {
        return (array) ($translations[$defaultLocale] ?? []);
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    private function saveMenuTranslations(CmsMenu $menu, array $translations, string $defaultLocale): void
    {
        foreach ($this->languageSettings->languages(true) as $language) {
            $locale = (string) $language['locale'];
            $data = (array) ($translations[$locale] ?? []);
            $title = trim((string) ($data['title'] ?? ''));

            if ($title === '' && $locale !== $defaultLocale) {
                continue;
            }

            $menu->translations()->updateOrCreate(
                ['locale' => $locale],
                ['title' => $title !== '' ? $title : null]
            );
        }
    }
}
