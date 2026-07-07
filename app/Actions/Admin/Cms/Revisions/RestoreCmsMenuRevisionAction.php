<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsRevision;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestoreCmsMenuRevisionAction
{
    public function __construct(
        private readonly BuildCmsMenuRevisionSnapshotAction $buildSnapshot,
        private readonly CreateCmsRevisionAction $createRevision,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CmsMenu $menu, CmsRevision $revision, string $mode, ?int $authorId = null): array
    {
        return DB::transaction(function () use ($menu, $revision, $mode, $authorId): array {
            $lockedMenu = CmsMenu::query()->lockForUpdate()->findOrFail($menu->id);
            $this->assertRevisionMatches($lockedMenu, $revision);

            $snapshot = $revision->snapshot['menu'] ?? [];

            if ($mode === 'full') {
                $this->assertMenuTargetsExist($snapshot['items'] ?? []);
            }

            $this->createRevision->handle(
                $lockedMenu,
                'restore_backup',
                $this->buildSnapshot->handle($lockedMenu),
                $authorId,
                __('cms_admin_ui.revisions.restore_backup_title'),
                forceCreate: true,
            );

            $this->restoreMenuFields($lockedMenu, $snapshot);
            $this->restoreTranslations($lockedMenu, $snapshot['translations'] ?? []);

            $warnings = $mode === 'full'
                ? $this->restoreItems($lockedMenu, $snapshot['items'] ?? [])
                : ['deactivated_items' => 0];

            $this->createRevision->handle(
                $lockedMenu->fresh() ?: $lockedMenu,
                'restore',
                $this->buildSnapshot->handle($lockedMenu->fresh() ?: $lockedMenu),
                $authorId,
                __('cms_admin_ui.revisions.restored_revision_title', ['number' => $revision->revision_number]),
                (int) $revision->id,
                ['restore_mode' => $mode, 'warnings' => $warnings],
            );

            return $warnings;
        });
    }

    private function assertRevisionMatches(CmsMenu $menu, CmsRevision $revision): void
    {
        abort_unless($revision->subject_type === CmsMenu::class && (int) $revision->subject_id === (int) $menu->id, 404);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     *
     * @throws ValidationException
     */
    private function assertMenuTargetsExist(array $items): void
    {
        $pageIds = collect($items)
            ->filter(fn (array $item): bool => in_array($item['type'] ?? null, ['page', 'category'], true))
            ->pluck('cms_page_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $postIds = collect($items)
            ->filter(fn (array $item): bool => ($item['type'] ?? null) === 'post')
            ->pluck('cms_post_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        $missingPages = $pageIds->diff(CmsPage::query()->whereIn('id', $pageIds)->pluck('id')->map(fn (mixed $id): int => (int) $id));
        $missingPosts = $postIds->diff(CmsPost::query()->whereIn('id', $postIds)->pluck('id')->map(fn (mixed $id): int => (int) $id));

        if ($missingPages->isNotEmpty() || $missingPosts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'revision' => __('cms_admin_ui.revisions.menu_restore_missing_targets'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function restoreMenuFields(CmsMenu $menu, array $snapshot): void
    {
        $menu->forceFill(Arr::only($snapshot, [
            'title',
            'placements',
            'is_active',
            'settings',
        ]))->save();
    }

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function restoreTranslations(CmsMenu $menu, array $translations): void
    {
        foreach ($translations as $translation) {
            $locale = (string) ($translation['locale'] ?? '');

            if ($locale === '') {
                continue;
            }

            $menu->translations()->updateOrCreate(
                ['locale' => $locale],
                ['title' => $translation['title'] ?? ''],
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function restoreItems(CmsMenu $menu, array $items): array
    {
        $idMap = [];
        $keptIds = [];

        foreach ($items as $itemSnapshot) {
            $snapshotId = (int) ($itemSnapshot['id'] ?? 0);
            $item = $snapshotId > 0
                ? $menu->items()->whereKey($snapshotId)->first()
                : null;

            if (! $item instanceof CmsMenuItem) {
                $item = new CmsMenuItem(['cms_menu_id' => $menu->id]);
            }

            $item->forceFill(array_merge(
                Arr::only($itemSnapshot, [
                    'locale',
                    'translation_key',
                    'translated_from_menu_item_id',
                    'cms_page_id',
                    'cms_post_id',
                    'type',
                    'label',
                    'url',
                    'target',
                    'rel',
                    'sort_order',
                    'is_active',
                    'metadata',
                ]),
                ['cms_menu_id' => $menu->id, 'parent_id' => null]
            ))->save();

            if ($snapshotId > 0) {
                $idMap[$snapshotId] = (int) $item->id;
            }

            $keptIds[] = (int) $item->id;
        }

        foreach ($items as $itemSnapshot) {
            $snapshotId = (int) ($itemSnapshot['id'] ?? 0);
            $parentSnapshotId = (int) ($itemSnapshot['parent_id'] ?? 0);
            $itemId = $idMap[$snapshotId] ?? null;

            if (! $itemId) {
                continue;
            }

            CmsMenuItem::query()
                ->whereKey($itemId)
                ->update(['parent_id' => $parentSnapshotId > 0 ? ($idMap[$parentSnapshotId] ?? null) : null]);
        }

        $deactivatedItems = $menu->items()
            ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
            ->where('is_active', true)
            ->update(['is_active' => false, 'parent_id' => null]);

        return ['deactivated_items' => (int) $deactivatedItems];
    }
}
