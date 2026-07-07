<?php

namespace App\Support\Translations;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class DynamicPromptFileManager
{
    public function filePath(string $locale): string
    {
        $fileName = (string) config('dynamic_prompts.file_name', 'dynamic_prompts.php');

        return lang_path(trim($locale).DIRECTORY_SEPARATOR.$fileName);
    }

    /**
     * @return array<string, mixed>
     */
    public function read(string $locale): array
    {
        $path = $this->filePath($locale);

        if (! File::exists($path)) {
            return [];
        }

        $content = include $path;

        return is_array($content) ? $content : [];
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    public function write(string $locale, array $translations): void
    {
        $path = $this->filePath($locale);
        File::ensureDirectoryExists(dirname($path));

        $export = var_export($this->sortRecursive($translations), true);
        $php = "<?php\n\nreturn {$export};\n";

        File::put($path, $php);
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, string>
     */
    public function flatten(array $translations): array
    {
        return Arr::dot($translations);
    }

    /**
     * @param  array<string, string>  $translations
     * @return array<string, mixed>
     */
    public function expand(array $translations): array
    {
        $expanded = [];

        foreach ($translations as $key => $value) {
            Arr::set($expanded, $key, $value);
        }

        return $expanded;
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed>
     */
    private function sortRecursive(array $translations): array
    {
        ksort($translations);

        foreach ($translations as $key => $value) {
            if (is_array($value)) {
                $translations[$key] = $this->sortRecursive($value);
            }
        }

        return $translations;
    }
}
