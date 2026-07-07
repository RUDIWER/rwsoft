<?php

namespace App\Support\ModelDiscovery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class ModelClassLocator
{
    /**
     * @return array<int, class-string<Model>>
     */
    public function all(): array
    {
        $classes = [];

        foreach ($this->modelDirectories() as $directory) {
            foreach (File::allFiles($directory) as $file) {
                $className = $this->resolveClassNameFromPath($file->getPathname());
                if (! class_exists($className) || ! is_subclass_of($className, Model::class)) {
                    continue;
                }

                $classes[] = $className;
            }
        }

        $classes = array_values(array_unique($classes));
        sort($classes);

        return $classes;
    }

    /**
     * @return array<int, string>
     */
    private function modelDirectories(): array
    {
        $directories = [];

        $sharedModelsDirectory = app_path('Models');
        if (File::isDirectory($sharedModelsDirectory)) {
            $directories[] = $sharedModelsDirectory;
        }

        $appsDirectory = app_path('Apps');
        if (! File::isDirectory($appsDirectory)) {
            return $directories;
        }

        foreach (File::directories($appsDirectory) as $appDirectory) {
            $appModelsDirectory = $appDirectory.DIRECTORY_SEPARATOR.'Models';
            if (! File::isDirectory($appModelsDirectory)) {
                continue;
            }

            $directories[] = $appModelsDirectory;
        }

        return $directories;
    }

    private function resolveClassNameFromPath(string $filePath): string
    {
        $relativePath = str_replace(app_path().DIRECTORY_SEPARATOR, '', $filePath);
        $relativePath = str_replace(['/', '\\'], '\\', $relativePath);

        if (str_ends_with($relativePath, '.php')) {
            $relativePath = substr($relativePath, 0, -4);
        }

        return 'App\\'.$relativePath;
    }
}
