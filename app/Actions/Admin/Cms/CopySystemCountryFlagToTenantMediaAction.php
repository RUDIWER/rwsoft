<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsMediaFolder;
use App\Support\Cms\CmsCountryFlagCatalog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CopySystemCountryFlagToTenantMediaAction
{
    public function __construct(private readonly CmsCountryFlagCatalog $catalog) {}

    public function handle(string $countryCode, ?int $userId = null): CmsMediaAsset
    {
        $country = $this->catalog->findOrFail($countryCode);
        $svg = $this->catalog->svg((string) $country['code']);
        $hash = hash('sha256', $svg);

        $existing = CmsMediaAsset::query()
            ->where('metadata->source', 'system_country_flags')
            ->where('metadata->country_code', (string) $country['code'])
            ->first();

        if ($existing instanceof CmsMediaAsset) {
            return $existing;
        }

        return DB::transaction(function () use ($country, $hash, $svg, $userId): CmsMediaAsset {
            $folder = $this->ensureCountryFolder((string) $country['continent']);
            $siteId = TenantContext::siteId();
            $filename = 'flag-'.$country['code'].'.svg';

            $asset = CmsMediaAsset::query()->create([
                'folder_id' => $folder->id,
                'uploaded_by' => $userId,
                'disk' => (string) config('cms_country_flags.tenant_media.disk', 'public'),
                'visibility' => 'public',
                'path' => 'pending/system-country-flag-'.$country['code'],
                'filename' => $filename,
                'original_filename' => $this->originalFilename((string) $country['name']),
                'mime_type' => 'image/svg+xml',
                'extension' => 'svg',
                'size_bytes' => strlen($svg),
                'width' => 640,
                'height' => 480,
                'hash' => $hash,
                'alt_text' => $country['name'].' flag',
                'caption' => null,
                'focal_point' => null,
                'metadata' => [
                    'source' => 'system_country_flags',
                    'source_version' => (string) config('cms_country_flags.source.version', ''),
                    'country_code' => (string) $country['code'],
                    'country_name' => (string) $country['name'],
                    'continent' => (string) $country['continent'],
                    'optimization' => [
                        'status' => 'skipped',
                        'reason' => 'trusted_svg_source',
                    ],
                    'variants' => [],
                ],
                'sort_order' => ((int) (CmsMediaAsset::query()->max('sort_order') ?? 0)) + 1,
            ]);

            $path = $this->assetDirectory((int) $asset->id, $siteId).'/original/'.$filename;
            Storage::disk((string) $asset->disk)->put($path, $svg, ['visibility' => 'public']);

            $asset->forceFill(['path' => $path])->save();

            return $asset->refresh();
        });
    }

    public function ensureRootFolder(): CmsMediaFolder
    {
        return $this->ensureFolder(
            null,
            (string) config('cms_country_flags.tenant_media.root_folder_name', 'Countries'),
            (string) config('cms_country_flags.tenant_media.root_folder_slug', 'countries'),
        );
    }

    private function ensureCountryFolder(string $continent): CmsMediaFolder
    {
        $root = $this->ensureRootFolder();

        return $this->ensureFolder((int) $root->id, $continent, Str::slug($continent) ?: 'other');
    }

    private function ensureFolder(?int $parentId, string $name, string $slug): CmsMediaFolder
    {
        $folder = CmsMediaFolder::query()
            ->where('parent_id', $parentId)
            ->where('slug', $slug)
            ->first();

        if ($folder instanceof CmsMediaFolder) {
            return $folder;
        }

        return CmsMediaFolder::query()->create([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'sort_order' => ((int) CmsMediaFolder::query()->where('parent_id', $parentId)->max('sort_order')) + 1,
            'settings' => [
                'source' => 'system_country_flags',
            ],
        ]);
    }

    private function assetDirectory(int $assetId, ?int $siteId): string
    {
        $root = trim((string) config('cms_country_flags.tenant_media.asset_directory', 'cms/media'), '/');

        return $root.'/site-'.($siteId ?: 0).'/assets/'.$assetId;
    }

    private function originalFilename(string $countryName): string
    {
        return (Str::slug($countryName) ?: 'country').'.svg';
    }
}
