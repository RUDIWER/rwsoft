<?php

namespace App\Actions\Admin\Cms\SitePackages;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class PreviewCmsSitePackageZipAction
{
    /**
     * @return array{manifest: array<string, mixed>, modules: array<string, int>, warnings: array<int, string>}
     */
    public function handle(UploadedFile $file): array
    {
        $options = $this->options();
        $zip = new ZipArchive;

        if ($zip->open((string) $file->getRealPath()) !== true) {
            throw ValidationException::withMessages([
                'site_package_zip' => __('cms_admin_ui.validation.starter_zip_unreadable'),
            ]);
        }

        try {
            $this->validateZipEntries($zip, $options);
            $manifest = $this->jsonFromZip($zip, 'manifest.json', $options);
            $modules = $this->validatedModules($manifest, $options);

            return [
                'manifest' => $manifest,
                'modules' => $this->moduleCounts($zip, $modules, $options),
                'warnings' => $this->warnings($zip, $modules),
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{allowed_extensions: array<int, string>, allowed_paths: array<int, string>, max_files: int, max_file_bytes: int, max_json_bytes: int, manifest_type: string, importable_modules: array<int, string>}
     */
    private function options(): array
    {
        return [
            'allowed_extensions' => (array) config('cms_site_packages.import.allowed_extensions', []),
            'allowed_paths' => (array) config('cms_site_packages.import.allowed_paths', []),
            'max_files' => (int) config('cms_site_packages.import.max_files', 500),
            'max_file_bytes' => (int) config('cms_site_packages.import.max_file_bytes', 10 * 1024 * 1024),
            'max_json_bytes' => (int) config('cms_site_packages.import.max_json_bytes', 5 * 1024 * 1024),
            'manifest_type' => (string) config('cms_site_packages.manifest_type'),
            'importable_modules' => (array) config('cms_site_packages.importable_modules', []),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function validateZipEntries(ZipArchive $zip, array $options): void
    {
        if ($zip->numFiles > (int) $options['max_files']) {
            throw ValidationException::withMessages([
                'site_package_zip' => __('cms_admin_ui.validation.starter_zip_too_many_files'),
            ]);
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\')) {
                throw ValidationException::withMessages([
                    'site_package_zip' => __('cms_admin_ui.validation.starter_zip_invalid_path', ['path' => $name]),
                ]);
            }

            if (str_ends_with($name, '/')) {
                continue;
            }

            if (! $this->pathIsAllowed($name, (array) $options['allowed_paths'])) {
                throw ValidationException::withMessages([
                    'site_package_zip' => __('cms_admin_ui.validation.starter_zip_forbidden_path', ['path' => $name]),
                ]);
            }

            $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION));

            if (! in_array($extension, (array) $options['allowed_extensions'], true)) {
                throw ValidationException::withMessages([
                    'site_package_zip' => __('cms_admin_ui.validation.starter_zip_forbidden_file', ['path' => $name]),
                ]);
            }

            $stat = $zip->statIndex($index);
            $size = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;

            if ($size > (int) $options['max_file_bytes']) {
                throw ValidationException::withMessages([
                    'site_package_zip' => __('cms_admin_ui.validation.starter_zip_file_too_large', ['path' => $name]),
                ]);
            }
        }
    }

    /**
     * @param  array<int, string>  $allowedPaths
     */
    private function pathIsAllowed(string $name, array $allowedPaths): bool
    {
        foreach ($allowedPaths as $allowedPath) {
            $allowedPath = (string) $allowedPath;

            if ($allowedPath === $name || (str_ends_with($allowedPath, '/') && str_starts_with($name, $allowedPath))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function jsonFromZip(ZipArchive $zip, string $name, array $options): array
    {
        $contents = $zip->getFromName($name);

        if ($contents === false) {
            throw ValidationException::withMessages([
                'site_package_zip' => __('cms_admin_ui.validation.starter_zip_missing_file', ['path' => $name]),
            ]);
        }

        if (strlen((string) $contents) > (int) $options['max_json_bytes']) {
            throw ValidationException::withMessages([
                'site_package_zip' => __('cms_admin_ui.validation.starter_zip_json_too_large', ['path' => $name]),
            ]);
        }

        $decoded = json_decode((string) $contents, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'site_package_zip' => __('cms_admin_ui.validation.starter_zip_invalid_json', ['path' => $name]),
            ]);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    private function validatedModules(array $manifest, array $options): array
    {
        if (($manifest['type'] ?? null) !== $options['manifest_type']) {
            throw ValidationException::withMessages([
                'site_package_zip' => __('cms_admin_ui.validation.starter_zip_invalid_manifest'),
            ]);
        }

        $modules = (array) ($manifest['modules'] ?? []);

        if ($modules === []) {
            throw ValidationException::withMessages([
                'site_package_zip' => __('cms_admin_ui.validation.starter_zip_invalid_modules'),
            ]);
        }

        $allowed = (array) $options['importable_modules'];

        foreach ($modules as $module) {
            if (! is_string($module) || ! in_array($module, $allowed, true)) {
                throw ValidationException::withMessages([
                    'site_package_zip' => __('cms_admin_ui.validation.starter_zip_unknown_module', ['module' => (string) $module]),
                ]);
            }
        }

        return collect($modules)->unique()->values()->all();
    }

    /**
     * @param  array<int, string>  $modules
     * @param  array<string, mixed>  $options
     * @return array<string, int>
     */
    private function moduleCounts(ZipArchive $zip, array $modules, array $options): array
    {
        $counts = [];

        foreach ($modules as $module) {
            $path = match ($module) {
                'media' => 'media/manifest.json',
                'downloads' => 'downloads/manifest.json',
                'themes' => 'themes/manifest.json',
                'blogs' => 'posts.json',
                default => $module.'.json',
            };

            if ($zip->locateName($path) === false) {
                $counts[$module] = 0;

                continue;
            }

            $data = $this->jsonFromZip($zip, $path, $options);
            $counts[$module] = match ($module) {
                'taxonomies' => count((array) ($data['categories'] ?? [])) + count((array) ($data['tags'] ?? [])),
                'downloads' => count((array) ($data['groups'] ?? [])) + count((array) ($data['folders'] ?? [])) + count((array) ($data['assets'] ?? [])),
                default => count($data),
            };
        }

        return $counts;
    }

    /**
     * @param  array<int, string>  $modules
     * @return array<int, string>
     */
    private function warnings(ZipArchive $zip, array $modules): array
    {
        $warnings = [];

        if (in_array('forms', $modules, true)) {
            foreach (['form_submissions.json', 'submissions.json'] as $path) {
                if ($zip->locateName($path) !== false) {
                    $warnings[] = __('cms_admin_ui.validation.site_package_preview_ignores_submissions');
                    break;
                }
            }
        }

        return $warnings;
    }
}
