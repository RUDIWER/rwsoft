<?php

namespace App\Actions\Admin\Cms\Themes;

use App\Models\Cms\CmsTheme;
use App\Support\Tenancy\TenantContext;
use RuntimeException;

class ThemeStoragePathAction
{
    public function base(CmsTheme|string $theme): string
    {
        $siteId = TenantContext::siteId();

        if (! $siteId) {
            throw new RuntimeException(__('cms_admin_ui.flash.active_site_context_missing'));
        }

        $themeKey = $theme instanceof CmsTheme ? $theme->key : $theme;

        return 'sites/'.$siteId.'/themes/'.$themeKey;
    }

    public function file(CmsTheme|string $theme, string $filename): string
    {
        return $this->base($theme).'/'.ltrim($filename, '/');
    }

    public function versionFile(CmsTheme|string $theme, string $hash, string $filename): string
    {
        return $this->base($theme).'/versions/'.$hash.'/'.ltrim($filename, '/');
    }
}
