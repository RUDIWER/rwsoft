<?php

namespace App\Actions\Admin\Cms\Starters;

use App\Actions\Admin\Cms\GenerateCmsHtmlAnchorAction;
use App\Actions\Admin\Cms\SaveCmsLayoutSectionsAction;
use App\Actions\Admin\Cms\SaveCmsSectionsAction;
use App\Actions\Admin\Cms\Themes\CompileThemeCssAction;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsDocCollection;
use App\Models\Cms\CmsDocPage;
use App\Models\Cms\CmsDocVersion;
use App\Models\Cms\CmsDownloadAsset;
use App\Models\Cms\CmsDownloadFolder;
use App\Models\Cms\CmsDownloadGroup;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormField;
use App\Models\Cms\CmsLanguage;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsPublicText;
use App\Models\Cms\CmsRedirect;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use App\Models\Cms\CmsTheme;
use App\Support\Cms\CmsBlockPackageMapper;
use App\Support\Cms\CmsBlockRegistry;
use App\Support\Cms\CmsTemplateDataContract;
use App\Support\Cms\CmsTemplateRegistry;
use App\Support\PublicSite\CmsPublicTextCache;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ImportCmsStarterZipAction
{
    public function __construct(
        private readonly SaveCmsLayoutSectionsAction $saveLayoutSections,
        private readonly SaveCmsSectionsAction $saveSections,
        private readonly GenerateCmsHtmlAnchorAction $htmlAnchorAction,
        private readonly CmsBlockRegistry $blockRegistry,
        private readonly CmsBlockPackageMapper $blockPackageMapper,
        private readonly CmsTemplateRegistry $templateRegistry,
        private readonly CompileThemeCssAction $compileThemeCss,
        private readonly CmsPublicTextCache $publicTextCache,
    ) {}

    /**
     * @return array{manifest: array<string, mixed>, modules: array<string, int>, imported: array<string, int>, mappings: array<string, array<string, int|string>>, warnings: array<int, string>}
     */
    /**
     * @param  array{config?: string, manifest_type?: string, importable_modules?: array<int, string>, import_prefix?: string, allow_code_blocks?: bool, error_key?: string, import_marker_key?: string, require_empty_site?: bool}  $options
     * @return array{manifest: array<string, mixed>, modules: array<string, int>, imported: array<string, int>, mappings: array<string, array<string, int|string>>, warnings: array<int, string>}
     */
    public function handle(UploadedFile $file, array $options = []): array
    {
        $options = $this->normalizedOptions($options);
        $zip = new ZipArchive;

        if ($zip->open((string) $file->getRealPath()) !== true) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_unreadable'),
            ]);
        }

        try {
            $this->validateZipEntries($zip, $options);
            $manifest = $this->jsonFromZip($zip, 'manifest.json', $options);
            $modules = $this->validatedModules($manifest, $options);
            $moduleCounts = $this->moduleCounts($zip, $modules, $options);

            if ((bool) ($options['require_empty_site'] ?? false)) {
                $this->assertCmsSitePackageImportTargetIsEmpty();
            }

            $importResult = DB::transaction(function () use ($zip, $manifest, $modules, $options): array {
                return $this->importModules($zip, $manifest, $modules, $options);
            });

            return [
                'manifest' => $manifest,
                'modules' => $moduleCounts,
                'imported' => $importResult['imported'],
                'mappings' => $importResult['mappings'],
                'warnings' => [],
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function validateZipEntries(ZipArchive $zip, array $options): void
    {
        $allowedExtensions = (array) ($options['allowed_extensions'] ?? []);
        $allowedPaths = (array) ($options['allowed_paths'] ?? []);
        $maxFiles = (int) ($options['max_files'] ?? 500);
        $maxFileBytes = (int) ($options['max_file_bytes'] ?? (5 * 1024 * 1024));

        if ($zip->numFiles > $maxFiles) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_too_many_files'),
            ]);
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\')) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_invalid_path', ['path' => $name]),
                ]);
            }

            if (str_ends_with($name, '/')) {
                continue;
            }

            if (! $this->pathIsAllowed($name, $allowedPaths)) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_forbidden_path', ['path' => $name]),
                ]);
            }

            $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION));

            if (! in_array($extension, $allowedExtensions, true)) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_forbidden_file', ['path' => $name]),
                ]);
            }

            $stat = $zip->statIndex($index);
            $size = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;

            if ($size > $maxFileBytes) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_file_too_large', ['path' => $name]),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function normalizedOptions(array $options): array
    {
        $config = (string) ($options['config'] ?? 'cms_starters.import');

        return [
            'allowed_extensions' => $options['allowed_extensions'] ?? config($config.'.allowed_extensions', []),
            'allowed_paths' => $options['allowed_paths'] ?? config($config.'.allowed_paths', []),
            'max_files' => $options['max_files'] ?? config($config.'.max_files', 500),
            'max_file_bytes' => $options['max_file_bytes'] ?? config($config.'.max_file_bytes', 5 * 1024 * 1024),
            'max_json_bytes' => $options['max_json_bytes'] ?? config($config.'.max_json_bytes', 2 * 1024 * 1024),
            'manifest_type' => $options['manifest_type'] ?? config($config.'.manifest_type'),
            'importable_modules' => $options['importable_modules'] ?? config($config.'.importable_modules', []),
            'import_prefix' => $options['import_prefix'] ?? 'starter',
            'allow_code_blocks' => $options['allow_code_blocks'] ?? config($config.'.allow_code_blocks_by_default', false),
            'error_key' => $options['error_key'] ?? 'starter_zip',
            'import_marker_key' => $options['import_marker_key'] ?? 'starter_import_key',
            'require_empty_site' => $options['require_empty_site'] ?? false,
        ];
    }

    private function assertCmsSitePackageImportTargetIsEmpty(): void
    {
        $contentCounts = [
            'pages' => CmsPage::query()->where(function ($query): void {
                $query->where('is_home', false)
                    ->orWhere('slug', '!=', 'home')
                    ->orWhereNotNull('parent_id');
            })->count(),
            'menus' => CmsMenu::query()->count(),
            'blogs' => CmsPost::query()->count(),
            'forms' => CmsForm::query()->count(),
            'docs' => CmsDocCollection::query()->count() + CmsDocVersion::query()->count() + CmsDocPage::query()->count(),
            'redirects' => CmsRedirect::query()->count(),
            'taxonomies' => CmsCategory::query()->count() + CmsTag::query()->count(),
            'media' => CmsMediaAsset::query()->count(),
            'downloads' => $this->downloadContentCount(),
        ];

        $hasContent = collect($contentCounts)->contains(fn (int $count): bool => $count > 0);

        if (! $hasContent) {
            return;
        }

        throw ValidationException::withMessages([
            'starter_zip' => __('cms_admin_ui.validation.site_package_import_requires_empty_site'),
        ]);
    }

    private function downloadContentCount(): int
    {
        if (! Schema::hasTable((new CmsDownloadFolder)->getTable())) {
            return 0;
        }

        return CmsDownloadFolder::query()->count()
            + CmsDownloadAsset::query()->count()
            + CmsDownloadGroup::query()->count();
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
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function jsonFromZip(ZipArchive $zip, string $name, array $options): array
    {
        $contents = $zip->getFromName($name);

        if ($contents === false) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_file', ['path' => $name]),
            ]);
        }

        if (strlen((string) $contents) > (int) ($options['max_json_bytes'] ?? (2 * 1024 * 1024))) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_json_too_large', ['path' => $name]),
            ]);
        }

        $decoded = json_decode((string) $contents, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_invalid_json', ['path' => $name]),
            ]);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<int, string>
     */
    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    private function validatedModules(array $manifest, array $options): array
    {
        if (($manifest['type'] ?? null) !== ($options['manifest_type'] ?? null)) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_invalid_manifest_type'),
            ]);
        }

        $modules = $manifest['modules'] ?? [];

        if (! is_array($modules)) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_invalid_modules'),
            ]);
        }

        $modules = collect($modules)
            ->map(fn (mixed $module): string => (string) $module)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $unknownModules = array_values(array_diff($modules, (array) ($options['importable_modules'] ?? [])));

        if ($unknownModules !== []) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_unknown_module', ['module' => $unknownModules[0]]),
            ]);
        }

        return $modules;
    }

    /**
     * @param  array<int, string>  $modules
     * @return array<string, int>
     */
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

            if (! in_array($module, ['site', 'languages', 'public_texts', 'layouts', 'templates', 'pages', 'menus', 'forms', 'media', 'downloads', 'themes', 'redirects', 'taxonomies', 'blogs', 'docs'], true) || $zip->locateName($path) === false) {
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
     * @param  array<string, mixed>  $manifest
     * @param  array<int, string>  $modules
     * @return array{imported: array<string, int>, mappings: array<string, array<string, int|string>>}
     */
    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<int, string>  $modules
     * @param  array<string, mixed>  $options
     * @return array{imported: array<string, int>, mappings: array<string, array<string, int|string>>}
     */
    private function importModules(ZipArchive $zip, array $manifest, array $modules, array $options): array
    {
        $prefix = $this->importKeyPrefix($manifest, $options);
        $mappings = [
            'layouts' => [],
            'templates' => [],
            'pages' => [],
            'menus' => [],
            'site' => [],
            'languages' => [],
            'public_texts' => [],
            'redirects' => [],
            'forms' => [],
            'categories' => [],
            'tags' => [],
            'posts' => [],
            'media' => [],
            'downloads' => [],
            'download_folders' => [],
            'themes' => [],
            'docs' => [],
        ];
        $imported = [
            'layouts' => 0,
            'templates' => 0,
            'pages' => 0,
            'menus' => 0,
            'site' => 0,
            'languages' => 0,
            'public_texts' => 0,
            'redirects' => 0,
            'forms' => 0,
            'taxonomies' => 0,
            'blogs' => 0,
            'media' => 0,
            'downloads' => 0,
            'themes' => 0,
            'docs' => 0,
        ];

        if (in_array('public_texts', $modules, true) && $zip->locateName('public_texts.json') !== false) {
            $imported['public_texts'] = $this->importPublicTexts($zip, $options);
        }

        if (in_array('redirects', $modules, true) && $zip->locateName('redirects.json') !== false) {
            $mappings['redirects'] = $this->importRedirects($zip, $prefix, $options);
            $imported['redirects'] = count($mappings['redirects']);
        }

        if (in_array('themes', $modules, true) && $zip->locateName('themes/manifest.json') !== false) {
            $mappings['themes'] = $this->importThemes($zip, $prefix, $options);
            $imported['themes'] = count($mappings['themes']);
        }

        if (in_array('media', $modules, true) && $zip->locateName('media/manifest.json') !== false) {
            $mappings['media'] = $this->importMedia($zip, $prefix, $options);
            $imported['media'] = count($mappings['media']);
        }

        if (in_array('downloads', $modules, true) && $zip->locateName('downloads/manifest.json') !== false) {
            $downloadMappings = $this->importDownloads($zip, $prefix, $options);
            $mappings['downloads'] = $downloadMappings['downloads'];
            $mappings['download_folders'] = $downloadMappings['download_folders'];
            $imported['downloads'] = count($mappings['downloads']) + count($mappings['download_folders']) + count($downloadMappings['download_groups']);
        }

        if (in_array('site', $modules, true) && $zip->locateName('site.json') !== false) {
            $imported['site'] = $this->importSiteSettings($zip, $options, $mappings['media']);
        }

        if (in_array('languages', $modules, true) && $zip->locateName('languages.json') !== false) {
            $mappings['languages'] = $this->importLanguages($zip, $mappings['media'], $options);
            $imported['languages'] = count($mappings['languages']);
        }

        if (in_array('forms', $modules, true) && $zip->locateName('forms.json') !== false) {
            $mappings['forms'] = $this->importForms($zip, $prefix, $options);
            $imported['forms'] = count($mappings['forms']);
        }

        if (in_array('menus', $modules, true) && $zip->locateName('menus.json') !== false) {
            $mappings['menus'] = $this->importMenus($zip, $prefix, $options);
            $imported['menus'] = count($mappings['menus']);
        }

        if (in_array('layouts', $modules, true) && $zip->locateName('layouts.json') !== false) {
            $mappings['layouts'] = $this->importLayouts($zip, $prefix, $options, $mappings['media'], $mappings['forms'], $mappings['menus'], $mappings['downloads'], $mappings['download_folders']);
            $imported['layouts'] = count($mappings['layouts']);
        }

        if (in_array('templates', $modules, true) && $zip->locateName('templates.json') !== false) {
            $mappings['templates'] = $this->importTemplates($zip, $prefix, $mappings['layouts'], $options, $mappings['media'], $mappings['forms'], $mappings['menus'], $mappings['downloads'], $mappings['download_folders']);
            $imported['templates'] = count($mappings['templates']);
        }

        if (in_array('pages', $modules, true) && $zip->locateName('pages.json') !== false) {
            $mappings['pages'] = $this->importPages($zip, $prefix, $mappings['templates'], $options, $mappings['media'], $mappings['forms'], $mappings['menus'], $mappings['downloads'], $mappings['download_folders']);
            $imported['pages'] = count($mappings['pages']);
        }

        if (in_array('menus', $modules, true) && $zip->locateName('menus.json') !== false) {
            $this->importMenuItemsForMenus($zip, $prefix, $mappings['pages'], $mappings['menus'], $options);
        }

        if (in_array('taxonomies', $modules, true) && $zip->locateName('taxonomies.json') !== false) {
            $taxonomyMappings = $this->importTaxonomies($zip, $prefix, $mappings['pages'], $mappings['templates'], $options);
            $mappings['categories'] = $taxonomyMappings['categories'];
            $mappings['tags'] = $taxonomyMappings['tags'];
            $imported['taxonomies'] = count($mappings['categories']) + count($mappings['tags']);
        }

        if (in_array('blogs', $modules, true) && $zip->locateName('posts.json') !== false) {
            $mappings['posts'] = $this->importPosts($zip, $prefix, $mappings['templates'], $mappings['media'], $mappings['categories'], $mappings['tags'], $mappings['forms'], $options, $mappings['downloads'], $mappings['download_folders']);
            $imported['blogs'] = count($mappings['posts']);
        }

        if (in_array('docs', $modules, true) && $zip->locateName('docs.json') !== false) {
            $mappings['docs'] = $this->importDocs($zip, $prefix, $mappings['media'], $options);
            $imported['docs'] = count($mappings['docs']);
        }

        return [
            'imported' => $imported,
            'mappings' => $mappings,
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $options
     */
    private function importKeyPrefix(array $manifest, array $options): string
    {
        $source = (string) ($manifest['key'] ?? $manifest['name'] ?? 'starter');
        $prefix = (string) ($options['import_prefix'] ?? 'starter');

        return $prefix.':'.(Str::slug($source) ?: $prefix).':';
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, int>
     */
    private function importRedirects(ZipArchive $zip, string $prefix, array $options): array
    {
        $redirects = $this->listFromZip($zip, 'redirects.json', $options);
        $mappings = [];

        foreach ($redirects as $redirectData) {
            $importKey = $this->requiredImportKey($redirectData, 'redirect');
            $sourcePath = $this->redirectSourcePath($redirectData['source_path'] ?? null);
            $locale = $this->nullableLocaleValue($redirectData['locale'] ?? null);

            if ($sourcePath === '') {
                continue;
            }

            $redirect = CmsRedirect::query()->create([
                'import_key' => $prefix.$importKey,
                'source_path' => $this->uniqueRedirectSourcePath($sourcePath, $locale),
                'target_url' => $this->redirectTargetUrl($redirectData['target_url'] ?? null),
                'status_code' => $this->redirectStatusCode($redirectData['status_code'] ?? null),
                'locale' => $locale,
                'is_active' => false,
                'starts_at' => $this->nullableDateString($redirectData['starts_at'] ?? null),
                'ends_at' => $this->nullableDateString($redirectData['ends_at'] ?? null),
                'hit_count' => 0,
                'last_hit_at' => null,
            ]);

            $mappings[$importKey] = (int) $redirect->id;
        }

        return $mappings;
    }

    private function redirectSourcePath(mixed $value): string
    {
        $path = $this->stringValue($value);

        return preg_match('/^\/[^\s]*$/', $path) === 1 ? $path : '';
    }

    private function redirectTargetUrl(mixed $value): string
    {
        $targetUrl = $this->stringValue($value);

        if (preg_match('/^(\/[^\s]*|https?:\/\/[^\s]+)$/i', $targetUrl) === 1) {
            return $targetUrl;
        }

        throw ValidationException::withMessages([
            'starter_zip' => __('cms_admin_ui.validation.redirect_target_url'),
        ]);
    }

    private function redirectStatusCode(mixed $value): int
    {
        $statusCode = (int) $value;

        return in_array($statusCode, [301, 302, 307, 308], true) ? $statusCode : 301;
    }

    private function nullableLocaleValue(mixed $value): ?string
    {
        $locale = $this->stringValue($value);

        if ($locale === '') {
            return null;
        }

        return $this->localeValue($locale);
    }

    private function nullableDateString(mixed $value): ?string
    {
        $date = $this->stringValue($value);

        return strtotime($date) !== false ? $date : null;
    }

    private function uniqueRedirectSourcePath(string $sourcePath, ?string $locale): string
    {
        $candidate = $sourcePath;
        $index = 2;

        while (CmsRedirect::query()->where('source_path', $candidate)->where('locale', $locale)->exists()) {
            $candidate = rtrim($sourcePath, '/').'-imported-'.$index;
            $index++;
        }

        return $candidate;
    }

    /**
     * @param  array<string, int>  $pageMappings
     * @param  array<string, int>  $templateMappings
     * @param  array<string, mixed>  $options
     * @return array{categories: array<string, int>, tags: array<string, int>}
     */
    private function importTaxonomies(ZipArchive $zip, string $prefix, array $pageMappings, array $templateMappings, array $options): array
    {
        $taxonomyData = $this->jsonFromZip($zip, 'taxonomies.json', $options);
        $categories = collect((array) ($taxonomyData['categories'] ?? []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->all();
        $tags = collect((array) ($taxonomyData['tags'] ?? []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->all();
        $categoryTranslationKeys = $this->categoryTranslationKeys($categories, $prefix, $options);
        $tagTranslationKeys = $this->tagTranslationKeys($tags, $prefix, $options);
        $categoryMappings = $this->importCategories($categories, $prefix, $pageMappings, $templateMappings, $options, $categoryTranslationKeys);
        $tagMappings = $this->importTags($tags, $prefix, $pageMappings, $templateMappings, $options, $tagTranslationKeys);

        $this->syncCategoryRelations($categories, $categoryMappings);
        $this->syncTagRelations($tags, $tagMappings);

        return [
            'categories' => $categoryMappings,
            'tags' => $tagMappings,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @param  array<string, int>  $pageMappings
     * @param  array<string, int>  $templateMappings
     * @param  array<string, mixed>  $options
     * @param  array<string, string>  $translationKeys
     * @return array<string, int>
     */
    private function importCategories(array $categories, string $prefix, array $pageMappings, array $templateMappings, array $options, array $translationKeys): array
    {
        $mappings = [];

        foreach ($categories as $categoryData) {
            $importKey = $this->requiredImportKey($categoryData, 'category');
            $starterImportKey = $prefix.$importKey;
            $importMarkerKey = (string) ($options['import_marker_key'] ?? 'starter_import_key');
            $category = CmsCategory::query()
                ->where('settings->'.$importMarkerKey, $starterImportKey)
                ->first() ?: new CmsCategory;
            $type = $category->exists ? (string) $category->type : $this->categoryType($categoryData['type'] ?? null);
            $locale = $category->exists ? (string) $category->locale : $this->localeValue($categoryData['locale'] ?? null);
            $settings = is_array($categoryData['settings'] ?? null) ? $categoryData['settings'] : [];
            $settings[$importMarkerKey] = $starterImportKey;

            $category->fill([
                'parent_id' => null,
                'type' => $type,
                'title' => $this->stringValue($categoryData['title'] ?? null, 'Categorie'),
                'slug' => $category->exists
                    ? (string) $category->slug
                    : $this->uniqueCategorySlug($type, $locale, $categoryData['slug'] ?? $categoryData['title'] ?? $importKey),
                'locale' => $locale,
                'translation_key' => $category->exists
                    ? (string) $category->translation_key
                    : $translationKeys[$this->categoryTranslationGroupKey($type, $categoryData['translation_key'] ?? null)] ?? (string) Str::ulid(),
                'translated_from_category_id' => null,
                'landing_page_id' => $this->optionalMappedId($pageMappings, $categoryData['landing_page_import_key'] ?? null),
                'archive_template_id' => $this->optionalMappedId($templateMappings, $categoryData['archive_template_import_key'] ?? null),
                'detail_template_id' => $this->optionalMappedId($templateMappings, $categoryData['detail_template_import_key'] ?? null),
                'description' => $this->nullableString($categoryData['description'] ?? null),
                'sort_order' => max((int) ($categoryData['sort_order'] ?? 0), 0),
                'is_active' => false,
                'settings' => $settings,
            ])->save();

            $mappings[$importKey] = (int) $category->id;
        }

        return $mappings;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tags
     * @param  array<string, int>  $pageMappings
     * @param  array<string, int>  $templateMappings
     * @param  array<string, mixed>  $options
     * @param  array<string, string>  $translationKeys
     * @return array<string, int>
     */
    private function importTags(array $tags, string $prefix, array $pageMappings, array $templateMappings, array $options, array $translationKeys): array
    {
        $mappings = [];

        foreach ($tags as $tagData) {
            $importKey = $this->requiredImportKey($tagData, 'tag');
            $starterImportKey = $prefix.$importKey;
            $importMarkerKey = (string) ($options['import_marker_key'] ?? 'starter_import_key');
            $tag = CmsTag::query()
                ->where('settings->'.$importMarkerKey, $starterImportKey)
                ->first() ?: new CmsTag;
            $locale = $tag->exists ? (string) $tag->locale : $this->localeValue($tagData['locale'] ?? null);
            $settings = is_array($tagData['settings'] ?? null) ? $tagData['settings'] : [];
            $settings[$importMarkerKey] = $starterImportKey;

            $tag->fill([
                'title' => $this->stringValue($tagData['title'] ?? null, 'Tag'),
                'slug' => $tag->exists
                    ? (string) $tag->slug
                    : $this->uniqueTagSlug($locale, $tagData['slug'] ?? $tagData['title'] ?? $importKey),
                'locale' => $locale,
                'translation_key' => $tag->exists
                    ? (string) $tag->translation_key
                    : $translationKeys[$this->translationGroupKey($tagData['translation_key'] ?? null)] ?? (string) Str::ulid(),
                'translated_from_tag_id' => null,
                'landing_page_id' => $this->optionalMappedId($pageMappings, $tagData['landing_page_import_key'] ?? null),
                'archive_template_id' => $this->optionalMappedId($templateMappings, $tagData['archive_template_import_key'] ?? null),
                'detail_template_id' => $this->optionalMappedId($templateMappings, $tagData['detail_template_import_key'] ?? null),
                'description' => $this->nullableString($tagData['description'] ?? null),
                'is_active' => false,
                'settings' => $settings,
            ])->save();

            $mappings[$importKey] = (int) $tag->id;
        }

        return $mappings;
    }

    /**
     * @param  array<string, int>  $templateMappings
     * @param  array<string, int>  $mediaMappings
     * @param  array<string, int>  $categoryMappings
     * @param  array<string, int>  $tagMappings
     * @param  array<string, string>  $formMappings
     * @param  array<string, mixed>  $options
     * @return array<string, int>
     */
    private function importPosts(ZipArchive $zip, string $prefix, array $templateMappings, array $mediaMappings, array $categoryMappings, array $tagMappings, array $formMappings, array $options, array $downloadMappings = [], array $downloadFolderMappings = []): array
    {
        $posts = $this->listFromZip($zip, 'posts.json', $options);
        $translationKeys = $this->postTranslationKeys($posts, $prefix, $options);
        $mappings = [];

        foreach ($posts as $postData) {
            $importKey = $this->requiredImportKey($postData, 'post');
            $starterImportKey = $prefix.$importKey;
            $importMarkerKey = (string) ($options['import_marker_key'] ?? 'starter_import_key');
            $post = CmsPost::query()
                ->where('settings->'.$importMarkerKey, $starterImportKey)
                ->first() ?: new CmsPost;
            $locale = $post->exists ? (string) $post->locale : $this->localeValue($postData['locale'] ?? null);
            $settings = is_array($postData['settings'] ?? null) ? $postData['settings'] : [];
            $settings[$importMarkerKey] = $starterImportKey;
            $contentBlocks = is_array($postData['content_blocks'] ?? null) ? $postData['content_blocks'] : [];
            $contentBlocks = $this->remapContentBlocks($contentBlocks, $mediaMappings, $categoryMappings, $tagMappings, $formMappings, $downloadMappings, $downloadFolderMappings);
            $this->validateContentBlocks($contentBlocks, 'post '.$importKey, $options);

            $post->fill([
                'author_id' => null,
                'featured_media_asset_id' => $this->optionalMappedId($mediaMappings, $postData['featured_media_import_key'] ?? null),
                'detail_template_id' => $this->optionalMappedId($templateMappings, $postData['detail_template_import_key'] ?? null),
                'title' => $this->stringValue($postData['title'] ?? null, 'Blog'),
                'slug' => $post->exists
                    ? (string) $post->slug
                    : $this->uniquePostSlug($locale, $postData['slug'] ?? $postData['title'] ?? $importKey),
                'locale' => $locale,
                'translation_key' => $post->exists
                    ? (string) $post->translation_key
                    : $translationKeys[$this->translationGroupKey($postData['translation_key'] ?? null)] ?? (string) Str::ulid(),
                'translated_from_post_id' => null,
                'status' => 'draft',
                'excerpt' => $this->nullableString($postData['excerpt'] ?? null),
                'content_blocks' => $contentBlocks,
                'seo_title' => $this->nullableString($postData['seo_title'] ?? null),
                'seo_description' => $this->nullableString($postData['seo_description'] ?? null),
                'canonical_url' => $this->nullableString($postData['canonical_url'] ?? null),
                'og_image_path' => $this->nullableString($postData['og_image_path'] ?? null),
                'noindex' => (bool) ($postData['noindex'] ?? false),
                'is_featured' => (bool) ($postData['is_featured'] ?? false),
                'is_searchable' => (bool) ($postData['is_searchable'] ?? true),
                'published_at' => null,
                'settings' => $settings,
            ])->save();

            $post->categories()->sync($this->mappedIds($categoryMappings, $postData['category_import_keys'] ?? [], 'category'));
            $post->tags()->sync($this->mappedIds($tagMappings, $postData['tag_import_keys'] ?? [], 'tag'));
            $mappings[$importKey] = (int) $post->id;
        }

        $this->syncPostRelations($posts, $mappings);

        return $mappings;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, int>  $mediaMappings
     */
    private function importSiteSettings(ZipArchive $zip, array $options, array $mediaMappings = []): int
    {
        $settings = $this->listFromZip($zip, 'site.json', $options);
        $allowed = collect((array) config('cms_site_packages.policies.site.settings', []))
            ->map(fn (mixed $path): string => (string) $path)
            ->filter()
            ->all();
        $imported = 0;

        foreach ($settings as $settingData) {
            $group = $this->settingSegment($settingData['group'] ?? null, 'general');
            $key = $this->settingSegment($settingData['key'] ?? null);

            if ($key === '' || ! in_array($group.'.'.$key, $allowed, true)) {
                continue;
            }

            $setting = CmsSetting::query()->updateOrCreate(
                ['group' => $group, 'key' => $key],
                [
                    'label' => $this->nullableString($settingData['label'] ?? null),
                    'type' => $this->settingType($settingData['type'] ?? null),
                    'value' => $this->siteSettingValue($zip, $group, $key, $settingData['value'] ?? [], $mediaMappings),
                    'is_public' => true,
                    'sort_order' => max((int) ($settingData['sort_order'] ?? 0), 0),
                ],
            );

            $this->importSettingTranslations($setting, (array) ($settingData['translations'] ?? []));
            $imported++;
        }

        $this->publicTextCache->flush();

        return $imported;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function importPublicTexts(ZipArchive $zip, array $options): int
    {
        $texts = $this->listFromZip($zip, 'public_texts.json', $options);
        $imported = 0;

        foreach ($texts as $textData) {
            $group = $this->settingSegment($textData['group'] ?? null, 'general');
            $key = $this->settingSegment($textData['key'] ?? null);

            if ($key === '') {
                continue;
            }

            $publicText = CmsPublicText::query()->updateOrCreate(
                ['group' => $group, 'key' => $key],
                [
                    'label' => $this->stringValue($textData['label'] ?? null, $group.'.'.$key),
                    'description' => $this->nullableString($textData['description'] ?? null),
                    'default_value' => $this->nullableString($textData['default_value'] ?? null),
                    'type' => $this->settingType($textData['type'] ?? null),
                    'is_system' => (bool) ($textData['is_system'] ?? true),
                    'sort_order' => max((int) ($textData['sort_order'] ?? 0), 0),
                ],
            );

            $this->importPublicTextTranslations($publicText, (array) ($textData['translations'] ?? []));
            $imported++;
        }

        $this->publicTextCache->flush();

        return $imported;
    }

    /**
     * @param  array<string, int|string>  $mediaMappings
     * @param  array<string, mixed>  $options
     * @return array<string, int>
     */
    private function importLanguages(ZipArchive $zip, array $mediaMappings, array $options): array
    {
        $languages = $this->listFromZip($zip, 'languages.json', $options);
        $mappings = [];

        foreach ($languages as $languageData) {
            $locale = Str::lower($this->settingSegment($languageData['locale'] ?? null));

            if ($locale === '') {
                continue;
            }

            $flagMediaImportKey = $this->nullableString($languageData['flag_media_import_key'] ?? null);
            $flagMediaAssetId = $flagMediaImportKey !== null && isset($mediaMappings[$flagMediaImportKey])
                ? (int) $mediaMappings[$flagMediaImportKey]
                : null;

            $language = CmsLanguage::query()->updateOrCreate(
                ['locale' => $locale],
                [
                    'name' => $this->stringValue($languageData['name'] ?? null, strtoupper($locale)),
                    'native_name' => $this->stringValue($languageData['native_name'] ?? null, strtoupper($locale)),
                    'direction' => in_array($languageData['direction'] ?? null, ['ltr', 'rtl'], true) ? (string) $languageData['direction'] : 'ltr',
                    'flag_media_asset_id' => $flagMediaAssetId,
                    'is_active' => (bool) ($languageData['is_active'] ?? true),
                    'sort_order' => max((int) ($languageData['sort_order'] ?? 0), 0),
                ],
            );

            $mappings[$locale] = (int) $language->id;
        }

        return $mappings;
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    private function importSettingTranslations(CmsSetting $setting, array $translations): void
    {
        foreach ($translations as $locale => $translation) {
            if (! is_array($translation)) {
                continue;
            }

            $setting->translations()->updateOrCreate(
                ['locale' => $this->localeValue($locale)],
                ['value' => $this->jsonValue($translation['value'] ?? [])],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    private function importPublicTextTranslations(CmsPublicText $publicText, array $translations): void
    {
        foreach ($translations as $locale => $translation) {
            if (! is_array($translation)) {
                continue;
            }

            $publicText->translations()->updateOrCreate(
                ['locale' => $this->localeValue($locale)],
                ['value' => $this->nullableString($translation['value'] ?? null)],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, string>
     */
    private function importForms(ZipArchive $zip, string $prefix, array $options): array
    {
        $forms = $this->listFromZip($zip, 'forms.json', $options);
        $translationKeys = $this->formTranslationKeys($forms, $prefix, $options);
        $mappings = [];
        $fieldMappings = [];

        foreach ($forms as $formData) {
            $importKey = $this->requiredImportKey($formData, 'form');
            $starterImportKey = $prefix.$importKey;
            $importMarkerKey = (string) ($options['import_marker_key'] ?? 'starter_import_key');
            $form = CmsForm::query()
                ->where('settings->'.$importMarkerKey, $starterImportKey)
                ->first() ?: new CmsForm;
            $locale = $form->exists ? (string) $form->locale : $this->localeValue($formData['locale'] ?? null);
            $settings = is_array($formData['settings'] ?? null) ? $formData['settings'] : [];
            $settings[$importMarkerKey] = $starterImportKey;

            $form->fill([
                'title' => $this->stringValue($formData['title'] ?? null, 'Formulier'),
                'locale' => $locale,
                'translation_key' => $form->exists
                    ? (string) $form->translation_key
                    : $translationKeys[$this->translationGroupKey($this->formTranslationKeyValue($formData['translation_key'] ?? null))] ?? (string) Str::ulid(),
                'translated_from_form_id' => null,
                'description' => $this->nullableString($formData['description'] ?? null),
                'notification_email' => $this->nullableEmail($formData['notification_email'] ?? null),
                'submit_button_label' => $this->nullableString($formData['submit_button_label'] ?? null),
                'success_message' => $this->nullableString($formData['success_message'] ?? null),
                'is_active' => false,
                'settings' => $settings,
            ])->save();

            $fieldMappings[$importKey] = $this->importFormFields($form, (array) ($formData['fields'] ?? []), $prefix, $options);
            $mappings[$importKey] = (string) $form->translation_key;
        }

        $this->syncFormRelations($forms, $mappings, $prefix, $options);
        $this->syncFormFieldRelations($forms, $fieldMappings);

        return $mappings;
    }

    /**
     * @param  array<int, mixed>  $fields
     * @param  array<string, mixed>  $options
     * @return array<string, int>
     */
    private function importFormFields(CmsForm $form, array $fields, string $prefix, array $options): array
    {
        $mappings = [];

        foreach (array_values($fields) as $index => $fieldData) {
            if (! is_array($fieldData)) {
                continue;
            }

            $importKey = $this->requiredImportKey($fieldData, 'form field');
            $starterImportKey = $prefix.$importKey;
            $importMarkerKey = (string) ($options['import_marker_key'] ?? 'starter_import_key');
            $field = CmsFormField::query()
                ->where('cms_form_id', $form->id)
                ->where('settings->'.$importMarkerKey, $starterImportKey)
                ->first() ?: new CmsFormField(['cms_form_id' => $form->id]);
            $settings = is_array($fieldData['settings'] ?? null) ? $fieldData['settings'] : [];
            $settings[$importMarkerKey] = $starterImportKey;

            $field->fill([
                'cms_form_id' => $form->id,
                'type' => $this->formFieldType($fieldData['type'] ?? null),
                'translation_key' => $field->exists
                    ? (string) $field->translation_key
                    : $this->formTranslationKeyValue($fieldData['translation_key'] ?? null, (string) Str::ulid()),
                'translated_from_form_field_id' => null,
                'label' => $this->stringValue($fieldData['label'] ?? null, 'Veld'),
                'placeholder' => $this->nullableString($fieldData['placeholder'] ?? null),
                'help_text' => $this->nullableString($fieldData['help_text'] ?? null),
                'options' => $this->formFieldOptions($fieldData['options'] ?? []),
                'validation_rules' => is_array($fieldData['validation_rules'] ?? null) ? $fieldData['validation_rules'] : [],
                'sort_order' => max((int) ($fieldData['sort_order'] ?? (($index + 1) * 10)), 0),
                'is_required' => (bool) ($fieldData['is_required'] ?? false),
                'is_active' => false,
                'width' => in_array($fieldData['width'] ?? null, ['full', 'half'], true) ? $fieldData['width'] : 'full',
                'settings' => $settings,
            ])->save();

            $mappings[$importKey] = (int) $field->id;
        }

        return $mappings;
    }

    private function settingSegment(mixed $value, string $fallback = ''): string
    {
        $segment = Str::snake(Str::lower($this->stringValue($value, $fallback)));

        return preg_match('/^[a-z0-9_\-]+$/', $segment) === 1 ? $segment : $fallback;
    }

    private function settingType(mixed $value): string
    {
        $type = $this->stringValue($value, 'text');

        return in_array($type, ['text', 'textarea', 'number', 'boolean'], true) ? $type : 'text';
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, int>  $mediaMappings
     * @return array<string, mixed>
     */
    private function siteSettingValue(ZipArchive $zip, string $group, string $key, mixed $value, array $mediaMappings = []): array
    {
        if (in_array($group.'.'.$key, ['contact.image_media_asset_id', 'branding.company_logo_media_asset_id'], true)) {
            $valueArray = $this->jsonValue($value);
            $mediaImportKey = $this->nullableString($valueArray['media_import_key'] ?? null);

            return [
                'value' => $mediaImportKey !== null && isset($mediaMappings[$mediaImportKey])
                    ? $mediaMappings[$mediaImportKey]
                    : null,
            ];
        }

        if ($group !== 'branding' || ! in_array($key, $this->brandingPathKeys(), true)) {
            return $this->jsonValue($value);
        }

        $entry = $this->brandingFileEntry($key);

        if ($zip->locateName($entry) === false) {
            return $this->jsonValue($value);
        }

        $contents = $zip->getFromName($entry);

        if (! is_string($contents) || $contents === '') {
            return $this->jsonValue($value);
        }

        [$width, $height] = $this->imageDimensionsFromString($contents);

        if ($width === null || $height === null) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_forbidden_file', ['path' => $entry]),
            ]);
        }

        $path = $this->targetBrandingPath($key);
        Storage::disk('public')->put($path, $contents);

        return ['value' => $path];
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonValue(mixed $value): array
    {
        return is_array($value) ? $value : ['value' => $value];
    }

    /**
     * @return array<int, string>
     */
    private function brandingPathKeys(): array
    {
        return [
            'logo_path',
            'favicon_32_path',
            'favicon_192_path',
            'apple_touch_icon_path',
        ];
    }

    private function brandingFileEntry(string $key): string
    {
        return 'site/files/'.$key.'.png';
    }

    private function targetBrandingPath(string $key): string
    {
        $siteId = TenantContext::siteId();
        $base = $siteId ? 'sites/'.$siteId.'/cms' : 'cms';

        return match ($key) {
            'logo_path' => $base.'/branding/logo.png',
            'favicon_32_path' => $base.'/favicon/favicon-32x32.png',
            'favicon_192_path' => $base.'/favicon/favicon-192x192.png',
            'apple_touch_icon_path' => $base.'/favicon/apple-touch-icon.png',
            default => $base.'/branding/'.$key.'.png',
        };
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, int>
     */
    private function importMedia(ZipArchive $zip, string $prefix, array $options): array
    {
        $mediaItems = $this->listFromZip($zip, 'media/manifest.json', $options);
        $mappings = [];

        foreach ($mediaItems as $mediaData) {
            $importKey = $this->requiredImportKey($mediaData, 'media');
            $filePath = $this->stringValue($mediaData['file'] ?? null);
            $extension = Str::lower($this->stringValue($mediaData['extension'] ?? pathinfo($filePath, PATHINFO_EXTENSION)));

            if ($filePath === '' || ! str_starts_with($filePath, 'media/files/') || $zip->locateName($filePath) === false) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_file', ['path' => $filePath ?: 'media/files']),
                ]);
            }

            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_forbidden_file', ['path' => $filePath]),
                ]);
            }

            $contents = (string) $zip->getFromName($filePath);
            $hash = hash('sha256', $contents);
            $asset = CmsMediaAsset::query()->where('hash', $hash)->first();

            if (! $asset instanceof CmsMediaAsset) {
                [$width, $height] = $this->imageDimensionsFromString($contents);

                if ($width === null || $height === null) {
                    throw ValidationException::withMessages([
                        'starter_zip' => __('cms_admin_ui.validation.starter_zip_forbidden_file', ['path' => $filePath]),
                    ]);
                }

                $filename = 'media-'.Str::ulid().'.'.$extension;
                $path = $this->mediaDirectory().'/'.$filename;

                Storage::disk('public')->put($path, $contents);

                $asset = CmsMediaAsset::query()->create([
                    'folder_id' => null,
                    'uploaded_by' => null,
                    'disk' => 'public',
                    'visibility' => 'public',
                    'path' => $path,
                    'filename' => $filename,
                    'original_filename' => $this->nullableString($mediaData['original_filename'] ?? $mediaData['filename'] ?? $filename),
                    'mime_type' => $this->mediaMimeType($contents, $extension, $mediaData['mime_type'] ?? null),
                    'extension' => $extension,
                    'size_bytes' => strlen($contents),
                    'width' => $width,
                    'height' => $height,
                    'hash' => $hash,
                    'alt_text' => $this->nullableString($mediaData['alt_text'] ?? null),
                    'caption' => $this->nullableString($mediaData['caption'] ?? null),
                    'focal_point' => is_array($mediaData['focal_point'] ?? null) ? $mediaData['focal_point'] : null,
                    'metadata' => $this->mediaMetadata($mediaData, $prefix.$importKey),
                    'sort_order' => ((int) (CmsMediaAsset::query()->max('sort_order') ?? 0)) + 1,
                ]);
            }

            $this->importMediaTranslations($asset, (array) ($mediaData['translations'] ?? []));
            $mappings[$importKey] = (int) $asset->id;
        }

        return $mappings;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{download_groups: array<string, int>, download_folders: array<string, int>, downloads: array<string, int>}
     */
    private function importDownloads(ZipArchive $zip, string $prefix, array $options): array
    {
        $downloadData = $this->jsonFromZip($zip, 'downloads/manifest.json', $options);
        $groupMappings = [];
        $folderMappings = [];
        $downloadMappings = [];

        foreach ((array) ($downloadData['groups'] ?? []) as $groupData) {
            if (! is_array($groupData)) {
                continue;
            }

            $importKey = $this->requiredImportKey($groupData, 'download_group');
            $group = CmsDownloadGroup::query()->create([
                'name' => $this->stringValue($groupData['name'] ?? null, 'Download group'),
                'slug' => $this->uniqueDownloadGroupSlug($groupData['slug'] ?? $groupData['name'] ?? $importKey),
                'description' => $this->nullableString($groupData['description'] ?? null),
                'is_active' => false,
                'sort_order' => (int) ($groupData['sort_order'] ?? 0),
                'settings' => [
                    'site_package_import_key' => $prefix.$importKey,
                ],
            ]);
            $groupMappings[$importKey] = (int) $group->id;
        }

        foreach ((array) ($downloadData['folders'] ?? []) as $folderData) {
            if (! is_array($folderData)) {
                continue;
            }

            $importKey = $this->requiredImportKey($folderData, 'download_folder');
            $folder = CmsDownloadFolder::query()->create([
                'parent_id' => null,
                'name' => $this->stringValue($folderData['name'] ?? null, 'Download folder'),
                'slug' => $this->uniqueDownloadFolderSlug($folderData['slug'] ?? $folderData['name'] ?? $importKey, null),
                'access_mode' => $this->downloadAccessMode($folderData['access_mode'] ?? null),
                'password_hash' => null,
                'password_expires_minutes' => $this->nullableInteger($folderData['password_expires_minutes'] ?? null),
                'settings' => [
                    ...(is_array($folderData['settings'] ?? null) ? $folderData['settings'] : []),
                    'site_package_import_key' => $prefix.$importKey,
                    'parent_import_key' => $this->nullableString($folderData['parent_import_key'] ?? null),
                ],
                'sort_order' => (int) ($folderData['sort_order'] ?? 0),
            ]);
            $folderMappings[$importKey] = (int) $folder->id;
        }

        foreach ((array) ($downloadData['folders'] ?? []) as $folderData) {
            if (! is_array($folderData)) {
                continue;
            }

            $importKey = $this->requiredImportKey($folderData, 'download_folder');
            $folderId = $folderMappings[$importKey] ?? null;
            $parentImportKey = $this->nullableString($folderData['parent_import_key'] ?? null);
            $parentId = $parentImportKey ? ($folderMappings[$parentImportKey] ?? null) : null;

            if (! $folderId) {
                continue;
            }

            $folder = CmsDownloadFolder::query()->find($folderId);

            if (! $folder instanceof CmsDownloadFolder) {
                continue;
            }

            $folder->forceFill([
                'parent_id' => $parentId,
                'slug' => $this->uniqueDownloadFolderSlug($folder->slug, $parentId, (int) $folder->id),
            ])->save();
            $this->syncDownloadAccessRules('folder', (int) $folder->id, (array) ($folderData['access_rules'] ?? []), $groupMappings);
        }

        foreach ((array) ($downloadData['assets'] ?? []) as $assetData) {
            if (! is_array($assetData)) {
                continue;
            }

            $importKey = $this->requiredImportKey($assetData, 'download');
            $filePath = $this->stringValue($assetData['file'] ?? null);
            $extension = Str::lower($this->stringValue($assetData['extension'] ?? pathinfo($filePath, PATHINFO_EXTENSION)));

            if ($filePath === '' || ! str_starts_with($filePath, 'downloads/files/') || $zip->locateName($filePath) === false) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_file', ['path' => $filePath ?: 'downloads/files']),
                ]);
            }

            if (! in_array($extension, (array) config('cms_downloads.allowed_extensions', []), true)) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_forbidden_file', ['path' => $filePath]),
                ]);
            }

            $contents = (string) $zip->getFromName($filePath);
            $hash = hash('sha256', $contents);
            $disk = (string) config('cms_downloads.disk', 'private');
            $filename = $this->downloadFilename($assetData, $importKey, $extension);
            $folderImportKey = $this->nullableString($assetData['folder_import_key'] ?? null);
            $folderId = $folderImportKey ? ($folderMappings[$folderImportKey] ?? null) : null;

            $asset = CmsDownloadAsset::query()->create([
                'folder_id' => $folderId,
                'uploaded_by' => null,
                'disk' => $disk,
                'visibility' => 'protected',
                'access_mode' => $this->downloadAccessMode($assetData['access_mode'] ?? null),
                'path' => $this->downloadDirectory().'/pending/'.Str::ulid().'.'.$extension,
                'filename' => $filename,
                'original_filename' => $this->nullableString($assetData['original_filename'] ?? $assetData['filename'] ?? $filename),
                'mime_type' => $this->downloadMimeType($contents, $extension, $assetData['mime_type'] ?? null),
                'extension' => $extension,
                'size_bytes' => strlen($contents),
                'hash' => $hash,
                'title' => $this->nullableString($assetData['title'] ?? null),
                'description' => $this->nullableString($assetData['description'] ?? null),
                'published_at' => $this->nullableDateString($assetData['published_at'] ?? null),
                'expires_at' => $this->nullableDateString($assetData['expires_at'] ?? null),
                'metadata' => [
                    ...(is_array($assetData['metadata'] ?? null) ? $assetData['metadata'] : []),
                    'site_package_import_key' => $prefix.$importKey,
                ],
                'sort_order' => (int) ($assetData['sort_order'] ?? 0),
            ]);
            $path = $this->downloadDirectory().'/assets/'.$asset->id.'/'.$filename;
            Storage::disk($disk)->put($path, $contents);
            $asset->forceFill(['path' => $path])->save();

            $this->importDownloadTranslations($asset, (array) ($assetData['translations'] ?? []));
            $this->syncDownloadAccessRules('asset', (int) $asset->id, (array) ($assetData['access_rules'] ?? []), $groupMappings);
            $downloadMappings[$importKey] = (int) $asset->id;
        }

        return [
            'download_groups' => $groupMappings,
            'download_folders' => $folderMappings,
            'downloads' => $downloadMappings,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, int>
     */
    private function importThemes(ZipArchive $zip, string $prefix, array $options): array
    {
        $themes = $this->listFromZip($zip, 'themes/manifest.json', $options);
        $mappings = [];

        foreach ($themes as $themeData) {
            $importKey = $this->requiredImportKey($themeData, 'theme');
            $theme = CmsTheme::query()->create([
                'import_key' => $prefix.$importKey,
                'name' => $this->stringValue($themeData['name'] ?? null, 'Site package theme'),
                'key' => $this->uniqueThemeKey($themeData['key'] ?? $themeData['name'] ?? $importKey),
                'description' => $this->nullableString($themeData['description'] ?? null),
                'author' => $this->nullableString($themeData['author'] ?? null),
                'version' => $this->stringValue($themeData['version'] ?? null, '1.0.0'),
                'status' => 'draft',
                'is_active' => false,
                'active_version_id' => null,
                'created_by' => null,
                'updated_by' => null,
            ]);

            foreach ((array) ($themeData['versions'] ?? []) as $versionData) {
                if (! is_array($versionData)) {
                    continue;
                }

                $developerCss = $this->stringFromZip($zip, $this->stringValue($versionData['developer_css_file'] ?? null));

                if ($developerCss === '') {
                    $developerCss = $this->stringFromZip($zip, $this->stringValue($versionData['theme_css_file'] ?? null));
                }

                if ($developerCss === '') {
                    continue;
                }

                $this->compileThemeCss->handle(
                    $theme,
                    $developerCss,
                    is_array($versionData['settings'] ?? null) ? $versionData['settings'] : [],
                    is_array($versionData['source_manifest'] ?? null) ? $versionData['source_manifest'] : [],
                    null,
                    $this->themeVersionHash($versionData['version_hash'] ?? null),
                );
            }

            $theme->forceFill(['active_version_id' => null])->save();
            $mappings[$importKey] = (int) $theme->id;
        }

        return $mappings;
    }

    private function uniqueThemeKey(mixed $source): string
    {
        $base = Str::slug($this->stringValue($source, 'theme')) ?: 'theme';
        $key = $base;
        $index = 2;

        while (CmsTheme::query()->where('key', $key)->exists()) {
            $key = $base.'-'.$index;
            $index++;
        }

        return $key;
    }

    private function themeVersionHash(mixed $hash): ?string
    {
        $hash = $this->stringValue($hash);

        return preg_match('/^[a-f0-9]{16,64}$/i', $hash) === 1 ? $hash : null;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function imageDimensionsFromString(string $contents): array
    {
        $dimensions = @getimagesizefromstring($contents);

        if (! is_array($dimensions)) {
            return [null, null];
        }

        return [(int) ($dimensions[0] ?? 0), (int) ($dimensions[1] ?? 0)];
    }

    private function mediaMimeType(string $contents, string $extension, mixed $fallback): string
    {
        $mimeType = function_exists('finfo_buffer')
            ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents)
            : false;

        if (is_string($mimeType) && str_starts_with($mimeType, 'image/')) {
            return $mimeType;
        }

        return $this->stringValue($fallback, 'image/'.$extension);
    }

    /**
     * @param  array<string, mixed>  $mediaData
     * @return array<string, mixed>
     */
    private function mediaMetadata(array $mediaData, string $importKey): array
    {
        $metadata = is_array($mediaData['metadata'] ?? null) ? $mediaData['metadata'] : [];
        $metadata['site_package_import_key'] = $importKey;

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    private function importMediaTranslations(CmsMediaAsset $asset, array $translations): void
    {
        foreach ($translations as $locale => $translation) {
            if (! is_array($translation)) {
                continue;
            }

            $locale = $this->localeValue($locale);
            $asset->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'alt_text' => $this->nullableString($translation['alt_text'] ?? null),
                    'caption' => $this->nullableString($translation['caption'] ?? null),
                ],
            );
        }
    }

    private function mediaDirectory(): string
    {
        $siteId = TenantContext::siteId();

        if (! $siteId) {
            return 'cms/media';
        }

        return 'sites/'.$siteId.'/cms/media';
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    private function importDownloadTranslations(CmsDownloadAsset $asset, array $translations): void
    {
        foreach ($translations as $locale => $translation) {
            if (! is_array($translation)) {
                continue;
            }

            $asset->translations()->updateOrCreate(
                ['locale' => $this->localeValue($locale)],
                [
                    'title' => $this->nullableString($translation['title'] ?? null),
                    'description' => $this->nullableString($translation['description'] ?? null),
                ],
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @param  array<string, int>  $downloadGroupMappings
     */
    private function syncDownloadAccessRules(string $subjectType, int $subjectId, array $rules, array $downloadGroupMappings): void
    {
        foreach (array_values($rules) as $index => $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $ruleType = $this->stringValue($rule['rule_type'] ?? null);
            $groupId = null;

            if ($ruleType === 'download_group') {
                $groupImportKey = $this->stringValue($rule['download_group_import_key'] ?? null);
                $groupId = $downloadGroupMappings[$groupImportKey] ?? null;

                if (! $groupId) {
                    continue;
                }
            }

            if (! in_array($ruleType, ['download_group', 'profile_field'], true)) {
                continue;
            }

            CmsDownloadAccessRule::query()->create([
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'rule_type' => $ruleType,
                'site_user_id' => null,
                'cms_download_group_id' => $groupId,
                'profile_field_key' => $this->nullableString($rule['profile_field_key'] ?? null),
                'operator' => $this->nullableString($rule['operator'] ?? null),
                'value' => is_array($rule['value'] ?? null) ? array_values($rule['value']) : (filled($rule['value'] ?? null) ? [(string) $rule['value']] : null),
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }

    private function downloadFilename(array $assetData, string $importKey, string $extension): string
    {
        $source = $this->stringValue($assetData['filename'] ?? $assetData['original_filename'] ?? $importKey, 'download');
        $base = Str::slug(pathinfo($source, PATHINFO_FILENAME)) ?: 'download';

        return $base.'-'.Str::lower(Str::random(10)).'.'.$extension;
    }

    private function downloadMimeType(string $contents, string $extension, mixed $fallback): string
    {
        $mimeType = function_exists('finfo_buffer')
            ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents)
            : false;

        return is_string($mimeType) && $mimeType !== ''
            ? $mimeType
            : $this->stringValue($fallback, 'application/octet-stream');
    }

    private function downloadDirectory(): string
    {
        $siteId = TenantContext::siteId();
        $base = trim((string) config('cms_downloads.directory', 'cms/downloads'), '/');

        return $base.'/site-'.($siteId ?: 0);
    }

    private function downloadAccessMode(mixed $value): string
    {
        $mode = $this->stringValue($value, 'inherit');

        return in_array($mode, ['inherit', 'public', 'authenticated', 'restricted', 'password'], true)
            ? $mode
            : 'inherit';
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(1, (int) $value);
    }

    private function uniqueDownloadGroupSlug(mixed $source): string
    {
        $base = Str::slug($this->stringValue($source, 'download-group')) ?: 'download-group';
        $slug = $base;
        $index = 2;

        while (CmsDownloadGroup::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index;
            $index++;
        }

        return $slug;
    }

    private function uniqueDownloadFolderSlug(mixed $source, ?int $parentId, ?int $ignoreId = null): string
    {
        $base = Str::slug($this->stringValue($source, 'folder')) ?: 'folder';
        $slug = $base;
        $index = 2;

        while (CmsDownloadFolder::query()
            ->where('parent_id', $parentId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$index;
            $index++;
        }

        return $slug;
    }

    /**
     * @return array<string, int>
     */
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, int>
     */
    /**
     * @param  array<string, int>  $mediaMappings
     * @param  array<string, string>  $formMappings
     */
    private function importLayouts(ZipArchive $zip, string $prefix, array $options, array $mediaMappings = [], array $formMappings = [], array $menuMappings = [], array $downloadMappings = [], array $downloadFolderMappings = []): array
    {
        $layouts = $this->listFromZip($zip, 'layouts.json', $options);
        $mappings = [];

        foreach ($layouts as $layoutData) {
            $importKey = $this->requiredImportKey($layoutData, 'layout');
            $layout = CmsLayout::query()->firstOrNew(['import_key' => $prefix.$importKey]);
            $settings = is_array($layoutData['settings'] ?? null) ? $layoutData['settings'] : [];

            if (($options['import_prefix'] ?? 'starter') === 'site-package') {
                $settings['site_package_was_default'] = (bool) ($layoutData['is_default'] ?? false);
            }
            $layout->fill([
                'name' => $this->stringValue($layoutData['name'] ?? null, 'Layout'),
                'locale' => $this->localeValue($layoutData['locale'] ?? null),
                'is_default' => false,
                'is_active' => false,
                'cache_strategy' => $this->cacheStrategy($layoutData['cache_strategy'] ?? null),
                'settings' => $this->htmlAnchorAction->handle(
                    $layout,
                    $settings,
                    [$layoutData['name'] ?? null, $layoutData['locale'] ?? null, 'layout'],
                ),
            ])->save();

            if (blank($layout->translation_key)) {
                $layout->forceFill(['translation_key' => (string) Str::ulid()])->save();
            }

            $sections = is_array($layoutData['sections'] ?? null) ? $layoutData['sections'] : [];
            $sections = $this->remapSectionReferences($sections, $mediaMappings, $formMappings, $menuMappings, $downloadMappings, $downloadFolderMappings);
            $this->validateSections($sections, $this->blockRegistry->layoutZones(), false, [], 'layout '.$importKey, $options);
            $this->saveLayoutSections->handle($layout, $sections);

            $mappings[$importKey] = (int) $layout->id;
        }

        return $mappings;
    }

    /**
     * @param  array<string, int>  $layoutMappings
     * @return array<string, int>
     */
    /**
     * @param  array<string, int>  $layoutMappings
     * @param  array<string, mixed>  $options
     * @return array<string, int>
     */
    /**
     * @param  array<string, int>  $mediaMappings
     * @param  array<string, string>  $formMappings
     */
    private function importTemplates(ZipArchive $zip, string $prefix, array $layoutMappings, array $options, array $mediaMappings = [], array $formMappings = [], array $menuMappings = [], array $downloadMappings = [], array $downloadFolderMappings = []): array
    {
        $templates = $this->listFromZip($zip, 'templates.json', $options);
        $mappings = [];

        foreach ($templates as $templateData) {
            $importKey = $this->requiredImportKey($templateData, 'template');
            $layoutImportKey = $this->stringValue($templateData['layout_import_key'] ?? null);
            $layoutId = $layoutMappings[$layoutImportKey] ?? null;

            if (! is_int($layoutId)) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_layout_mapping', ['key' => $layoutImportKey]),
                ]);
            }

            $templateClass = $this->templateClass($templateData['template_class'] ?? null);
            $templateKey = $this->templateKey($templateClass, $templateData['template_key'] ?? null);
            $dataContract = app(CmsTemplateDataContract::class)->normalize(
                is_array($templateData['data_contract'] ?? null) ? $templateData['data_contract'] : [],
                $templateKey,
            );
            $template = CmsTemplate::query()->firstOrNew(['import_key' => $prefix.$importKey]);
            $settings = is_array($templateData['settings'] ?? null) ? $templateData['settings'] : [];

            if (($options['import_prefix'] ?? 'starter') === 'site-package') {
                $settings['site_package_was_default'] = (bool) ($templateData['is_default'] ?? false);
            }
            $template->fill([
                'name' => $this->stringValue($templateData['name'] ?? null, 'Template'),
                'locale' => $this->localeValue($templateData['locale'] ?? null),
                'layout_id' => $layoutId,
                'template_class' => $templateClass,
                'template_key' => $templateKey,
                'is_default' => false,
                'is_active' => false,
                'cache_strategy' => $this->cacheStrategy($templateData['cache_strategy'] ?? null),
                'settings' => $settings,
                'data_contract' => $dataContract,
            ])->save();

            if (blank($template->translation_key)) {
                $template->forceFill(['translation_key' => (string) Str::ulid()])->save();
            }

            $sections = is_array($templateData['sections'] ?? null) ? $templateData['sections'] : [];
            $sections = $this->remapSectionReferences($sections, $mediaMappings, $formMappings, $menuMappings, $downloadMappings, $downloadFolderMappings);
            $this->validateSections(
                $sections,
                ['content'],
                true,
                $this->templateFieldKeys($templateKey, $dataContract),
                'template '.$importKey,
                $options,
            );
            $this->saveSections->handle($template, $sections, ['content']);

            $mappings[$importKey] = (int) $template->id;
        }

        return $mappings;
    }

    /**
     * @param  array<string, int>  $templateMappings
     * @return array<string, int>
     */
    /**
     * @param  array<string, int>  $templateMappings
     * @param  array<string, mixed>  $options
     * @return array<string, int>
     */
    /**
     * @param  array<string, int>  $mediaMappings
     * @param  array<string, string>  $formMappings
     */
    private function importPages(ZipArchive $zip, string $prefix, array $templateMappings, array $options, array $mediaMappings = [], array $formMappings = [], array $menuMappings = [], array $downloadMappings = [], array $downloadFolderMappings = []): array
    {
        $pages = $this->listFromZip($zip, 'pages.json', $options);
        $mappings = [];
        $translationKeys = $this->pageTranslationKeys($pages, $prefix, $options);

        foreach ($pages as $pageData) {
            $importKey = $this->requiredImportKey($pageData, 'page');
            $starterImportKey = $prefix.$importKey;
            $importMarkerKey = (string) ($options['import_marker_key'] ?? 'starter_import_key');
            $locale = $this->localeValue($pageData['locale'] ?? null);
            $templateId = $this->mappedId($templateMappings, $pageData['detail_template_import_key'] ?? null, 'template');
            $page = CmsPage::query()
                ->where('settings->'.$importMarkerKey, $starterImportKey)
                ->first() ?: new CmsPage;
            $settings = is_array($pageData['settings'] ?? null) ? $pageData['settings'] : [];
            $settings[$importMarkerKey] = $starterImportKey;
            $template = CmsTemplate::query()->find($templateId);

            if (($options['import_prefix'] ?? 'starter') === 'site-package') {
                $settings['site_package_was_home'] = (bool) ($pageData['is_home'] ?? false);
            }

            $page->fill([
                'parent_id' => null,
                'detail_template_id' => $templateId,
                'title' => $this->stringValue($pageData['title'] ?? null, 'Page'),
                'slug' => $page->exists
                    ? (string) $page->slug
                    : $this->uniquePageSlug($locale, $pageData['slug'] ?? $pageData['title'] ?? $importKey),
                'locale' => $locale,
                'translation_key' => $page->exists
                    ? (string) $page->translation_key
                    : $translationKeys[$this->translationGroupKey($pageData['translation_key'] ?? null)] ?? (string) Str::ulid(),
                'status' => 'draft',
                'template' => $this->stringValue($pageData['template'] ?? null),
                'short_description' => $this->nullableString($pageData['short_description'] ?? null),
                'content_blocks' => [],
                'template_data' => $this->templateData(
                    is_array($pageData['template_data'] ?? null) ? $pageData['template_data'] : [],
                    $template,
                    $mediaMappings,
                    $downloadMappings,
                    $downloadFolderMappings,
                ),
                'seo_title' => $this->nullableString($pageData['seo_title'] ?? null),
                'seo_description' => $this->nullableString($pageData['seo_description'] ?? null),
                'canonical_url' => $this->nullableString($pageData['canonical_url'] ?? null),
                'og_image_path' => $this->nullableString($pageData['og_image_path'] ?? null),
                'noindex' => (bool) ($pageData['noindex'] ?? false),
                'is_home' => false,
                'is_searchable' => (bool) ($pageData['is_searchable'] ?? true),
                'sort_order' => max((int) ($pageData['sort_order'] ?? 0), 0),
                'published_at' => null,
                'settings' => $settings,
            ])->save();

            if (blank($page->translation_key)) {
                $page->forceFill(['translation_key' => (string) Str::ulid()])->save();
            }

            $sections = is_array($pageData['sections'] ?? null) ? $pageData['sections'] : [];
            $sections = $this->remapSectionReferences($sections, $mediaMappings, $formMappings, $menuMappings, $downloadMappings, $downloadFolderMappings);
            $this->validateSections(
                $sections,
                ['content'],
                true,
                $this->templateFieldKeys((string) ($template?->template_key ?? ''), is_array($template?->data_contract) ? $template->data_contract : []),
                'page '.$importKey,
                $options,
            );
            $this->saveSections->handle($page, $sections, ['content']);

            $mappings[$importKey] = (int) $page->id;
        }

        $this->syncPageParents($pages, $mappings);
        $this->syncPageTranslations($pages, $mappings);

        return $mappings;
    }

    /**
     * @param  array<string, int>  $mediaMappings
     * @param  array<string, mixed>  $options
     * @return array<string, int>
     */
    private function importDocs(ZipArchive $zip, string $prefix, array $mediaMappings, array $options): array
    {
        $collections = $this->listFromZip($zip, 'docs.json', $options);
        $collectionMappings = [];
        $versionMappings = [];
        $pageMappings = [];
        $pageRows = [];
        $importMarkerKey = (string) ($options['import_marker_key'] ?? 'starter_import_key');

        foreach ($collections as $collectionData) {
            $collectionImportKey = $this->requiredImportKey($collectionData, 'doc collection');
            $collectionMarker = $prefix.$collectionImportKey;
            $collection = CmsDocCollection::query()
                ->where('settings->'.$importMarkerKey, $collectionMarker)
                ->first() ?: new CmsDocCollection;
            $collectionSettings = is_array($collectionData['settings'] ?? null) ? $collectionData['settings'] : [];
            $collectionSettings[$importMarkerKey] = $collectionMarker;

            $collection->fill([
                'name' => $this->stringValue($collectionData['name'] ?? null, 'Docs'),
                'slug' => $collection->exists
                    ? (string) $collection->slug
                    : $this->uniqueDocCollectionSlug($collectionData['slug'] ?? $collectionData['name'] ?? $collectionImportKey),
                'description' => $this->nullableString($collectionData['description'] ?? null),
                'is_active' => false,
                'sort_order' => max((int) ($collectionData['sort_order'] ?? 0), 0),
                'settings' => $collectionSettings,
            ])->save();

            $collectionMappings[$collectionImportKey] = (int) $collection->id;

            foreach ((array) ($collectionData['versions'] ?? []) as $versionData) {
                if (! is_array($versionData)) {
                    continue;
                }

                $versionImportKey = $this->requiredImportKey($versionData, 'doc version');
                $versionMarker = $prefix.$versionImportKey;
                $version = CmsDocVersion::query()
                    ->where('settings->'.$importMarkerKey, $versionMarker)
                    ->first() ?: new CmsDocVersion;
                $versionSettings = is_array($versionData['settings'] ?? null) ? $versionData['settings'] : [];
                $versionSettings[$importMarkerKey] = $versionMarker;

                $version->fill([
                    'cms_doc_collection_id' => $collection->id,
                    'label' => $this->stringValue($versionData['label'] ?? null, 'Latest'),
                    'slug' => $version->exists
                        ? (string) $version->slug
                        : $this->uniqueDocVersionSlug($collection, $versionData['slug'] ?? $versionData['label'] ?? $versionImportKey),
                    'is_default' => (bool) ($versionData['is_default'] ?? false),
                    'is_active' => false,
                    'sort_order' => max((int) ($versionData['sort_order'] ?? 0), 0),
                    'settings' => $versionSettings,
                ])->save();

                $versionMappings[$versionImportKey] = (int) $version->id;

                foreach ((array) ($versionData['pages'] ?? []) as $pageData) {
                    if (! is_array($pageData)) {
                        continue;
                    }

                    $pageImportKey = $this->requiredImportKey($pageData, 'doc page');
                    $locale = $this->localeValue($pageData['locale'] ?? null);
                    $body = $this->replaceMarkdownMediaImportKeys($this->nullableString($pageData['body'] ?? null), $mediaMappings);
                    $rendered = app(CmsDocsMarkdownRenderer::class)->render($body, $locale);
                    $pageMarker = $prefix.$pageImportKey;
                    $page = CmsDocPage::query()
                        ->where('settings->'.$importMarkerKey, $pageMarker)
                        ->first() ?: new CmsDocPage;
                    $pageSettings = is_array($pageData['settings'] ?? null) ? $pageData['settings'] : [];
                    $pageSettings[$importMarkerKey] = $pageMarker;

                    $page->fill([
                        'cms_doc_version_id' => $version->id,
                        'parent_id' => null,
                        'author_id' => null,
                        'title' => $this->stringValue($pageData['title'] ?? null, 'Docs page'),
                        'slug' => $page->exists
                            ? (string) $page->slug
                            : $this->uniqueDocPageSlug($version, $locale, $pageData['slug'] ?? $pageData['title'] ?? $pageImportKey),
                        'path' => $page->exists
                            ? (string) $page->path
                            : $this->uniqueDocPagePath($version, $locale, $pageData['path'] ?? $pageData['slug'] ?? $pageImportKey),
                        'locale' => $locale,
                        'translation_key' => $this->docTranslationKey($pageData['translation_key'] ?? null, $page->translation_key ?? null),
                        'translated_from_doc_page_id' => null,
                        'status' => 'draft',
                        'body_format' => 'markdown',
                        'body' => $body,
                        'plain_text' => $rendered['plain_text'],
                        'seo_title' => $this->nullableString($pageData['seo_title'] ?? null),
                        'seo_description' => $this->nullableString($pageData['seo_description'] ?? null),
                        'noindex' => (bool) ($pageData['noindex'] ?? false),
                        'sort_order' => max((int) ($pageData['sort_order'] ?? 0), 0),
                        'published_at' => null,
                        'settings' => $pageSettings,
                    ])->save();

                    $pageMappings[$pageImportKey] = (int) $page->id;
                    $pageRows[] = $pageData;
                }
            }
        }

        $this->syncDocPageParents($pageRows, $pageMappings);
        $this->syncDocPageTranslations($pageRows, $pageMappings);

        return $collectionMappings;
    }

    /**
     * @param  array<string, int>  $mediaMappings
     */
    private function replaceMarkdownMediaImportKeys(?string $markdown, array $mediaMappings): ?string
    {
        if ($markdown === null || $markdown === '') {
            return $markdown;
        }

        return (string) preg_replace_callback(
            '/!\[([^\]]*)\]\(media:([A-Za-z0-9_.-]+)\)/',
            function (array $matches) use ($mediaMappings): string {
                $mediaId = $mediaMappings[(string) $matches[2]] ?? null;

                if (! is_int($mediaId)) {
                    return (string) $matches[0];
                }

                return '!['.(string) $matches[1].'](media:'.$mediaId.')';
            },
            $markdown
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, int>  $mappings
     */
    private function syncDocPageParents(array $pages, array $mappings): void
    {
        foreach ($pages as $pageData) {
            $parentImportKey = $this->stringValue($pageData['parent_import_key'] ?? null);

            if ($parentImportKey === '') {
                continue;
            }

            $importKey = $this->requiredImportKey($pageData, 'doc page');
            $pageId = $mappings[$importKey] ?? null;
            $parentId = $mappings[$parentImportKey] ?? null;

            if (! is_int($pageId) || ! is_int($parentId) || $pageId === $parentId) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_mapping', ['type' => 'doc page', 'key' => $parentImportKey]),
                ]);
            }

            CmsDocPage::query()->whereKey($pageId)->update(['parent_id' => $parentId]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, int>  $mappings
     */
    private function syncDocPageTranslations(array $pages, array $mappings): void
    {
        foreach ($pages as $pageData) {
            $translatedFromImportKey = $this->stringValue($pageData['translated_from_import_key'] ?? null);

            if ($translatedFromImportKey === '') {
                continue;
            }

            $importKey = $this->requiredImportKey($pageData, 'doc page');
            $pageId = $mappings[$importKey] ?? null;
            $translatedFromPageId = $mappings[$translatedFromImportKey] ?? null;

            if (! is_int($pageId) || ! is_int($translatedFromPageId) || $pageId === $translatedFromPageId) {
                continue;
            }

            CmsDocPage::query()->whereKey($pageId)->update(['translated_from_doc_page_id' => $translatedFromPageId]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $mediaMappings
     * @return array<string, mixed>
     */
    private function templateData(array $data, ?CmsTemplate $template, array $mediaMappings, array $downloadMappings = [], array $downloadFolderMappings = []): array
    {
        if (! $template instanceof CmsTemplate) {
            return [];
        }

        $contract = app(CmsTemplateDataContract::class)->normalize($template->data_contract, (string) $template->template_key);

        foreach ($contract['template_fields'] as $field) {
            $type = (string) ($field['type'] ?? '');

            if (in_array($type, ['media', 'media_select'], true)) {
                $importKey = $this->stringValue(Arr::get($data, $field['key']));
                Arr::set($data, $field['key'], $importKey !== '' ? ($mediaMappings[$importKey] ?? null) : null);

                continue;
            }

            if (in_array($type, ['download', 'download_select'], true)) {
                $importKey = $this->stringValue(Arr::get($data, $field['key']));
                Arr::set($data, $field['key'], $importKey !== '' ? ($downloadMappings[$importKey] ?? null) : null);

                continue;
            }

            if ($type === 'download_folder_select') {
                $importKey = $this->stringValue(Arr::get($data, $field['key']));
                Arr::set($data, $field['key'], $importKey !== '' ? ($downloadFolderMappings[$importKey] ?? null) : null);
            }
        }

        return app(CmsTemplateDataContract::class)->cleanTemplateData($data, $template);
    }

    /**
     * @param  array<string, int>  $mappings
     */
    private function mappedId(array $mappings, mixed $importKey, string $type, bool $required = true): ?int
    {
        $importKey = $this->stringValue($importKey);

        if ($importKey === '') {
            return $required ? throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_mapping_key', ['type' => $type]),
            ]) : null;
        }

        $id = $mappings[$importKey] ?? null;

        if (! is_int($id)) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_mapping', ['type' => $type, 'key' => $importKey]),
            ]);
        }

        return $id;
    }

    /**
     * @param  array<string, int>  $mappings
     */
    private function optionalMappedId(array $mappings, mixed $importKey): ?int
    {
        $importKey = $this->stringValue($importKey);

        if ($importKey === '') {
            return null;
        }

        return $mappings[$importKey] ?? null;
    }

    /**
     * @param  array<string, int>  $mappings
     * @return array<int, int>
     */
    private function mappedIds(array $mappings, mixed $importKeys, string $type): array
    {
        if (! is_array($importKeys)) {
            return [];
        }

        return collect($importKeys)
            ->map(fn (mixed $importKey): int => $this->mappedId($mappings, $importKey, $type))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, int>  $mappings
     */
    private function syncPageParents(array $pages, array $mappings): void
    {
        foreach ($pages as $pageData) {
            $parentImportKey = $this->stringValue($pageData['parent_import_key'] ?? null);

            if ($parentImportKey === '') {
                continue;
            }

            $importKey = $this->requiredImportKey($pageData, 'page');
            $pageId = $mappings[$importKey] ?? null;
            $parentId = $mappings[$parentImportKey] ?? null;

            if (! is_int($pageId) || ! is_int($parentId) || $pageId === $parentId) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_mapping', ['type' => 'page', 'key' => $parentImportKey]),
                ]);
            }

            CmsPage::query()->whereKey($pageId)->update(['parent_id' => $parentId]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, int>  $mappings
     */
    private function syncPageTranslations(array $pages, array $mappings): void
    {
        foreach ($pages as $pageData) {
            $translatedFromImportKey = $this->stringValue($pageData['translated_from_import_key'] ?? null);

            if ($translatedFromImportKey === '') {
                continue;
            }

            $importKey = $this->requiredImportKey($pageData, 'page');
            $pageId = $mappings[$importKey] ?? null;
            $translatedFromPageId = $mappings[$translatedFromImportKey] ?? null;

            if (! is_int($pageId) || ! is_int($translatedFromPageId) || $pageId === $translatedFromPageId) {
                continue;
            }

            CmsPage::query()->whereKey($pageId)->update(['translated_from_page_id' => $translatedFromPageId]);
        }
    }

    /**
     * @return array<string, int>
     */
    private function importMenus(ZipArchive $zip, string $prefix, array $options): array
    {
        $menus = $this->listFromZip($zip, 'menus.json', $options);
        $mappings = [];

        foreach ($menus as $menuData) {
            $importKey = $this->requiredImportKey($menuData, 'menu');
            $starterImportKey = $prefix.$importKey;
            $importMarkerKey = (string) ($options['import_marker_key'] ?? 'starter_import_key');
            $menu = CmsMenu::query()
                ->where('settings->'.$importMarkerKey, $starterImportKey)
                ->first() ?: new CmsMenu;
            $settings = is_array($menuData['settings'] ?? null) ? $menuData['settings'] : [];
            $settings[$importMarkerKey] = $starterImportKey;

            $menu->fill([
                'title' => $this->stringValue($menuData['title'] ?? null, 'Starter menu'),
                'placements' => $this->menuPlacements($menuData['placements'] ?? []),
                'is_active' => false,
                'settings' => $settings,
            ])->save();

            $mappings[$importKey] = (int) $menu->id;
        }

        return $mappings;
    }

    /**
     * @param  array<string, int>  $pageMappings
     * @param  array<string, int>  $menuMappings
     * @param  array<string, mixed>  $options
     */
    private function importMenuItemsForMenus(ZipArchive $zip, string $prefix, array $pageMappings, array $menuMappings, array $options): void
    {
        foreach ($this->listFromZip($zip, 'menus.json', $options) as $menuData) {
            $importKey = $this->requiredImportKey($menuData, 'menu');
            $menuId = $menuMappings[$importKey] ?? null;

            if (! is_int($menuId)) {
                continue;
            }

            $menu = CmsMenu::query()->find($menuId);

            if (! $menu instanceof CmsMenu) {
                continue;
            }

            $itemMappings = $this->importMenuItems($menu, (array) ($menuData['items'] ?? []), $prefix, $pageMappings, $options);
            $this->syncMenuItemParents((array) ($menuData['items'] ?? []), $itemMappings);
        }
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<string, int>  $pageMappings
     * @return array<string, int>
     */
    /**
     * @param  array<string, mixed>  $options
     */
    private function importMenuItems(CmsMenu $menu, array $items, string $prefix, array $pageMappings, array $options): array
    {
        $mappings = [];

        foreach (array_values($items) as $index => $itemData) {
            if (! is_array($itemData)) {
                continue;
            }

            $importKey = $this->requiredImportKey($itemData, 'menu item');
            $starterImportKey = $prefix.$importKey;
            $importMarkerKey = (string) ($options['import_marker_key'] ?? 'starter_import_key');
            $type = $this->menuItemType($itemData['type'] ?? null);
            $item = CmsMenuItem::query()
                ->where('cms_menu_id', $menu->id)
                ->where('metadata->'.$importMarkerKey, $starterImportKey)
                ->first() ?: new CmsMenuItem(['cms_menu_id' => $menu->id]);
            $metadata = is_array($itemData['metadata'] ?? null) ? $itemData['metadata'] : [];
            $metadata[$importMarkerKey] = $starterImportKey;
            $pageId = $type === 'page'
                ? $this->mappedId($pageMappings, $itemData['page_import_key'] ?? null, 'page')
                : null;

            $item->fill([
                'cms_menu_id' => $menu->id,
                'locale' => $this->localeValue($itemData['locale'] ?? null),
                'parent_id' => null,
                'cms_page_id' => $pageId,
                'cms_post_id' => null,
                'type' => $type,
                'label' => $this->menuItemLabel($itemData, $type),
                'translation_key' => $item->translation_key ?: $this->formTranslationKeyValue($itemData['translation_key'] ?? null, (string) Str::ulid()),
                'url' => in_array($type, ['custom', 'external'], true) ? $this->menuItemUrl($itemData, $type) : null,
                'target' => in_array($itemData['target'] ?? null, ['_self', '_blank'], true) ? $itemData['target'] : null,
                'rel' => $this->nullableString($itemData['rel'] ?? null),
                'sort_order' => max((int) ($itemData['sort_order'] ?? $index), 0),
                'is_active' => (bool) ($itemData['is_active'] ?? true),
                'metadata' => $metadata,
            ])->save();

            $mappings[$importKey] = (int) $item->id;
        }

        return $mappings;
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<string, int>  $mappings
     */
    private function syncMenuItemParents(array $items, array $mappings): void
    {
        foreach ($items as $itemData) {
            if (! is_array($itemData)) {
                continue;
            }

            $parentImportKey = $this->stringValue($itemData['parent_import_key'] ?? null);

            if ($parentImportKey === '') {
                continue;
            }

            $importKey = $this->requiredImportKey($itemData, 'menu item');
            $itemId = $mappings[$importKey] ?? null;
            $parentId = $mappings[$parentImportKey] ?? null;

            if (! is_int($itemId) || ! is_int($parentId) || $itemId === $parentId) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_mapping', ['type' => 'menu item', 'key' => $parentImportKey]),
                ]);
            }

            CmsMenuItem::query()->whereKey($itemId)->update(['parent_id' => $parentId]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @param  array<string, int>  $mappings
     */
    private function syncCategoryRelations(array $categories, array $mappings): void
    {
        foreach ($categories as $categoryData) {
            $importKey = $this->requiredImportKey($categoryData, 'category');
            $categoryId = $mappings[$importKey] ?? null;

            if (! is_int($categoryId)) {
                continue;
            }

            $updates = [];
            $parentImportKey = $this->stringValue($categoryData['parent_import_key'] ?? null);

            if ($parentImportKey !== '') {
                $parentId = $mappings[$parentImportKey] ?? null;

                if (! is_int($parentId) || $parentId === $categoryId) {
                    throw ValidationException::withMessages([
                        'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_mapping', ['type' => 'category', 'key' => $parentImportKey]),
                    ]);
                }

                $updates['parent_id'] = $parentId;
            }

            $translatedFromImportKey = $this->stringValue($categoryData['translated_from_import_key'] ?? null);

            if ($translatedFromImportKey !== '') {
                $translatedFromId = $mappings[$translatedFromImportKey] ?? null;

                if (! is_int($translatedFromId) || $translatedFromId === $categoryId) {
                    throw ValidationException::withMessages([
                        'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_mapping', ['type' => 'category', 'key' => $translatedFromImportKey]),
                    ]);
                }

                $updates['translated_from_category_id'] = $translatedFromId;
            }

            if ($updates !== []) {
                CmsCategory::query()->whereKey($categoryId)->update($updates);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $tags
     * @param  array<string, int>  $mappings
     */
    private function syncTagRelations(array $tags, array $mappings): void
    {
        foreach ($tags as $tagData) {
            $translatedFromImportKey = $this->stringValue($tagData['translated_from_import_key'] ?? null);

            if ($translatedFromImportKey === '') {
                continue;
            }

            $importKey = $this->requiredImportKey($tagData, 'tag');
            $tagId = $mappings[$importKey] ?? null;
            $translatedFromId = $mappings[$translatedFromImportKey] ?? null;

            if (! is_int($tagId) || ! is_int($translatedFromId) || $tagId === $translatedFromId) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_mapping', ['type' => 'tag', 'key' => $translatedFromImportKey]),
                ]);
            }

            CmsTag::query()->whereKey($tagId)->update(['translated_from_tag_id' => $translatedFromId]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $posts
     * @param  array<string, int>  $mappings
     */
    private function syncPostRelations(array $posts, array $mappings): void
    {
        foreach ($posts as $postData) {
            $translatedFromImportKey = $this->stringValue($postData['translated_from_import_key'] ?? null);

            if ($translatedFromImportKey === '') {
                continue;
            }

            $importKey = $this->requiredImportKey($postData, 'post');
            $postId = $mappings[$importKey] ?? null;
            $translatedFromId = $mappings[$translatedFromImportKey] ?? null;

            if (! is_int($postId) || ! is_int($translatedFromId) || $postId === $translatedFromId) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_mapping', ['type' => 'post', 'key' => $translatedFromImportKey]),
                ]);
            }

            CmsPost::query()->whereKey($postId)->update(['translated_from_post_id' => $translatedFromId]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $forms
     * @param  array<string, string>  $mappings
     * @param  array<string, mixed>  $options
     */
    private function syncFormRelations(array $forms, array &$mappings, string $prefix, array $options): void
    {
        $importMarkerKey = (string) ($options['import_marker_key'] ?? 'starter_import_key');

        foreach ($forms as $formData) {
            $translatedFromImportKey = $this->stringValue($formData['translated_from_import_key'] ?? null);

            if ($translatedFromImportKey === '') {
                continue;
            }

            $importKey = $this->requiredImportKey($formData, 'form');
            $form = CmsForm::query()
                ->where('settings->'.$importMarkerKey, $prefix.$importKey)
                ->first();
            $translatedFrom = CmsForm::query()
                ->where('settings->'.$importMarkerKey, $prefix.$translatedFromImportKey)
                ->first();

            if (! $form instanceof CmsForm || ! $translatedFrom instanceof CmsForm || $form->is($translatedFrom)) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_mapping', ['type' => 'form', 'key' => $translatedFromImportKey]),
                ]);
            }

            $form->forceFill([
                'translation_key' => $mappings[$translatedFromImportKey] ?? $form->translation_key,
                'translated_from_form_id' => $translatedFrom->id,
            ])->save();
            $mappings[$importKey] = (string) $form->translation_key;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $forms
     * @param  array<string, array<string, int>>  $fieldMappings
     */
    private function syncFormFieldRelations(array $forms, array $fieldMappings): void
    {
        foreach ($forms as $formData) {
            $formImportKey = $this->requiredImportKey($formData, 'form');

            foreach ((array) ($formData['fields'] ?? []) as $fieldData) {
                if (! is_array($fieldData)) {
                    continue;
                }

                $translatedFromImportKey = $this->stringValue($fieldData['translated_from_import_key'] ?? null);

                if ($translatedFromImportKey === '') {
                    continue;
                }

                $importKey = $this->requiredImportKey($fieldData, 'form field');
                $fieldId = $fieldMappings[$formImportKey][$importKey] ?? null;
                $translatedFromId = $this->mappedFormFieldId($fieldMappings, $translatedFromImportKey);

                if (! is_int($fieldId) || ! is_int($translatedFromId) || $fieldId === $translatedFromId) {
                    throw ValidationException::withMessages([
                        'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_mapping', ['type' => 'form field', 'key' => $translatedFromImportKey]),
                    ]);
                }

                CmsFormField::query()->whereKey($fieldId)->update(['translated_from_form_field_id' => $translatedFromId]);
            }
        }
    }

    /**
     * @param  array<string, array<string, int>>  $fieldMappings
     */
    private function mappedFormFieldId(array $fieldMappings, string $fieldImportKey): ?int
    {
        foreach ($fieldMappings as $formFieldMappings) {
            $fieldId = $formFieldMappings[$fieldImportKey] ?? null;

            if (is_int($fieldId)) {
                return $fieldId;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    private function listFromZip(ZipArchive $zip, string $name, array $options): array
    {
        return collect($this->jsonFromZip($zip, $name, $options))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->all();
    }

    private function stringFromZip(ZipArchive $zip, string $name): string
    {
        if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\')) {
            return '';
        }

        $contents = $zip->getFromName($name);

        return $contents === false ? '' : (string) $contents;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requiredImportKey(array $data, string $type): string
    {
        $importKey = $this->stringValue($data['import_key'] ?? null);

        if ($importKey === '') {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_import_key', ['type' => $type]),
            ]);
        }

        return $importKey;
    }

    /**
     * @param  array<string, mixed>  $sections
     * @param  array<string, int>  $mediaMappings
     * @param  array<string, string>  $formMappings
     * @param  array<string, int>  $menuMappings
     * @return array<string, mixed>
     */
    private function remapSectionReferences(array $sections, array $mediaMappings, array $formMappings = [], array $menuMappings = [], array $downloadMappings = [], array $downloadFolderMappings = []): array
    {
        foreach ($sections as $zone => $zoneSections) {
            foreach ((array) $zoneSections as $sectionIndex => $section) {
                foreach ((array) Arr::get($section, 'placements', []) as $placementIndex => $placement) {
                    $sections[$zone][$sectionIndex]['placements'][$placementIndex] = $this->remapPlacementReferences(
                        (array) $placement,
                        $mediaMappings,
                        $formMappings,
                        $menuMappings,
                        $downloadMappings,
                        $downloadFolderMappings,
                    );
                }
            }
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $placement
     * @param  array<string, int>  $mediaMappings
     * @param  array<string, string>  $formMappings
     * @param  array<string, int>  $menuMappings
     * @return array<string, mixed>
     */
    private function remapPlacementReferences(array $placement, array $mediaMappings, array $formMappings = [], array $menuMappings = [], array $downloadMappings = [], array $downloadFolderMappings = []): array
    {
        $block = (array) Arr::get($placement, 'block', []);
        $placement['block'] = $this->blockPackageMapper->importBlockContent(
            (string) ($block['type'] ?? 'text'),
            $block,
            $mediaMappings,
            formMappings: $formMappings,
            menuMappings: $menuMappings,
            downloadMappings: $downloadMappings,
            downloadFolderMappings: $downloadFolderMappings,
        );

        foreach ((array) ($placement['slots'] ?? []) as $slotKey => $slot) {
            foreach ((array) Arr::get((array) $slot, 'placements', []) as $childIndex => $childPlacement) {
                $placement['slots'][$slotKey]['placements'][$childIndex] = $this->remapPlacementReferences(
                    (array) $childPlacement,
                    $mediaMappings,
                    $formMappings,
                    $menuMappings,
                    $downloadMappings,
                    $downloadFolderMappings,
                );
            }
        }

        return $placement;
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @param  array<string, int>  $mediaMappings
     * @param  array<string, int>  $categoryMappings
     * @param  array<string, int>  $tagMappings
     * @param  array<string, string>  $formMappings
     * @return array<int, array<string, mixed>>
     */
    private function remapContentBlocks(array $blocks, array $mediaMappings, array $categoryMappings, array $tagMappings, array $formMappings = [], array $downloadMappings = [], array $downloadFolderMappings = []): array
    {
        return collect($blocks)
            ->filter(fn (mixed $block): bool => is_array($block))
            ->map(function (array $block) use ($mediaMappings, $categoryMappings, $tagMappings, $formMappings, $downloadMappings, $downloadFolderMappings): array {
                return $this->blockPackageMapper->importBlockContent(
                    (string) ($block['type'] ?? 'text'),
                    $block,
                    $mediaMappings,
                    $categoryMappings,
                    $tagMappings,
                    $formMappings,
                    downloadMappings: $downloadMappings,
                    downloadFolderMappings: $downloadFolderMappings,
                );
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $sections
     * @param  array<int, string>  $allowedZones
     * @param  array<int, string>  $allowedFieldKeys
     */
    /**
     * @param  array<string, mixed>  $options
     */
    private function validateSections(array $sections, array $allowedZones, bool $contentOnly, array $allowedFieldKeys, string $context, array $options): void
    {
        foreach ($sections as $zone => $zoneSections) {
            if (! in_array($zone, $allowedZones, true)) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_forbidden_zone', ['zone' => (string) $zone]),
                ]);
            }

            foreach ((array) $zoneSections as $section) {
                foreach ((array) Arr::get($section, 'placements', []) as $placement) {
                    $this->validatePlacement((array) $placement, (string) $zone, $contentOnly, $allowedFieldKeys, $context, $options, 0);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $placement
     * @param  array<int, string>  $allowedFieldKeys
     * @param  array<string, mixed>  $options
     */
    private function validatePlacement(array $placement, string $zone, bool $contentOnly, array $allowedFieldKeys, string $context, array $options, int $depth): void
    {
        $block = (array) Arr::get($placement, 'block', []);
        $type = (string) ($block['type'] ?? '');

        if ($type === '') {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_block_type', ['context' => $context]),
            ]);
        }

        $allowed = $contentOnly || $zone === 'slot'
            ? in_array($type, $this->blockRegistry->contentTypeKeys(), true)
            : $this->blockRegistry->isAllowedForZone($type, $zone);

        if (! $allowed) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_forbidden_block_type', ['type' => $type]),
            ]);
        }

        if (in_array($type, ['custom_head_code', 'custom_body_end_code'], true) && ! (bool) ($options['allow_code_blocks'] ?? false)) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_code_block_forbidden'),
            ]);
        }

        if ($type === 'dynamic_field' && ! in_array((string) ($block['field_key'] ?? ''), $allowedFieldKeys, true)) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_forbidden_template_field'),
            ]);
        }

        if ($depth >= 1 && ! empty($placement['slots'])) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.slot_nested_slots_forbidden'),
            ]);
        }

        foreach ((array) ($placement['slots'] ?? []) as $slotKey => $slot) {
            $slotDefinition = $this->slotDefinitionForBlock($type, (string) $slotKey);

            foreach ((array) Arr::get((array) $slot, 'placements', []) as $childPlacement) {
                $childType = (string) Arr::get((array) $childPlacement, 'block.type', '');

                if (! $this->slotAllowsBlock($slotDefinition, $childType)) {
                    throw ValidationException::withMessages([
                        'starter_zip' => __('cms_admin_ui.validation.slot_child_block_forbidden'),
                    ]);
                }

                $this->validatePlacement((array) $childPlacement, 'slot', true, $allowedFieldKeys, $context, $options, $depth + 1);
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function slotDefinitionForBlock(string $type, string $slotKey): ?array
    {
        $slot = collect((array) Arr::get($this->blockRegistry->definition($type), 'slots', []))
            ->first(fn (mixed $slot): bool => is_array($slot) && ($slot['key'] ?? null) === $slotKey);

        return is_array($slot) ? $slot : null;
    }

    /**
     * @param  array<string, mixed>|null  $slotDefinition
     */
    private function slotAllowsBlock(?array $slotDefinition, string $childType): bool
    {
        if (! is_array($slotDefinition) || $childType === '') {
            return false;
        }

        $allowedBlockKeys = array_map('strval', (array) ($slotDefinition['allowed_block_keys'] ?? []));

        return in_array($childType, $allowedBlockKeys, true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $options
     */
    private function validateContentBlocks(array $blocks, string $context, array $options): void
    {
        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? '');

            if ($type === '') {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_missing_block_type', ['context' => $context]),
                ]);
            }

            if (! in_array($type, $this->blockRegistry->contentTypeKeys(), true)) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_forbidden_block_type', ['type' => $type]),
                ]);
            }

            if (in_array($type, ['custom_head_code', 'custom_body_end_code'], true) && ! (bool) ($options['allow_code_blocks'] ?? false)) {
                throw ValidationException::withMessages([
                    'starter_zip' => __('cms_admin_ui.validation.starter_zip_code_block_forbidden'),
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @param  array<string, mixed>  $options
     * @return array<string, string>
     */
    private function categoryTranslationKeys(array $categories, string $prefix, array $options): array
    {
        $keys = [];
        $importMarkerKey = (string) ($options['import_marker_key'] ?? 'starter_import_key');

        foreach ($categories as $categoryData) {
            $sourceKey = $this->stringValue($categoryData['translation_key'] ?? null);
            $groupKey = $this->categoryTranslationGroupKey($categoryData['type'] ?? null, $sourceKey);

            if ($sourceKey === '' || isset($keys[$groupKey])) {
                continue;
            }

            $keys[$groupKey] = $this->categoryTranslationKeyHasConflict($categories, $sourceKey, $prefix, $importMarkerKey)
                ? (string) Str::ulid()
                : $sourceKey;
        }

        return $keys;
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     */
    private function categoryTranslationKeyHasConflict(array $categories, string $sourceKey, string $prefix, string $importMarkerKey): bool
    {
        foreach ($categories as $categoryData) {
            if ($this->stringValue($categoryData['translation_key'] ?? null) !== $sourceKey) {
                continue;
            }

            $importKey = $this->stringValue($categoryData['import_key'] ?? null);
            $type = $this->categoryType($categoryData['type'] ?? null);
            $locale = $this->localeValue($categoryData['locale'] ?? null);
            $query = CmsCategory::query()
                ->where('type', $type)
                ->where('translation_key', $sourceKey)
                ->where('locale', $locale);

            if ($importKey !== '') {
                $query->where(function ($query) use ($importMarkerKey, $importKey, $prefix): void {
                    $query
                        ->whereNull('settings->'.$importMarkerKey)
                        ->orWhere('settings->'.$importMarkerKey, '!=', $prefix.$importKey);
                });
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    private function categoryTranslationGroupKey(mixed $type, mixed $translationKey): string
    {
        return $this->categoryType($type).':'.$this->translationGroupKey($translationKey);
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, mixed>  $options
     * @return array<string, string>
     */
    private function pageTranslationKeys(array $pages, string $prefix, array $options): array
    {
        $keys = [];
        $importMarkerKey = (string) ($options['import_marker_key'] ?? 'starter_import_key');

        foreach ($pages as $pageData) {
            $sourceKey = $this->stringValue($pageData['translation_key'] ?? null);
            $groupKey = $this->translationGroupKey($sourceKey);

            if ($sourceKey === '' || isset($keys[$groupKey])) {
                continue;
            }

            $keys[$groupKey] = $this->pageTranslationKeyHasConflict($pages, $sourceKey, $prefix, $importMarkerKey)
                ? (string) Str::ulid()
                : $sourceKey;
        }

        return $keys;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     */
    private function pageTranslationKeyHasConflict(array $pages, string $sourceKey, string $prefix, string $importMarkerKey): bool
    {
        foreach ($pages as $pageData) {
            if ($this->stringValue($pageData['translation_key'] ?? null) !== $sourceKey) {
                continue;
            }

            $importKey = $this->stringValue($pageData['import_key'] ?? null);
            $locale = $this->localeValue($pageData['locale'] ?? null);
            $query = CmsPage::query()
                ->where('translation_key', $sourceKey)
                ->where('locale', $locale);

            if ($importKey !== '') {
                $query->where(function ($query) use ($importMarkerKey, $importKey, $prefix): void {
                    $query
                        ->whereNull('settings->'.$importMarkerKey)
                        ->orWhere('settings->'.$importMarkerKey, '!=', $prefix.$importKey);
                });
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tags
     * @param  array<string, mixed>  $options
     * @return array<string, string>
     */
    private function tagTranslationKeys(array $tags, string $prefix, array $options): array
    {
        $keys = [];
        $importMarkerKey = (string) ($options['import_marker_key'] ?? 'starter_import_key');

        foreach ($tags as $tagData) {
            $sourceKey = $this->stringValue($tagData['translation_key'] ?? null);
            $groupKey = $this->translationGroupKey($sourceKey);

            if ($sourceKey === '' || isset($keys[$groupKey])) {
                continue;
            }

            $keys[$groupKey] = $this->tagTranslationKeyHasConflict($tags, $sourceKey, $prefix, $importMarkerKey)
                ? (string) Str::ulid()
                : $sourceKey;
        }

        return $keys;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tags
     */
    private function tagTranslationKeyHasConflict(array $tags, string $sourceKey, string $prefix, string $importMarkerKey): bool
    {
        foreach ($tags as $tagData) {
            if ($this->stringValue($tagData['translation_key'] ?? null) !== $sourceKey) {
                continue;
            }

            $importKey = $this->stringValue($tagData['import_key'] ?? null);
            $locale = $this->localeValue($tagData['locale'] ?? null);
            $query = CmsTag::query()
                ->where('translation_key', $sourceKey)
                ->where('locale', $locale);

            if ($importKey !== '') {
                $query->where(function ($query) use ($importMarkerKey, $importKey, $prefix): void {
                    $query
                        ->whereNull('settings->'.$importMarkerKey)
                        ->orWhere('settings->'.$importMarkerKey, '!=', $prefix.$importKey);
                });
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $posts
     * @param  array<string, mixed>  $options
     * @return array<string, string>
     */
    private function postTranslationKeys(array $posts, string $prefix, array $options): array
    {
        $keys = [];
        $importMarkerKey = (string) ($options['import_marker_key'] ?? 'starter_import_key');

        foreach ($posts as $postData) {
            $sourceKey = $this->stringValue($postData['translation_key'] ?? null);
            $groupKey = $this->translationGroupKey($sourceKey);

            if ($sourceKey === '' || isset($keys[$groupKey])) {
                continue;
            }

            $keys[$groupKey] = $this->postTranslationKeyHasConflict($posts, $sourceKey, $prefix, $importMarkerKey)
                ? (string) Str::ulid()
                : $sourceKey;
        }

        return $keys;
    }

    /**
     * @param  array<int, array<string, mixed>>  $posts
     */
    private function postTranslationKeyHasConflict(array $posts, string $sourceKey, string $prefix, string $importMarkerKey): bool
    {
        foreach ($posts as $postData) {
            if ($this->stringValue($postData['translation_key'] ?? null) !== $sourceKey) {
                continue;
            }

            $importKey = $this->stringValue($postData['import_key'] ?? null);
            $locale = $this->localeValue($postData['locale'] ?? null);
            $query = CmsPost::query()
                ->where('translation_key', $sourceKey)
                ->where('locale', $locale);

            if ($importKey !== '') {
                $query->where(function ($query) use ($importMarkerKey, $importKey, $prefix): void {
                    $query
                        ->whereNull('settings->'.$importMarkerKey)
                        ->orWhere('settings->'.$importMarkerKey, '!=', $prefix.$importKey);
                });
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $forms
     * @param  array<string, mixed>  $options
     * @return array<string, string>
     */
    private function formTranslationKeys(array $forms, string $prefix, array $options): array
    {
        $keys = [];
        $importMarkerKey = (string) ($options['import_marker_key'] ?? 'starter_import_key');

        foreach ($forms as $formData) {
            $sourceKey = $this->formTranslationKeyValue($formData['translation_key'] ?? null);
            $groupKey = $this->translationGroupKey($sourceKey);

            if ($sourceKey === '' || isset($keys[$groupKey])) {
                continue;
            }

            $keys[$groupKey] = $this->formTranslationKeyHasConflict($forms, $sourceKey, $prefix, $importMarkerKey)
                ? (string) Str::ulid()
                : $sourceKey;
        }

        return $keys;
    }

    /**
     * @param  array<int, array<string, mixed>>  $forms
     */
    private function formTranslationKeyHasConflict(array $forms, string $sourceKey, string $prefix, string $importMarkerKey): bool
    {
        foreach ($forms as $formData) {
            if ($this->formTranslationKeyValue($formData['translation_key'] ?? null) !== $sourceKey) {
                continue;
            }

            $importKey = $this->stringValue($formData['import_key'] ?? null);
            $locale = $this->localeValue($formData['locale'] ?? null);
            $query = CmsForm::query()
                ->where('translation_key', $sourceKey)
                ->where('locale', $locale);

            if ($importKey !== '') {
                $query->where(function ($query) use ($importMarkerKey, $importKey, $prefix): void {
                    $query
                        ->whereNull('settings->'.$importMarkerKey)
                        ->orWhere('settings->'.$importMarkerKey, '!=', $prefix.$importKey);
                });
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    private function translationGroupKey(mixed $translationKey): string
    {
        return $this->stringValue($translationKey, (string) Str::ulid());
    }

    private function stringValue(mixed $value, string $fallback = ''): string
    {
        return is_scalar($value) && trim((string) $value) !== ''
            ? trim((string) $value)
            : $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return $value === '' ? null : $value;
    }

    private function nullableEmail(mixed $value): ?string
    {
        $email = $this->nullableString($value);

        if ($email === null) {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? null : $email;
    }

    private function formTranslationKeyValue(mixed $value, string $fallback = ''): string
    {
        $key = $this->stringValue($value, $fallback);

        if ($key === '' || strlen($key) > 32 || preg_match('/^[A-Za-z0-9_-]+$/', $key) !== 1) {
            return $fallback;
        }

        return $key;
    }

    private function uniquePageSlug(string $locale, mixed $source): string
    {
        $base = Str::slug($this->stringValue($source, 'pagina')) ?: 'pagina';
        $slug = $base;
        $index = 2;

        while (CmsPage::query()->where('locale', $locale)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index;
            $index++;
        }

        return $slug;
    }

    private function uniqueCategorySlug(string $type, string $locale, mixed $source): string
    {
        $base = Str::slug($this->stringValue($source, 'categorie')) ?: 'categorie';
        $slug = $base;
        $index = 2;

        while (CmsCategory::query()->where('type', $type)->where('locale', $locale)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index;
            $index++;
        }

        return $slug;
    }

    private function uniqueTagSlug(string $locale, mixed $source): string
    {
        $base = Str::slug($this->stringValue($source, 'tag')) ?: 'tag';
        $slug = $base;
        $index = 2;

        while (CmsTag::query()->where('locale', $locale)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index;
            $index++;
        }

        return $slug;
    }

    private function uniquePostSlug(string $locale, mixed $source): string
    {
        $base = Str::slug($this->stringValue($source, 'blog')) ?: 'blog';
        $slug = $base;
        $index = 2;

        while (CmsPost::query()->where('locale', $locale)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index;
            $index++;
        }

        return $slug;
    }

    private function uniqueDocCollectionSlug(mixed $source): string
    {
        $base = Str::slug($this->stringValue($source, 'docs')) ?: 'docs';
        $slug = $base;
        $index = 2;

        while (CmsDocCollection::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index;
            $index++;
        }

        return $slug;
    }

    private function uniqueDocVersionSlug(CmsDocCollection $collection, mixed $source): string
    {
        $base = Str::slug($this->stringValue($source, 'latest')) ?: 'latest';
        $slug = $base;
        $index = 2;

        while (CmsDocVersion::query()->where('cms_doc_collection_id', $collection->id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index;
            $index++;
        }

        return $slug;
    }

    private function uniqueDocPageSlug(CmsDocVersion $version, string $locale, mixed $source): string
    {
        $base = Str::slug($this->stringValue($source, 'page')) ?: 'page';
        $slug = $base;
        $index = 2;

        while (CmsDocPage::query()->where('cms_doc_version_id', $version->id)->where('locale', $locale)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index;
            $index++;
        }

        return $slug;
    }

    private function uniqueDocPagePath(CmsDocVersion $version, string $locale, mixed $source): string
    {
        $base = trim($this->stringValue($source, 'page'), '/');
        $base = collect(explode('/', $base))
            ->map(fn (string $segment): string => Str::slug($segment) ?: 'page')
            ->implode('/');
        $path = $base !== '' ? $base : 'page';
        $index = 2;

        while (CmsDocPage::query()->where('cms_doc_version_id', $version->id)->where('locale', $locale)->where('path', $path)->exists()) {
            $path = $base.'-'.$index;
            $index++;
        }

        return $path;
    }

    private function docTranslationKey(mixed $source, mixed $existing): string
    {
        $key = $this->stringValue($existing) ?: $this->stringValue($source);

        if ($key === '' || strlen($key) > 64 || preg_match('/^[A-Za-z0-9_-]+$/', $key) !== 1) {
            return (string) Str::ulid();
        }

        return $key;
    }

    /**
     * @return array<int, string>
     */
    private function menuPlacements(mixed $placements): array
    {
        $allowed = array_keys((array) config('cms_menus.placements', []));

        return collect(is_array($placements) ? $placements : [])
            ->filter(fn (mixed $placement): bool => is_string($placement) && in_array($placement, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    private function menuItemType(mixed $type): string
    {
        $type = $this->stringValue($type, 'custom');

        if (! in_array($type, ['custom', 'external', 'page'], true)) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_forbidden_menu_item_type', ['type' => $type]),
            ]);
        }

        return $type;
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    private function menuItemLabel(array $itemData, string $type): string
    {
        $label = $this->stringValue($itemData['label'] ?? null);

        if ($label !== '') {
            return $label;
        }

        if ($type === 'page') {
            return 'Pagina';
        }

        throw ValidationException::withMessages([
            'starter_zip' => __('cms_admin_ui.validation.starter_zip_menu_item_label_required'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    private function menuItemUrl(array $itemData, string $type): string
    {
        $url = $this->stringValue($itemData['url'] ?? null);

        if ($url === '') {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_menu_item_url_required'),
            ]);
        }

        if ($type === 'external' && preg_match('/^https?:\/\//i', $url) !== 1) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_menu_item_external_url_required'),
            ]);
        }

        if ($type === 'custom' && ! str_starts_with($url, '/') && preg_match('/^https?:\/\//i', $url) !== 1) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_menu_item_url_invalid'),
            ]);
        }

        return $url;
    }

    private function localeValue(mixed $value): string
    {
        $locale = $this->stringValue($value, 'nl');

        return preg_match('/^[a-z]{2}([_-][A-Z]{2})?$/', $locale) === 1 ? $locale : 'nl';
    }

    private function categoryType(mixed $value): string
    {
        $type = $this->stringValue($value, 'post');

        return $type === 'post' ? $type : 'post';
    }

    private function formFieldType(mixed $value): string
    {
        $type = $this->stringValue($value, 'text');

        return in_array($type, ['text', 'email', 'number', 'date', 'time', 'textarea', 'select', 'combobox', 'checkbox'], true)
            ? $type
            : 'text';
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function formFieldOptions(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        return collect($options)
            ->filter(fn (mixed $option): bool => is_array($option))
            ->map(function (array $option): ?array {
                $key = $this->stringValue($option['key'] ?? null);
                $label = $this->stringValue($option['label'] ?? null);

                if ($key === '' || $label === '' || preg_match('/^[A-Za-z0-9_-]+$/', $key) !== 1) {
                    return null;
                }

                return [
                    'key' => Str::limit($key, 80, ''),
                    'label' => Str::limit($label, 255, ''),
                ];
            })
            ->filter()
            ->unique('key')
            ->values()
            ->all();
    }

    private function cacheStrategy(mixed $value): string
    {
        return in_array($value, ['inherit', 'none', 'block', 'layout'], true) ? (string) $value : 'inherit';
    }

    private function templateClass(mixed $value): string
    {
        $templateClass = $this->stringValue($value);

        if (! in_array($templateClass, $this->templateRegistry->classKeys(), true)) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_forbidden_template_class', ['class' => $templateClass]),
            ]);
        }

        return $templateClass;
    }

    private function templateKey(string $templateClass, mixed $value): string
    {
        $templateKey = $this->stringValue($value);

        if (! $this->templateRegistry->isValidTemplateKey($templateKey, $templateClass)) {
            throw ValidationException::withMessages([
                'starter_zip' => __('cms_admin_ui.validation.starter_zip_forbidden_template_type', ['type' => $templateKey]),
            ]);
        }

        return $templateKey;
    }

    /**
     * @return array<int, string>
     */
    private function templateFieldKeys(string $templateKey, array $dataContract = []): array
    {
        $contract = app(CmsTemplateDataContract::class)->normalize($dataContract, $templateKey);

        return collect($contract['system_fields'])
            ->filter(fn (array $field): bool => (bool) ($field['enabled'] ?? false))
            ->pluck('key')
            ->merge(collect($contract['template_fields'])->pluck('key')->map(fn (string $key): string => 'template.'.$key))
            ->values()
            ->all();
    }
}
