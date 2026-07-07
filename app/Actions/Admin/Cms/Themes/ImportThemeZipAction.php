<?php

namespace App\Actions\Admin\Cms\Themes;

use App\Models\Cms\CmsTheme;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ImportThemeZipAction
{
    public function __construct(
        private readonly CompileThemeCssAction $compileThemeCss,
        private readonly GenerateThemeCssFromSettingsAction $generateCssFromSettings,
        private readonly ValidateThemeCssAction $validateThemeCss,
    ) {}

    /**
     * @return array{theme: CmsTheme, validation: array<string, mixed>}
     */
    public function handle(UploadedFile $file, ?int $userId = null): array
    {
        $zip = new ZipArchive;

        if ($zip->open((string) $file->getRealPath()) !== true) {
            throw ValidationException::withMessages([
                'theme_zip' => 'Het ZIP bestand kon niet gelezen worden.',
            ]);
        }

        $this->validateZipEntries($zip);

        $manifest = $this->jsonFromZip($zip, 'manifest.json');
        $settings = $this->jsonFromZip($zip, 'settings/values.json', []);
        $developerCss = $this->stringFromZip($zip, 'css/developer.css');

        if ($developerCss === '') {
            $developerCss = $this->stringFromZip($zip, 'css/theme.min.css');
        }

        if (($manifest['type'] ?? null) !== 'rwsoft-css-theme') {
            throw ValidationException::withMessages([
                'theme_zip' => 'Dit ZIP bestand is geen geldig RwSoft CSS theme.',
            ]);
        }

        $generatedCss = $this->generateCssFromSettings->handle(is_array($settings) ? $settings : []);
        $validation = $this->validateThemeCss->handle($generatedCss."\n".$developerCss);

        if (! $validation['valid']) {
            throw ValidationException::withMessages([
                'theme_zip' => $this->validationMessages($validation['errors']),
            ]);
        }

        $theme = CmsTheme::query()->create([
            'name' => (string) ($manifest['name'] ?? 'Nieuw theme'),
            'key' => $this->uniqueKey((string) ($manifest['key'] ?? $manifest['name'] ?? 'theme')),
            'description' => $manifest['description'] ?? null,
            'author' => $manifest['author'] ?? null,
            'version' => (string) ($manifest['version'] ?? '1.0.0'),
            'status' => 'draft',
            'is_active' => false,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $result = $this->compileThemeCss->handle(
            $theme,
            $developerCss,
            is_array($settings) ? $settings : [],
            $manifest,
            $userId,
        );

        $zip->close();

        return [
            'theme' => $theme->fresh(['versions']) ?: $theme,
            'validation' => $result['validation'],
        ];
    }

    private function validateZipEntries(ZipArchive $zip): void
    {
        $allowedExtensions = (array) config('cms_themes.import.allowed_extensions', []);
        $allowedPaths = (array) config('cms_themes.import.allowed_paths', []);

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\')) {
                throw ValidationException::withMessages([
                    'theme_zip' => 'Het ZIP bestand bevat een ongeldig pad: '.$name,
                ]);
            }

            if (str_ends_with($name, '/')) {
                continue;
            }

            if (! $this->pathIsAllowed($name, $allowedPaths)) {
                throw ValidationException::withMessages([
                    'theme_zip' => 'Het ZIP bestand bevat een bestand buiten de toegestane theme mappen: '.$name,
                ]);
            }

            $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION));

            if (! in_array($extension, $allowedExtensions, true)) {
                throw ValidationException::withMessages([
                    'theme_zip' => 'Het ZIP bestand bevat een niet-toegestaan bestand: '.$name,
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
     * @return array<string, mixed>
     */
    private function jsonFromZip(ZipArchive $zip, string $name, ?array $default = null): array
    {
        $contents = $zip->getFromName($name);

        if ($contents === false) {
            if ($default !== null) {
                return $default;
            }

            throw ValidationException::withMessages([
                'theme_zip' => 'Het ZIP bestand mist '.$name.'.',
            ]);
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'theme_zip' => $name.' bevat geen geldige JSON.',
            ]);
        }

        return $decoded;
    }

    private function stringFromZip(ZipArchive $zip, string $name): string
    {
        $contents = $zip->getFromName($name);

        return $contents === false ? '' : (string) $contents;
    }

    private function uniqueKey(string $source): string
    {
        $base = Str::slug($source) ?: 'theme';
        $key = $base;
        $index = 2;

        while (CmsTheme::query()->where('key', $key)->exists()) {
            $key = $base.'-'.$index;
            $index++;
        }

        return $key;
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function validationMessages(array $errors): string
    {
        return collect($errors)
            ->map(fn (array $error): string => 'Regel '.$error['line'].': '.$error['message'])
            ->implode(' ');
    }
}
