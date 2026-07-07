<?php

namespace App\Support\Cms;

use App\Models\Cms\CmsDownloadAccessRule;
use App\Models\Cms\CmsDownloadAsset;
use App\Models\Cms\CmsDownloadFolder;
use App\Models\Cms\CmsDownloadGroup;
use App\Models\PublicSite\SiteUser;
use App\Support\PublicSite\CmsLanguageSettings;

class CmsDownloadLibraryPayload
{
    public function __construct(private readonly CmsLanguageSettings $languageSettings) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function assets(): array
    {
        return CmsDownloadAsset::query()
            ->with(['folder:id,name', 'translations'])
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CmsDownloadAsset $asset): array => $this->asset($asset))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function folders(): array
    {
        return CmsDownloadFolder::query()
            ->with('accessRules')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug', 'access_mode', 'password_hash', 'password_expires_minutes', 'sort_order'])
            ->map(fn (CmsDownloadFolder $folder): array => $this->folder($folder))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function groups(): array
    {
        return CmsDownloadGroup::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (CmsDownloadGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function siteUsers(): array
    {
        return SiteUser::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (SiteUser $siteUser): array => [
                'id' => $siteUser->id,
                'name' => $siteUser->name,
                'email' => $siteUser->email,
                'label' => trim($siteUser->name.' <'.$siteUser->email.'>'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function asset(CmsDownloadAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'folder_id' => $asset->folder_id,
            'folder_name' => $asset->folder?->name,
            'title' => $this->assetTitle($asset),
            'description' => $asset->description,
            'download_url' => route('cms.downloads.download', ['download' => $asset->id, 'filename' => $asset->filename], false),
            'filename' => $asset->filename,
            'original_filename' => $asset->original_filename,
            'mime_type' => $asset->mime_type,
            'extension' => $asset->extension,
            'access_mode' => $asset->access_mode,
            'published_at' => optional($asset->published_at)->toDateTimeString(),
            'expires_at' => optional($asset->expires_at)->toDateTimeString(),
            'size_bytes' => $asset->size_bytes,
            'size_kb' => round(((int) $asset->size_bytes) / 1024, 1),
            'metadata' => is_array($asset->metadata) ? $asset->metadata : [],
            'translations' => $this->downloadTranslations($asset),
            'sort_order' => $asset->sort_order,
            'created_at' => optional($asset->created_at)->toDateTimeString(),
            'updated_at' => optional($asset->updated_at)->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function folder(CmsDownloadFolder $folder): array
    {
        return [
            'id' => $folder->id,
            'parent_id' => $folder->parent_id,
            'name' => $folder->name,
            'slug' => $folder->slug,
            'access_mode' => $folder->access_mode,
            'has_password' => filled($folder->password_hash),
            'password_expires_minutes' => $folder->password_expires_minutes,
            'access_rules' => $this->accessRules($folder),
            'sort_order' => $folder->sort_order,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accessRules(CmsDownloadFolder $folder): array
    {
        $rules = $folder->relationLoaded('accessRules')
            ? $folder->accessRules
            : $folder->accessRules()->get();

        return $rules
            ->map(fn (CmsDownloadAccessRule $rule): array => [
                'rule_type' => $rule->rule_type,
                'site_user_id' => $rule->site_user_id,
                'cms_download_group_id' => $rule->cms_download_group_id,
                'profile_field_key' => $rule->profile_field_key,
                'operator' => $rule->operator,
                'value' => $rule->value,
            ])
            ->values()
            ->all();
    }

    private function assetTitle(CmsDownloadAsset $asset): string
    {
        return $asset->title
            ?: $asset->original_filename
            ?: $asset->filename
            ?: $asset->path;
    }

    /**
     * @return array<string, array{title: ?string, description: ?string}>
     */
    private function downloadTranslations(CmsDownloadAsset $asset): array
    {
        $translations = $asset->relationLoaded('translations')
            ? $asset->translations
            : $asset->translations()->get();
        $translations = $translations->keyBy('locale');
        $payload = [];

        foreach ($this->languageSettings->languages(true) as $language) {
            $locale = (string) $language['locale'];
            $translation = $translations->get($locale);

            $payload[$locale] = [
                'title' => $translation?->title,
                'description' => $translation?->description,
            ];
        }

        return $payload;
    }
}
