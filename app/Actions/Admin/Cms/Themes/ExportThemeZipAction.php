<?php

namespace App\Actions\Admin\Cms\Themes;

use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ExportThemeZipAction
{
    /**
     * @return array{path: string, filename: string}
     */
    public function handle(CmsTheme $theme): array
    {
        $version = $theme->activeVersion ?: $theme->versions()->first();

        if (! $version instanceof CmsThemeVersion) {
            throw new RuntimeException(__('cms_admin_ui.flash.theme_no_exportable_version'));
        }

        $filename = $theme->key.'-theme.zip';
        $directory = storage_path('app/theme-exports');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/'.Str::uuid().'-'.$filename;
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(__('cms_admin_ui.flash.theme_zip_create_failed'));
        }

        $disk = Storage::disk((string) config('cms_themes.storage_disk', 'local'));
        $manifest = $version->source_manifest ?: [
            'type' => 'rwsoft-css-theme',
            'name' => $theme->name,
            'key' => $theme->key,
            'version' => $theme->version,
            'author' => $theme->author,
        ];

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        $zip->addFromString('settings/schema.json', json_encode($this->settingsSchema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        $zip->addFromString('settings/values.json', json_encode($version->settings ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        $zip->addFromString('css/developer.css', $this->diskContents($disk, $version->developer_css_path));
        $zip->addFromString('css/generated.css', $version->generated_css_path ? $this->diskContents($disk, $version->generated_css_path) : '');
        $zip->addFromString('css/theme.min.css', $this->diskContents($disk, $version->minified_css_path));
        $zip->close();

        return [
            'path' => $path,
            'filename' => $filename,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsSchema(): array
    {
        return [
            'version' => 1,
            'fields' => [],
        ];
    }

    private function diskContents(FilesystemAdapter $disk, string $path): string
    {
        return $disk->exists($path) ? (string) $disk->get($path) : '';
    }
}
