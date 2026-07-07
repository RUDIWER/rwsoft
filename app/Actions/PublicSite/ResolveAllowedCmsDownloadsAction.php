<?php

namespace App\Actions\PublicSite;

use App\Models\Cms\CmsDownloadAccessRule;
use App\Models\Cms\CmsDownloadAsset;
use App\Models\Cms\CmsDownloadFolder;
use App\Models\PublicSite\SiteUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ResolveAllowedCmsDownloadsAction
{
    public const SESSION_UNLOCKS_KEY = 'cms_download_folder_unlocks';

    /**
     * @param  array<string, mixed>  $options
     * @return Collection<int, CmsDownloadAsset>
     */
    public function assets(Request $request, array $options = []): Collection
    {
        $query = CmsDownloadAsset::query()
            ->with(['folder.parent.parent', 'translations', 'accessRules', 'folder.accessRules'])
            ->whereNull('deleted_at')
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        $this->applySource($query, $options);

        return $query
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (CmsDownloadAsset $asset): bool => $this->canDownload($asset, $request, $options))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function canDownload(CmsDownloadAsset $asset, Request $request, array $options = []): bool
    {
        if ($asset->deleted_at !== null) {
            return false;
        }

        if ($asset->published_at !== null && $asset->published_at->isFuture()) {
            return false;
        }

        if ($asset->expires_at !== null && $asset->expires_at->isPast()) {
            return false;
        }

        $siteUser = $request->user('site_user');
        $mode = $this->effectiveAccessMode($asset);

        if ($mode === 'public') {
            return true;
        }

        if ($mode === 'authenticated') {
            return $siteUser instanceof SiteUser && $siteUser->isActive();
        }

        if ($mode === 'password') {
            return $this->folderIsUnlocked($asset->folder, $request);
        }

        if ($mode === 'restricted') {
            return $siteUser instanceof SiteUser
                && $siteUser->isActive()
                && $this->matchesRules($asset, $siteUser);
        }

        return $siteUser instanceof SiteUser && $siteUser->isActive();
    }

    public function folderIsUnlocked(?CmsDownloadFolder $folder, Request $request): bool
    {
        $current = $folder;

        while ($current instanceof CmsDownloadFolder) {
            if (filled($current->password_hash) || $current->access_mode === 'password') {
                return $this->sessionFolderUnlockIsValid($current, $request);
            }

            $current = $current->parent;
        }

        return false;
    }

    public function markFolderUnlocked(CmsDownloadFolder $folder, Request $request): void
    {
        $unlocks = $request->session()->get(self::SESSION_UNLOCKS_KEY, []);
        $minutes = (int) ($folder->password_expires_minutes ?: config('cms_downloads.folder_password_expires_minutes', 120));
        $unlocks[(string) $folder->id] = now()->addMinutes($minutes)->timestamp;

        $request->session()->put(self::SESSION_UNLOCKS_KEY, $unlocks);
    }

    private function applySource(Builder $query, array $options): void
    {
        $sourceMode = (string) ($options['source_mode'] ?? 'folders');

        if ($sourceMode === 'manual') {
            $ids = $this->integerList($options['download_asset_ids'] ?? []);

            if ($ids !== []) {
                $query->whereIn('id', $ids);
            }

            return;
        }

        $folderIds = $this->integerList($options['folder_ids'] ?? []);

        if ($sourceMode === 'current_page_context') {
            $folderId = (int) ($options['context_folder_id'] ?? 0);
            $folderIds = $folderId > 0 ? [$folderId] : [];
        }

        if ($folderIds === []) {
            return;
        }

        if ((bool) ($options['include_subfolders'] ?? true)) {
            $folderIds = $this->descendantFolderIds($folderIds);
        }

        $query->whereIn('folder_id', $folderIds);
    }

    private function effectiveAccessMode(CmsDownloadAsset $asset): string
    {
        $assetMode = (string) ($asset->access_mode ?: 'inherit');

        if ($assetMode !== 'inherit') {
            return $assetMode;
        }

        $folder = $asset->folder;

        while ($folder instanceof CmsDownloadFolder) {
            if (filled($folder->password_hash)) {
                return 'password';
            }

            $folderMode = (string) ($folder->access_mode ?: 'inherit');

            if ($folderMode !== 'inherit') {
                return $folderMode;
            }

            $folder = $folder->parent;
        }

        return (string) config('cms_downloads.default_access_mode', 'authenticated');
    }

    private function matchesRules(CmsDownloadAsset $asset, SiteUser $siteUser): bool
    {
        $rules = collect($asset->relationLoaded('accessRules') ? $asset->accessRules : $asset->accessRules()->get())
            ->merge($asset->folder instanceof CmsDownloadFolder
                ? collect($asset->folder->relationLoaded('accessRules') ? $asset->folder->accessRules : $asset->folder->accessRules()->get())
                : collect())
            ->filter(fn (CmsDownloadAccessRule $rule): bool => (bool) $rule->is_active);

        if ($rules->isEmpty()) {
            return false;
        }

        return $rules->contains(fn (CmsDownloadAccessRule $rule): bool => $this->ruleMatches($rule, $siteUser));
    }

    private function ruleMatches(CmsDownloadAccessRule $rule, SiteUser $siteUser): bool
    {
        return match ($rule->rule_type) {
            'site_user' => (int) $rule->site_user_id === (int) $siteUser->id,
            'download_group' => $this->siteUserIsInGroup($siteUser, (int) $rule->cms_download_group_id),
            'profile_field' => $this->profileFieldMatches($rule, $siteUser),
            default => false,
        };
    }

    private function siteUserIsInGroup(SiteUser $siteUser, int $groupId): bool
    {
        if ($groupId <= 0) {
            return false;
        }

        return $siteUser->newQuery()
            ->whereKey($siteUser->id)
            ->whereHas('downloadGroups', fn (Builder $query) => $query->whereKey($groupId)->where('is_active', true))
            ->exists();
    }

    private function profileFieldMatches(CmsDownloadAccessRule $rule, SiteUser $siteUser): bool
    {
        $fieldKey = trim((string) $rule->profile_field_key);

        if ($fieldKey === '') {
            return false;
        }

        $value = (string) optional($siteUser->profileFieldValues()->where('profile_field_key', $fieldKey)->first())->value;
        $expected = $rule->value;
        $operator = (string) ($rule->operator ?: 'equals');

        return match ($operator) {
            'filled' => trim($value) !== '',
            'not_equals' => $value !== (string) Arr::first((array) $expected),
            'in' => in_array($value, array_map('strval', (array) $expected), true),
            'not_in' => ! in_array($value, array_map('strval', (array) $expected), true),
            'contains' => str_contains($value, (string) Arr::first((array) $expected)),
            default => $value === (string) Arr::first((array) $expected),
        };
    }

    private function sessionFolderUnlockIsValid(CmsDownloadFolder $folder, Request $request): bool
    {
        $unlocks = $request->session()->get(self::SESSION_UNLOCKS_KEY, []);
        $expiresAt = (int) ($unlocks[(string) $folder->id] ?? 0);

        return $expiresAt > now()->timestamp;
    }

    /**
     * @param  array<int, int>  $folderIds
     * @return array<int, int>
     */
    private function descendantFolderIds(array $folderIds): array
    {
        $folders = CmsDownloadFolder::query()->get(['id', 'parent_id']);
        $ids = array_values(array_unique($folderIds));
        $frontier = $ids;

        while ($frontier !== []) {
            $children = $folders
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
            $frontier = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique(array_merge($ids, $frontier)));
        }

        return $ids;
    }

    /**
     * @return array<int, int>
     */
    private function integerList(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }
}
