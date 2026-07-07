<?php

namespace App\Actions\Admin\Cms\Themes;

use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CompileThemeCssAction
{
    public function __construct(
        private readonly GenerateThemeCssFromSettingsAction $generateCssFromSettings,
        private readonly ThemeStoragePathAction $storagePath,
        private readonly ValidateThemeCssAction $validateCss,
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $manifest
     * @return array{version: CmsThemeVersion, validation: array<string, mixed>}
     */
    public function handle(CmsTheme $theme, string $developerCss, array $settings = [], array $manifest = [], ?int $userId = null, ?string $forcedHash = null): array
    {
        $maxBytes = (int) config('cms_themes.css.max_css_bytes', 400000);

        if (strlen($developerCss) > $maxBytes) {
            throw new RuntimeException(__('cms_admin_ui.flash.theme_css_too_large'));
        }

        $generatedCss = $this->generateCssFromSettings->handle($settings);
        $combinedCss = $developerCss."\n".$generatedCss;
        $validation = $this->validateCss->handle($combinedCss);

        if (! $validation['valid']) {
            return [
                'version' => $theme->activeVersion ?: new CmsThemeVersion,
                'validation' => $validation,
            ];
        }

        $minifiedCss = $this->minify($combinedCss);
        $hash = $forcedHash ?: substr(hash('sha256', $minifiedCss), 0, 32);
        $disk = Storage::disk((string) config('cms_themes.storage_disk', 'local'));

        $developerCssPath = $this->storagePath->versionFile($theme, $hash, 'developer.css');
        $generatedCssPath = $this->storagePath->versionFile($theme, $hash, 'generated.css');
        $minifiedCssPath = $this->storagePath->versionFile($theme, $hash, 'theme.min.css');
        $manifestPath = $this->storagePath->versionFile($theme, $hash, 'manifest.json');
        $settingsPath = $this->storagePath->versionFile($theme, $hash, 'settings.json');

        $disk->put($developerCssPath, $developerCss);
        $disk->put($generatedCssPath, $generatedCss);
        $disk->put($minifiedCssPath, $minifiedCss);
        $disk->put($manifestPath, json_encode($this->manifest($theme, $manifest), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $disk->put($settingsPath, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $disk->put($this->storagePath->file($theme, 'developer.css'), $developerCss);
        $disk->put($this->storagePath->file($theme, 'generated.css'), $generatedCss);
        $disk->put($this->storagePath->file($theme, 'theme.min.css'), $minifiedCss);
        $disk->put($this->storagePath->file($theme, 'manifest.json'), json_encode($this->manifest($theme, $manifest), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $version = CmsThemeVersion::query()->firstOrNew([
            'cms_theme_id' => $theme->id,
            'version_hash' => $hash,
        ]);

        $version->fill([
            'developer_css_path' => $developerCssPath,
            'generated_css_path' => $generatedCssPath,
            'minified_css_path' => $minifiedCssPath,
            'settings' => $settings,
            'source_manifest' => $this->manifest($theme, $manifest),
            'external_assets' => $validation['external_assets'],
            'file_size_kb' => (int) ceil(strlen($minifiedCss) / 1024),
        ]);

        if (! $version->exists) {
            $version->created_by = $userId;
        }

        $version->save();

        return [
            'version' => $version,
            'validation' => $validation,
        ];
    }

    private function minify(string $css): string
    {
        $css = preg_replace('!/\*[^*]*\*+(?:[^/*][^*]*\*+)*/!', '', $css) ?? $css;
        $css = preg_replace('/\s+/', ' ', $css) ?? $css;
        $css = preg_replace('/\s*([{}:;,>])\s*/', '$1', $css) ?? $css;
        $css = str_replace([';}'], ['}'], $css);

        return trim($css)."\n";
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function manifest(CmsTheme $theme, array $manifest = []): array
    {
        return array_merge($manifest, [
            'type' => 'rwsoft-css-theme',
            'name' => $theme->name,
            'key' => $theme->key,
            'version' => $theme->version,
            'author' => $theme->author,
            'rwsoft_theme_version' => 1,
            'supports' => [
                'css' => true,
                'settings' => true,
                'blade' => false,
                'admin_pages' => false,
            ],
        ], $manifest);
    }
}
