<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use App\Models\User;
use App\Support\Security\TenantAcl;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CmsThemeAssetController extends Controller
{
    public function __construct(private readonly TenantAcl $tenantAcl) {}

    public function active(string $hash): Response
    {
        $theme = CmsTheme::query()
            ->with('activeVersion')
            ->where('is_active', true)
            ->first();

        abort_unless($theme instanceof CmsTheme, 404);
        abort_unless($theme->activeVersion instanceof CmsThemeVersion, 404);
        abort_unless(hash_equals($theme->activeVersion->version_hash, $hash), 404);

        return $this->cssResponse($theme->activeVersion, true);
    }

    public function preview(Request $request, int $theme, string $hash): Response
    {
        abort_unless($this->canPreview($request), 403);

        $themeModel = CmsTheme::query()->findOrFail($theme);
        $version = $themeModel->versions()
            ->where('version_hash', $hash)
            ->first();

        abort_unless($version instanceof CmsThemeVersion, 404);

        return $this->cssResponse($version, false);
    }

    private function cssResponse(CmsThemeVersion $version, bool $immutable): Response
    {
        $disk = Storage::disk((string) config('cms_themes.storage_disk', 'local'));

        abort_unless($disk->exists($version->minified_css_path), 404);

        $css = (string) $disk->get($version->minified_css_path);
        $headers = [
            'Content-Type' => 'text/css; charset=UTF-8',
            'ETag' => '"'.$version->version_hash.'"',
        ];

        $headers['Cache-Control'] = $immutable
            ? 'public, max-age=31536000, immutable'
            : 'private, no-store, max-age=0';

        return response($css, 200, $headers);
    }

    private function canPreview(Request $request): bool
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasRoleKey('super_admin')) {
            return true;
        }

        return $this->tenantAcl->canAccessRoute($user, 'admin.cms.themes.preview');
    }
}
