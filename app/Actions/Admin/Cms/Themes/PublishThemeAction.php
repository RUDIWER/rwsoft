<?php

namespace App\Actions\Admin\Cms\Themes;

use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use Illuminate\Support\Facades\DB;

class PublishThemeAction
{
    public function handle(CmsTheme $theme, CmsThemeVersion $version): void
    {
        DB::transaction(function () use ($theme, $version): void {
            CmsTheme::query()
                ->where('id', '!=', $theme->id)
                ->update([
                    'is_active' => false,
                    'status' => 'draft',
                ]);

            $version->forceFill([
                'published_at' => now(),
            ])->save();

            $theme->forceFill([
                'is_active' => true,
                'status' => 'active',
                'active_version_id' => $version->id,
            ])->save();
        });
    }
}
