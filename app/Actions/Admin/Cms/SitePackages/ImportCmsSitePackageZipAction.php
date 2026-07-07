<?php

namespace App\Actions\Admin\Cms\SitePackages;

use App\Actions\Admin\Cms\Starters\ImportCmsStarterZipAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ImportCmsSitePackageZipAction
{
    public function __construct(
        private readonly ImportCmsStarterZipAction $importCmsStarterZip,
    ) {}

    /**
     * @return array{manifest: array<string, mixed>, modules: array<string, int>, imported: array<string, int>, mappings: array<string, array<string, int>>, warnings: array<int, string>}
     */
    public function handle(UploadedFile $file): array
    {
        try {
            return $this->importCmsStarterZip->handle($file, [
                'config' => 'cms_site_packages.import',
                'manifest_type' => config('cms_site_packages.manifest_type'),
                'importable_modules' => config('cms_site_packages.importable_modules', []),
                'import_prefix' => 'site-package',
                'import_marker_key' => 'site_package_import_key',
                'require_empty_site' => true,
                'allow_code_blocks' => config('cms_site_packages.import.allow_code_blocks_by_default', false),
            ]);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            if (isset($errors['starter_zip']) && ! isset($errors['site_package_zip'])) {
                $errors['site_package_zip'] = $errors['starter_zip'];
                unset($errors['starter_zip']);
            }

            throw ValidationException::withMessages($errors);
        }
    }
}
