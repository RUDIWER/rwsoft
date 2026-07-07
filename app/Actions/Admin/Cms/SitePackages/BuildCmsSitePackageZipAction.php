<?php

namespace App\Actions\Admin\Cms\SitePackages;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
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
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use App\Support\Cms\CmsBlockPackageMapper;
use App\Support\Cms\CmsTemplateDataContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class BuildCmsSitePackageZipAction
{
    public function __construct(private readonly CmsBlockPackageMapper $blockPackageMapper) {}

    /**
     * @param  array{package_key?: string, package_name?: string, modules?: array<int, string>}  $input
     * @return array{path: string, filename: string, key: string}
     */
    public function handle(array $input): array
    {
        $modules = $this->modules((array) ($input['modules'] ?? config('cms_site_packages.implemented_modules', [])));
        $key = $this->packageKey((string) ($input['package_key'] ?? ''));
        $name = trim((string) ($input['package_name'] ?? '')) ?: 'CMS site package';
        $filename = $key.'.zip';
        $directory = storage_path('app/site-package-exports');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/'.Str::uuid().'-'.$filename;
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(__('cms_admin_ui.flash.site_package_zip_create_failed'));
        }

        foreach ($this->entries($key, $name, $modules) as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }

        foreach ($this->siteFiles($modules) as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }

        foreach ($this->mediaFiles($modules) as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }

        foreach ($this->downloadFiles($modules) as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }

        foreach ($this->themeFiles($modules) as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }

        $zip->close();

        return [
            'path' => $path,
            'filename' => $filename,
            'key' => $key,
        ];
    }

    /**
     * @param  array<int, string>  $modules
     * @return array<int, string>
     */
    private function modules(array $modules): array
    {
        $allowed = (array) config('cms_site_packages.implemented_modules', []);
        $modules = collect($modules)
            ->map(fn (mixed $module): string => (string) $module)
            ->filter(fn (string $module): bool => in_array($module, $allowed, true))
            ->unique()
            ->values()
            ->all();

        if (array_intersect($modules, ['media', 'menus']) !== [] && ! in_array('pages', $modules, true)) {
            $modules[] = 'pages';
        }

        if (in_array('blogs', $modules, true)) {
            foreach (['templates', 'media', 'taxonomies'] as $dependency) {
                if (! in_array($dependency, $modules, true)) {
                    $modules[] = $dependency;
                }
            }
        }

        if (in_array('forms', $modules, true) && ! in_array('media', $modules, true)) {
            $modules[] = 'media';
        }

        if (in_array('languages', $modules, true) && ! in_array('media', $modules, true)) {
            $modules[] = 'media';
        }

        if (in_array('pages', $modules, true) && ! in_array('templates', $modules, true)) {
            $modules[] = 'templates';
        }

        if (in_array('docs', $modules, true)) {
            foreach (['templates', 'media'] as $dependency) {
                if (! in_array($dependency, $modules, true)) {
                    $modules[] = $dependency;
                }
            }
        }

        if (array_intersect($modules, ['templates', 'pages', 'blogs', 'docs']) !== [] && ! in_array('layouts', $modules, true)) {
            array_unshift($modules, 'layouts');
        }

        return $modules !== [] ? $modules : $allowed;
    }

    /**
     * @param  array<int, string>  $modules
     * @return array<string, string>
     */
    private function entries(string $key, string $name, array $modules): array
    {
        $layouts = in_array('layouts', $modules, true)
            ? CmsLayout::query()->with('sections.placements.block', 'sections.placements.childPlacements.block')->orderBy('locale')->orderBy('name')->get()
            : collect();
        $templates = in_array('templates', $modules, true)
            ? CmsTemplate::query()->with('sections.placements.block', 'sections.placements.childPlacements.block')->orderBy('locale')->orderBy('name')->get()
            : collect();
        $pages = in_array('pages', $modules, true)
            ? CmsPage::query()->with('sections.placements.block', 'sections.placements.childPlacements.block')->orderBy('locale')->orderBy('sort_order')->orderBy('title')->get()
            : collect();
        $docCollections = in_array('docs', $modules, true)
            ? CmsDocCollection::query()->with('versions.pages.parent', 'versions.pages.translatedFrom')->orderBy('sort_order')->orderBy('name')->get()
            : collect();
        $menus = in_array('menus', $modules, true)
            ? CmsMenu::query()->with('items.page')->orderBy('title')->get()
            : collect();
        $settings = in_array('site', $modules, true)
            ? $this->exportableSettings()
            : collect();
        $languages = in_array('languages', $modules, true)
            ? CmsLanguage::query()->orderBy('sort_order')->orderBy('locale')->get()
            : collect();
        $publicTexts = in_array('public_texts', $modules, true)
            ? CmsPublicText::query()->with('translations')->orderBy('group')->orderBy('key')->get()
            : collect();
        $forms = in_array('forms', $modules, true)
            ? CmsForm::query()->with('fields')->orderBy('locale')->orderBy('title')->get()
            : collect();
        $redirects = in_array('redirects', $modules, true)
            ? CmsRedirect::query()->orderBy('locale')->orderBy('source_path')->get()
            : collect();
        $categories = in_array('taxonomies', $modules, true)
            ? CmsCategory::query()->orderBy('type')->orderBy('locale')->orderBy('sort_order')->orderBy('title')->get()
            : collect();
        $tags = in_array('taxonomies', $modules, true)
            ? CmsTag::query()->orderBy('locale')->orderBy('title')->get()
            : collect();
        $posts = in_array('blogs', $modules, true)
            ? CmsPost::query()->with(['categories', 'tags'])->orderBy('locale')->orderBy('title')->get()
            : collect();
        $mediaAssets = in_array('media', $modules, true)
            ? $this->exportableMediaAssets()
            : collect();
        $downloadFolders = in_array('downloads', $modules, true)
            ? CmsDownloadFolder::query()->with('accessRules')->orderBy('parent_id')->orderBy('sort_order')->orderBy('name')->get()
            : collect();
        $downloadGroups = in_array('downloads', $modules, true)
            ? CmsDownloadGroup::query()->orderBy('sort_order')->orderBy('name')->get()
            : collect();
        $downloadAssets = in_array('downloads', $modules, true)
            ? $this->exportableDownloadAssets()
            : collect();
        $themes = in_array('themes', $modules, true)
            ? CmsTheme::query()->with('versions')->orderByDesc('is_active')->orderBy('name')->get()
            : collect();
        $mediaKeys = $mediaAssets->mapWithKeys(fn (CmsMediaAsset $asset): array => [
            $asset->id => $this->importKey($asset, 'media', $asset->filename ?: 'media-'.$asset->id),
        ])->all();
        $formKeys = $forms->mapWithKeys(fn (CmsForm $form): array => [
            $form->id => $this->importKey($form, 'form', $form->locale.'-'.$form->title.'-'.$form->id),
        ])->all();
        $formKeysByTranslationKey = $forms
            ->filter(fn (CmsForm $form): bool => filled($form->translation_key))
            ->groupBy('translation_key')
            ->map(fn ($group): ?string => $formKeys[$group->first()->id] ?? null)
            ->filter()
            ->all();
        $downloadFolderKeys = $downloadFolders->mapWithKeys(fn (CmsDownloadFolder $folder): array => [
            $folder->id => $this->importKey($folder, 'download-folder', $folder->slug ?: $folder->name.'-'.$folder->id),
        ])->all();
        $downloadGroupKeys = $downloadGroups->mapWithKeys(fn (CmsDownloadGroup $group): array => [
            $group->id => $this->importKey($group, 'download-group', $group->slug ?: $group->name.'-'.$group->id),
        ])->all();
        $downloadKeys = $downloadAssets->mapWithKeys(fn (CmsDownloadAsset $asset): array => [
            $asset->id => $this->importKey($asset, 'download', $asset->filename ?: 'download-'.$asset->id),
        ])->all();

        $layoutKeys = $layouts->mapWithKeys(fn (CmsLayout $layout): array => [
            $layout->id => $this->importKey($layout, 'layout', $layout->name ?: 'layout-'.$layout->id),
        ])->all();
        $templates = $templates
            ->filter(fn (CmsTemplate $template): bool => $template->layout_id !== null && isset($layoutKeys[$template->layout_id]))
            ->values();
        $templateKeys = $templates->mapWithKeys(fn (CmsTemplate $template): array => [
            $template->id => $this->importKey($template, 'template', $template->template_key.'-'.$template->id),
        ])->all();
        $templatesById = $templates->keyBy('id');
        $pages = $pages
            ->filter(fn (CmsPage $page): bool => $page->detail_template_id !== null && isset($templateKeys[$page->detail_template_id]))
            ->values();
        $usedPageKeys = [];
        $pageKeys = $pages->mapWithKeys(function (CmsPage $page) use (&$usedPageKeys): array {
            $baseKey = $this->importKey($page, 'page', $page->locale.'-'.($page->slug ?: 'page').'-'.$page->id);
            $importKey = $baseKey;
            $counter = 2;

            while (in_array($importKey, $usedPageKeys, true)) {
                $importKey = $baseKey.'-'.$page->id.($counter > 2 ? '-'.$counter : '');
                $counter++;
            }

            $usedPageKeys[] = $importKey;

            return [$page->id => $importKey];
        })->all();
        $docCollectionKeys = $docCollections->mapWithKeys(fn (CmsDocCollection $collection): array => [
            $collection->id => $this->importKey($collection, 'doc-collection', $collection->slug ?: 'collection-'.$collection->id),
        ])->all();
        $docVersionKeys = $docCollections
            ->flatMap(fn (CmsDocCollection $collection): Collection => $collection->versions)
            ->mapWithKeys(fn (CmsDocVersion $version): array => [
                $version->id => $this->importKey($version, 'doc-version', $version->slug ?: 'version-'.$version->id),
            ])
            ->all();
        $docPageKeys = $docCollections
            ->flatMap(fn (CmsDocCollection $collection): Collection => $collection->versions)
            ->flatMap(fn (CmsDocVersion $version): Collection => $version->pages)
            ->mapWithKeys(fn (CmsDocPage $page): array => [
                $page->id => $this->importKey($page, 'doc-page', $page->locale.'-'.($page->path ?: $page->slug ?: 'page').'-'.$page->id),
            ])
            ->all();
        $categoryKeys = $categories->mapWithKeys(fn (CmsCategory $category): array => [
            $category->id => $this->importKey($category, 'category', $category->type.'-'.$category->locale.'-'.$category->slug),
        ])->all();
        $tagKeys = $tags->mapWithKeys(fn (CmsTag $tag): array => [
            $tag->id => $this->importKey($tag, 'tag', $tag->locale.'-'.$tag->slug),
        ])->all();
        $postKeys = $posts->mapWithKeys(fn (CmsPost $post): array => [
            $post->id => $this->importKey($post, 'post', $post->locale.'-'.$post->slug),
        ])->all();
        $usedMenuKeys = [];
        $menuKeys = $menus->mapWithKeys(function (CmsMenu $menu) use (&$usedMenuKeys): array {
            $baseKey = $this->importKey($menu, 'menu', $menu->title ?: 'menu-'.$menu->id);
            $importKey = $baseKey;
            $counter = 2;

            while (in_array($importKey, $usedMenuKeys, true)) {
                $importKey = $baseKey.'-'.$menu->id.($counter > 2 ? '-'.$counter : '');
                $counter++;
            }

            $usedMenuKeys[] = $importKey;

            return [$menu->id => $importKey];
        })->all();

        $entries = [
            'manifest.json' => $this->json([
                'type' => (string) config('cms_site_packages.manifest_type'),
                'key' => $key,
                'name' => $name,
                'version' => (int) config('cms_site_packages.version', 1),
                'modules' => $modules,
                'planned_modules' => (array) config('cms_site_packages.planned_modules', []),
            ]),
        ];

        if (in_array('site', $modules, true)) {
            $entries['site.json'] = $this->json($settings->map(fn (CmsSetting $setting): array => [
                'group' => $setting->group,
                'key' => $setting->key,
                'label' => $setting->label,
                'type' => $setting->type,
                'value' => $this->portableSettingValue($setting, $mediaKeys),
                'is_public' => (bool) $setting->is_public,
                'sort_order' => (int) $setting->sort_order,
                'translations' => $setting->translations
                    ->mapWithKeys(fn ($translation): array => [
                        $translation->locale => [
                            'value' => $translation->value ?? [],
                        ],
                    ])
                    ->all(),
            ])->values()->all());
        }

        if (in_array('public_texts', $modules, true)) {
            $entries['public_texts.json'] = $this->json($publicTexts->map(fn (CmsPublicText $text): array => [
                'group' => $text->group,
                'key' => $text->key,
                'label' => $text->label,
                'description' => $text->description,
                'default_value' => $text->default_value,
                'type' => $text->type,
                'is_system' => (bool) $text->is_system,
                'sort_order' => (int) $text->sort_order,
                'translations' => $text->translations
                    ->mapWithKeys(fn ($translation): array => [
                        $translation->locale => [
                            'value' => $translation->value,
                        ],
                    ])
                    ->all(),
            ])->values()->all());
        }

        if (in_array('languages', $modules, true)) {
            $entries['languages.json'] = $this->json($languages->map(fn (CmsLanguage $language): array => [
                'locale' => $language->locale,
                'name' => $language->name,
                'native_name' => $language->native_name,
                'direction' => $language->direction ?: 'ltr',
                'flag_media_import_key' => $language->flag_media_asset_id ? ($mediaKeys[$language->flag_media_asset_id] ?? null) : null,
                'is_active' => (bool) $language->is_active,
                'sort_order' => (int) $language->sort_order,
            ])->values()->all());
        }

        if (in_array('layouts', $modules, true)) {
            $entries['layouts.json'] = $this->json($layouts->map(fn (CmsLayout $layout): array => [
                'import_key' => $layoutKeys[$layout->id],
                'name' => $layout->name,
                'locale' => $layout->locale,
                'is_active' => (bool) $layout->is_active,
                'is_default' => (bool) $layout->is_default,
                'cache_strategy' => $layout->cache_strategy,
                'settings' => $layout->settings ?? [],
                'sections' => $this->sections($layout, $mediaKeys, $formKeysByTranslationKey, $menuKeys, $downloadKeys, $downloadFolderKeys),
            ])->values()->all());
        }

        if (in_array('templates', $modules, true)) {
            $entries['templates.json'] = $this->json($templates->map(fn (CmsTemplate $template): array => [
                'import_key' => $templateKeys[$template->id],
                'layout_import_key' => $layoutKeys[$template->layout_id] ?? null,
                'name' => $template->name,
                'locale' => $template->locale,
                'template_class' => $template->template_class,
                'template_key' => $template->template_key,
                'module_key' => $template->module_key,
                'is_active' => (bool) $template->is_active,
                'is_default' => (bool) $template->is_default,
                'cache_strategy' => $template->cache_strategy,
                'settings' => $template->settings ?? [],
                'data_contract' => $template->data_contract ?? [],
                'sections' => $this->sections($template, $mediaKeys, $formKeysByTranslationKey, $menuKeys, $downloadKeys, $downloadFolderKeys),
            ])->values()->all());
        }

        if (in_array('pages', $modules, true)) {
            $entries['pages.json'] = $this->json($pages->map(fn (CmsPage $page): array => array_filter([
                'import_key' => $pageKeys[$page->id],
                'parent_import_key' => $page->parent_id ? ($pageKeys[$page->parent_id] ?? null) : null,
                'detail_template_import_key' => $page->detail_template_id ? ($templateKeys[$page->detail_template_id] ?? null) : null,
                'title' => $page->title,
                'slug' => $page->slug,
                'locale' => $page->locale,
                'translation_key' => $page->translation_key,
                'translated_from_import_key' => $page->translated_from_page_id ? ($pageKeys[$page->translated_from_page_id] ?? null) : null,
                'short_description' => $page->short_description,
                'status' => $page->status,
                'is_home' => (bool) $page->is_home,
                'published_at' => $page->published_at?->format('Y-m-d H:i:s'),
                'is_searchable' => (bool) $page->is_searchable,
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'canonical_url' => $page->canonical_url,
                'og_image_path' => $page->og_image_path,
                'noindex' => (bool) $page->noindex,
                'sort_order' => (int) $page->sort_order,
                'template_data' => $this->templateData($page->template_data ?? [], $templatesById->get($page->detail_template_id), $mediaKeys, $downloadKeys, $downloadFolderKeys),
                'settings' => $this->withoutImportKeys($page->settings ?? []),
                'sections' => $this->sections($page, $mediaKeys, $formKeysByTranslationKey, $menuKeys, $downloadKeys, $downloadFolderKeys),
            ], fn (mixed $value): bool => $value !== null))->values()->all());
        }

        if (in_array('docs', $modules, true)) {
            $entries['docs.json'] = $this->json($docCollections->map(fn (CmsDocCollection $collection): array => [
                'import_key' => $docCollectionKeys[$collection->id],
                'name' => $collection->name,
                'slug' => $collection->slug,
                'description' => $collection->description,
                'is_active' => (bool) $collection->is_active,
                'sort_order' => (int) $collection->sort_order,
                'settings' => $this->withoutImportKeys($collection->settings ?? []),
                'versions' => $collection->versions->map(fn (CmsDocVersion $version): array => [
                    'import_key' => $docVersionKeys[$version->id],
                    'label' => $version->label,
                    'slug' => $version->slug,
                    'is_default' => (bool) $version->is_default,
                    'is_active' => (bool) $version->is_active,
                    'sort_order' => (int) $version->sort_order,
                    'settings' => $this->withoutImportKeys($version->settings ?? []),
                    'pages' => $version->pages->map(fn (CmsDocPage $page): array => array_filter([
                        'import_key' => $docPageKeys[$page->id],
                        'parent_import_key' => $page->parent_id ? ($docPageKeys[$page->parent_id] ?? null) : null,
                        'title' => $page->title,
                        'slug' => $page->slug,
                        'path' => $page->path,
                        'locale' => $page->locale,
                        'translation_key' => $page->translation_key,
                        'translated_from_import_key' => $page->translated_from_doc_page_id ? ($docPageKeys[$page->translated_from_doc_page_id] ?? null) : null,
                        'status' => $page->status,
                        'body_format' => $page->body_format,
                        'body' => $this->replaceMediaIdsWithImportKeys($page->body, $mediaKeys),
                        'seo_title' => $page->seo_title,
                        'seo_description' => $page->seo_description,
                        'noindex' => (bool) $page->noindex,
                        'sort_order' => (int) $page->sort_order,
                        'settings' => $this->withoutImportKeys($page->settings ?? []),
                    ], fn (mixed $value): bool => $value !== null && $value !== '')),
                ])->values()->all(),
            ])->values()->all());
        }

        if (in_array('forms', $modules, true)) {
            $entries['forms.json'] = $this->json($forms->map(fn (CmsForm $form): array => array_filter([
                'import_key' => $formKeys[$form->id],
                'title' => $form->title,
                'locale' => $form->locale,
                'translation_key' => $form->translation_key,
                'translated_from_import_key' => $form->translated_from_form_id ? ($formKeys[$form->translated_from_form_id] ?? null) : null,
                'description' => $form->description,
                'notification_email' => $form->notification_email,
                'submit_button_label' => $form->submit_button_label,
                'success_message' => $form->success_message,
                'is_active' => (bool) $form->is_active,
                'settings' => $this->withoutImportKeys($form->settings ?? []),
                'fields' => $form->fields
                    ->map(fn (CmsFormField $field): array => array_filter([
                        'import_key' => $this->formFieldImportKey($field),
                        'type' => $field->type,
                        'translation_key' => $field->translation_key,
                        'translated_from_import_key' => $field->translated_from_form_field_id ? $this->fieldImportKey($forms, (int) $field->translated_from_form_field_id) : null,
                        'label' => $field->label,
                        'placeholder' => $field->placeholder,
                        'help_text' => $field->help_text,
                        'options' => $field->options ?? [],
                        'validation_rules' => $field->validation_rules ?? [],
                        'sort_order' => (int) $field->sort_order,
                        'is_required' => (bool) $field->is_required,
                        'is_active' => (bool) $field->is_active,
                        'width' => $field->width ?: 'full',
                        'settings' => $this->withoutImportKeys($field->settings ?? []),
                    ], fn (mixed $value): bool => $value !== null && $value !== ''))
                    ->values()
                    ->all(),
            ], fn (mixed $value): bool => $value !== null && $value !== ''))->values()->all());
        }

        if (in_array('menus', $modules, true)) {
            $entries['menus.json'] = $this->json($menus->sortByDesc(fn (CmsMenu $menu): int => (int) $menu->id)->map(fn (CmsMenu $menu): array => [
                'import_key' => $menuKeys[$menu->id],
                'title' => $menu->title,
                'location' => (string) Arr::first((array) ($menu->placements ?? [])),
                'placements' => array_values((array) ($menu->placements ?? [])),
                'is_active' => (bool) $menu->is_active,
                'settings' => $this->withoutImportKeys($menu->settings ?? []),
                'items' => $this->menuItems($menu, $pageKeys),
            ])->values()->all());
        }

        if (in_array('redirects', $modules, true)) {
            $entries['redirects.json'] = $this->json($redirects->map(fn (CmsRedirect $redirect): array => array_filter([
                'import_key' => $this->importKey($redirect, 'redirect', ltrim($redirect->source_path, '/') ?: 'redirect-'.$redirect->id),
                'source_path' => $redirect->source_path,
                'target_url' => $redirect->target_url,
                'status_code' => (int) $redirect->status_code,
                'locale' => $redirect->locale,
                'is_active' => (bool) $redirect->is_active,
                'starts_at' => $redirect->starts_at?->format('Y-m-d H:i:s'),
                'ends_at' => $redirect->ends_at?->format('Y-m-d H:i:s'),
            ], fn (mixed $value): bool => $value !== null && $value !== ''))->values()->all());
        }

        if (in_array('taxonomies', $modules, true)) {
            $entries['taxonomies.json'] = $this->json([
                'categories' => $categories->map(fn (CmsCategory $category): array => array_filter([
                    'import_key' => $categoryKeys[$category->id],
                    'parent_import_key' => $category->parent_id ? ($categoryKeys[$category->parent_id] ?? null) : null,
                    'landing_page_import_key' => $category->landing_page_id ? ($pageKeys[$category->landing_page_id] ?? null) : null,
                    'archive_template_import_key' => $category->archive_template_id ? ($templateKeys[$category->archive_template_id] ?? null) : null,
                    'detail_template_import_key' => $category->detail_template_id ? ($templateKeys[$category->detail_template_id] ?? null) : null,
                    'type' => $category->type,
                    'title' => $category->title,
                    'slug' => $category->slug,
                    'locale' => $category->locale,
                    'translation_key' => $category->translation_key,
                    'translated_from_import_key' => $category->translated_from_category_id ? ($categoryKeys[$category->translated_from_category_id] ?? null) : null,
                    'description' => $category->description,
                    'sort_order' => (int) $category->sort_order,
                    'is_active' => (bool) $category->is_active,
                    'settings' => $this->withoutImportKeys($category->settings ?? []),
                ], fn (mixed $value): bool => $value !== null && $value !== ''))->values()->all(),
                'tags' => $tags->map(fn (CmsTag $tag): array => array_filter([
                    'import_key' => $tagKeys[$tag->id],
                    'landing_page_import_key' => $tag->landing_page_id ? ($pageKeys[$tag->landing_page_id] ?? null) : null,
                    'archive_template_import_key' => $tag->archive_template_id ? ($templateKeys[$tag->archive_template_id] ?? null) : null,
                    'detail_template_import_key' => $tag->detail_template_id ? ($templateKeys[$tag->detail_template_id] ?? null) : null,
                    'title' => $tag->title,
                    'slug' => $tag->slug,
                    'locale' => $tag->locale,
                    'translation_key' => $tag->translation_key,
                    'translated_from_import_key' => $tag->translated_from_tag_id ? ($tagKeys[$tag->translated_from_tag_id] ?? null) : null,
                    'description' => $tag->description,
                    'is_active' => (bool) $tag->is_active,
                    'settings' => $this->withoutImportKeys($tag->settings ?? []),
                ], fn (mixed $value): bool => $value !== null && $value !== ''))->values()->all(),
            ]);
        }

        if (in_array('blogs', $modules, true)) {
            $entries['posts.json'] = $this->json($posts->map(fn (CmsPost $post): array => array_filter([
                'import_key' => $postKeys[$post->id],
                'featured_media_import_key' => $post->featured_media_asset_id ? ($mediaKeys[$post->featured_media_asset_id] ?? null) : null,
                'detail_template_import_key' => $post->detail_template_id ? ($templateKeys[$post->detail_template_id] ?? null) : null,
                'title' => $post->title,
                'slug' => $post->slug,
                'locale' => $post->locale,
                'translation_key' => $post->translation_key,
                'translated_from_import_key' => $post->translated_from_post_id ? ($postKeys[$post->translated_from_post_id] ?? null) : null,
                'status' => $post->status,
                'excerpt' => $post->excerpt,
                'content_blocks' => $this->contentBlocks($post->content_blocks ?? [], $mediaKeys, $categoryKeys, $tagKeys, $formKeysByTranslationKey, $downloadKeys, $downloadFolderKeys),
                'seo_title' => $post->seo_title,
                'seo_description' => $post->seo_description,
                'canonical_url' => $post->canonical_url,
                'og_image_path' => $post->og_image_path,
                'noindex' => (bool) $post->noindex,
                'is_featured' => (bool) $post->is_featured,
                'is_searchable' => (bool) $post->is_searchable,
                'published_at' => $post->published_at?->format('Y-m-d H:i:s'),
                'settings' => $this->withoutImportKeys($post->settings ?? []),
                'category_import_keys' => $post->categories
                    ->map(fn (CmsCategory $category): ?string => $categoryKeys[$category->id] ?? null)
                    ->filter()
                    ->values()
                    ->all(),
                'tag_import_keys' => $post->tags
                    ->map(fn (CmsTag $tag): ?string => $tagKeys[$tag->id] ?? null)
                    ->filter()
                    ->values()
                    ->all(),
            ], fn (mixed $value): bool => $value !== null && $value !== ''))->values()->all());
        }

        if (in_array('media', $modules, true)) {
            $entries['media/manifest.json'] = $this->json($mediaAssets->map(fn (CmsMediaAsset $asset): array => [
                'import_key' => $mediaKeys[$asset->id],
                'file' => $this->mediaFileEntry($mediaKeys[$asset->id], (string) $asset->extension),
                'filename' => $asset->filename,
                'original_filename' => $asset->original_filename,
                'mime_type' => $asset->mime_type,
                'extension' => $asset->extension,
                'size_bytes' => (int) $asset->size_bytes,
                'width' => $asset->width,
                'height' => $asset->height,
                'hash' => $asset->hash,
                'alt_text' => $asset->alt_text,
                'caption' => $asset->caption,
                'focal_point' => $asset->focal_point ?? [],
                'metadata' => $this->withoutImportKeys($asset->metadata ?? []),
                'translations' => $asset->translations
                    ->mapWithKeys(fn ($translation): array => [
                        $translation->locale => [
                            'alt_text' => $translation->alt_text,
                            'caption' => $translation->caption,
                        ],
                    ])
                    ->all(),
            ])->values()->all());
        }

        if (in_array('downloads', $modules, true)) {
            $entries['downloads/manifest.json'] = $this->json([
                'groups' => $downloadGroups->map(fn (CmsDownloadGroup $group): array => [
                    'import_key' => $downloadGroupKeys[$group->id],
                    'name' => $group->name,
                    'slug' => $group->slug,
                    'description' => $group->description,
                    'is_active' => (bool) $group->is_active,
                    'sort_order' => (int) $group->sort_order,
                ])->values()->all(),
                'folders' => $downloadFolders->map(fn (CmsDownloadFolder $folder): array => array_filter([
                    'import_key' => $downloadFolderKeys[$folder->id],
                    'parent_import_key' => $folder->parent_id ? ($downloadFolderKeys[$folder->parent_id] ?? null) : null,
                    'name' => $folder->name,
                    'slug' => $folder->slug,
                    'access_mode' => $folder->access_mode,
                    'password_expires_minutes' => $folder->password_expires_minutes,
                    'settings' => $this->withoutImportKeys($folder->settings ?? []),
                    'sort_order' => (int) $folder->sort_order,
                    'access_rules' => $this->downloadAccessRules($folder->accessRules, $downloadGroupKeys),
                ], fn (mixed $value): bool => $value !== null))->values()->all(),
                'assets' => $downloadAssets->map(fn (CmsDownloadAsset $asset): array => array_filter([
                    'import_key' => $downloadKeys[$asset->id],
                    'folder_import_key' => $asset->folder_id ? ($downloadFolderKeys[$asset->folder_id] ?? null) : null,
                    'file' => $this->downloadFileEntry($downloadKeys[$asset->id], (string) $asset->extension),
                    'filename' => $asset->filename,
                    'original_filename' => $asset->original_filename,
                    'title' => $asset->title,
                    'description' => $asset->description,
                    'mime_type' => $asset->mime_type,
                    'extension' => $asset->extension,
                    'size_bytes' => (int) $asset->size_bytes,
                    'hash' => $asset->hash,
                    'access_mode' => $asset->access_mode,
                    'published_at' => $asset->published_at?->format('Y-m-d H:i:s'),
                    'expires_at' => $asset->expires_at?->format('Y-m-d H:i:s'),
                    'metadata' => $this->withoutImportKeys($asset->metadata ?? []),
                    'sort_order' => (int) $asset->sort_order,
                    'translations' => $asset->translations
                        ->mapWithKeys(fn ($translation): array => [
                            $translation->locale => [
                                'title' => $translation->title,
                                'description' => $translation->description,
                            ],
                        ])
                        ->all(),
                    'access_rules' => $this->downloadAccessRules($asset->accessRules, $downloadGroupKeys),
                ], fn (mixed $value): bool => $value !== null))->values()->all(),
            ]);
        }

        if (in_array('themes', $modules, true)) {
            $entries['themes/manifest.json'] = $this->json($themes->map(fn (CmsTheme $theme): array => [
                'import_key' => $this->importKey($theme, 'theme', $theme->key ?: 'theme-'.$theme->id),
                'key' => $theme->key,
                'name' => $theme->name,
                'description' => $theme->description,
                'author' => $theme->author,
                'version' => $theme->version,
                'status' => $theme->status,
                'is_active' => (bool) $theme->is_active,
                'versions' => $theme->versions
                    ->values()
                    ->map(fn (CmsThemeVersion $version): array => [
                        'version_hash' => $version->version_hash,
                        'settings' => $version->settings ?? [],
                        'source_manifest' => $version->source_manifest ?? [],
                        'external_assets' => $version->external_assets ?? [],
                        'file_size_kb' => (int) $version->file_size_kb,
                        'developer_css_file' => $this->themeFileEntry($theme, $version, 'developer.css'),
                        'generated_css_file' => $this->themeFileEntry($theme, $version, 'generated.css'),
                        'theme_css_file' => $this->themeFileEntry($theme, $version, 'theme.min.css'),
                    ])
                    ->all(),
            ])->values()->all());
        }

        return $entries;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    /**
     * @param  array<int|string, string>  $mediaKeys
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function sections(Model $owner, array $mediaKeys, array $formKeysByTranslationKey = [], array $menuKeys = [], array $downloadKeys = [], array $downloadFolderKeys = []): array
    {
        return $owner->sections
            ->groupBy('zone')
            ->map(fn ($sections) => $sections->values()->map(fn (CmsSection $section): array => [
                'name' => $section->name,
                'is_active' => (bool) $section->is_active,
                'visible_mobile' => (bool) $section->visible_mobile,
                'visible_tablet' => (bool) $section->visible_tablet,
                'visible_desktop' => (bool) $section->visible_desktop,
                'settings' => $section->settings ?? [],
                'placements' => $section->placements
                    ->reject(fn (CmsBlockPlacement $placement): bool => $this->isForbiddenCodePlacement($placement))
                    ->values()
                    ->map(fn (CmsBlockPlacement $placement): array => $this->placement($placement, $mediaKeys, $formKeysByTranslationKey, $menuKeys, $downloadKeys, $downloadFolderKeys))
                    ->all(),
            ])->all())
            ->all();
    }

    /**
     * @param  array<int|string, string>  $mediaKeys
     * @param  array<string, string>  $formKeysByTranslationKey
     * @param  array<int, string>  $menuKeys
     * @return array<string, mixed>
     */
    private function placement(CmsBlockPlacement $placement, array $mediaKeys, array $formKeysByTranslationKey = [], array $menuKeys = [], array $downloadKeys = [], array $downloadFolderKeys = []): array
    {
        $slots = $placement->childPlacements
            ->where('is_active', true)
            ->reject(fn (CmsBlockPlacement $placement): bool => $this->isForbiddenCodePlacement($placement))
            ->groupBy('slot_key')
            ->map(fn ($placements): array => [
                'placements' => $placements
                    ->sortBy('sort_order')
                    ->values()
                    ->map(fn (CmsBlockPlacement $childPlacement): array => $this->placement($childPlacement, $mediaKeys, $formKeysByTranslationKey, $menuKeys, $downloadKeys, $downloadFolderKeys))
                    ->all(),
            ])
            ->all();

        return array_filter([
            'is_active' => (bool) $placement->is_active,
            'visible_mobile' => (bool) $placement->visible_mobile,
            'visible_tablet' => (bool) $placement->visible_tablet,
            'visible_desktop' => (bool) $placement->visible_desktop,
            'mobile_span' => (int) $placement->mobile_span,
            'tablet_span' => (int) $placement->tablet_span,
            'desktop_span' => (int) $placement->desktop_span,
            'height_mode' => $placement->height_mode,
            'height_value' => $placement->height_value,
            'cache_strategy' => $placement->cache_strategy,
            'settings' => $placement->settings ?? [],
            'block' => $this->block($placement->block, $mediaKeys, $formKeysByTranslationKey, $menuKeys, $downloadKeys, $downloadFolderKeys),
            'slots' => $slots !== [] ? $slots : null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<int|string, string>  $mediaKeys
     * @return array<string, mixed>
     */
    private function block(?CmsBlock $block, array $mediaKeys, array $formKeysByTranslationKey = [], array $menuKeys = [], array $downloadKeys = [], array $downloadFolderKeys = []): array
    {
        if (! $block instanceof CmsBlock) {
            throw ValidationException::withMessages([
                'site_package_export' => __('cms_admin_ui.validation.starter_export_missing_block'),
            ]);
        }

        if (in_array($block->type, ['custom_head_code', 'custom_body_end_code'], true)) {
            throw ValidationException::withMessages([
                'site_package_export' => __('cms_admin_ui.validation.starter_export_code_block_forbidden'),
            ]);
        }

        $content = $this->blockPackageMapper->exportBlockContent(
            $block->type,
            $block->content ?? [],
            $mediaKeys,
            formKeysByTranslationKey: $formKeysByTranslationKey,
            menuKeys: $menuKeys,
            downloadKeys: $downloadKeys,
            downloadFolderKeys: $downloadFolderKeys,
        );

        return array_filter([
            'type' => $block->type,
            'cache_strategy' => $block->cache_strategy,
            ...$content,
        ], fn (mixed $value): bool => $value !== null);
    }

    private function isForbiddenCodePlacement(CmsBlockPlacement $placement): bool
    {
        return in_array($placement->block?->type, ['custom_head_code', 'custom_body_end_code'], true);
    }

    /**
     * @return Collection<int, CmsSetting>
     */
    private function exportableSettings()
    {
        $allowed = collect((array) config('cms_site_packages.policies.site.settings', []))
            ->map(fn (mixed $path): string => (string) $path)
            ->filter()
            ->values()
            ->all();

        if ($allowed === []) {
            return collect();
        }

        return CmsSetting::query()
            ->with('translations')
            ->where(function ($query) use ($allowed): void {
                foreach ($allowed as $path) {
                    [$group, $key] = array_pad(explode('.', $path, 2), 2, '');

                    if ($group !== '' && $key !== '') {
                        $query->orWhere(fn ($settingQuery) => $settingQuery
                            ->where('group', $group)
                            ->where('key', $key));
                    }
                }
            })
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('key')
            ->get();
    }

    /**
     * @param  array<int|string, string>  $mediaKeys
     * @return array<string, mixed>
     */
    private function portableSettingValue(CmsSetting $setting, array $mediaKeys): array
    {
        $value = is_array($setting->value) ? $setting->value : [];

        if (! in_array($setting->group.'.'.$setting->key, ['contact.image_media_asset_id', 'branding.company_logo_media_asset_id'], true)) {
            return $value;
        }

        $mediaId = (int) ($value['value'] ?? 0);

        return [
            'value' => null,
            'media_import_key' => $mediaId > 0 ? ($mediaKeys[$mediaId] ?? null) : null,
        ];
    }

    /**
     * @return Collection<int, CmsMediaAsset>
     */
    private function exportableMediaAssets()
    {
        return CmsMediaAsset::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(function (CmsMediaAsset $asset): bool {
                $disk = $asset->disk ?: 'public';

                return $disk === 'public'
                    && in_array(Str::lower((string) $asset->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
                    && Storage::disk($disk)->exists((string) $asset->path);
            })
            ->values();
    }

    /**
     * @return Collection<int, CmsDownloadAsset>
     */
    private function exportableDownloadAssets()
    {
        $allowedExtensions = collect((array) config('cms_downloads.allowed_extensions', []))
            ->map(fn (mixed $extension): string => Str::lower((string) $extension))
            ->filter()
            ->values()
            ->all();

        return CmsDownloadAsset::query()
            ->with(['translations', 'accessRules'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(function (CmsDownloadAsset $asset) use ($allowedExtensions): bool {
                $disk = $asset->disk ?: (string) config('cms_downloads.disk', 'private');
                $extension = Str::lower((string) $asset->extension);

                return ($allowedExtensions === [] || in_array($extension, $allowedExtensions, true))
                    && Storage::disk($disk)->exists((string) $asset->path);
            })
            ->values();
    }

    /**
     * @param  array<int, string>  $modules
     * @return array<string, string>
     */
    private function siteFiles(array $modules): array
    {
        if (! in_array('site', $modules, true)) {
            return [];
        }

        $files = [];

        foreach ($this->brandingPathKeys() as $key) {
            $setting = CmsSetting::query()
                ->where('group', 'branding')
                ->where('key', $key)
                ->first();
            $path = (string) data_get($setting?->value, 'value', '');

            if ($path !== '' && Storage::disk('public')->exists($path)) {
                $files[$this->brandingFileEntry($key)] = (string) Storage::disk('public')->get($path);
            }
        }

        return $files;
    }

    /**
     * @param  array<int, string>  $modules
     * @return array<string, string>
     */
    private function mediaFiles(array $modules): array
    {
        if (! in_array('media', $modules, true)) {
            return [];
        }

        $files = [];

        foreach ($this->exportableMediaAssets() as $asset) {
            $importKey = $this->importKey($asset, 'media', $asset->filename ?: 'media-'.$asset->id);
            $files[$this->mediaFileEntry($importKey, (string) $asset->extension)] = (string) Storage::disk('public')->get((string) $asset->path);
        }

        return $files;
    }

    /**
     * @param  array<int, string>  $modules
     * @return array<string, string>
     */
    private function downloadFiles(array $modules): array
    {
        if (! in_array('downloads', $modules, true)) {
            return [];
        }

        $files = [];

        foreach ($this->exportableDownloadAssets() as $asset) {
            $importKey = $this->importKey($asset, 'download', $asset->filename ?: 'download-'.$asset->id);
            $disk = $asset->disk ?: (string) config('cms_downloads.disk', 'private');
            $files[$this->downloadFileEntry($importKey, (string) $asset->extension)] = (string) Storage::disk($disk)->get((string) $asset->path);
        }

        return $files;
    }

    private function mediaFileEntry(string $importKey, string $extension): string
    {
        $extension = Str::lower($extension) ?: 'bin';

        return 'media/files/'.(Str::slug($importKey) ?: Str::ulid()).'.'.$extension;
    }

    private function downloadFileEntry(string $importKey, string $extension): string
    {
        $extension = Str::lower($extension) ?: 'bin';

        return 'downloads/files/'.(Str::slug($importKey) ?: Str::ulid()).'.'.$extension;
    }

    /**
     * @param  array<int, string>  $modules
     * @return array<string, string>
     */
    private function themeFiles(array $modules): array
    {
        if (! in_array('themes', $modules, true)) {
            return [];
        }

        $files = [];
        $disk = Storage::disk((string) config('cms_themes.storage_disk', 'local'));

        foreach (CmsTheme::query()->with('versions')->orderBy('name')->get() as $theme) {
            foreach ($theme->versions as $version) {
                foreach ([
                    'developer.css' => $version->developer_css_path,
                    'generated.css' => $version->generated_css_path,
                    'theme.min.css' => $version->minified_css_path,
                ] as $filename => $path) {
                    $path = (string) $path;

                    if ($path !== '' && $disk->exists($path)) {
                        $files[$this->themeFileEntry($theme, $version, $filename)] = (string) $disk->get($path);
                    }
                }
            }
        }

        return $files;
    }

    private function themeFileEntry(CmsTheme $theme, CmsThemeVersion $version, string $filename): string
    {
        return 'themes/files/'.(Str::slug((string) $theme->key) ?: 'theme-'.$theme->id).'/'.$version->version_hash.'/'.$filename;
    }

    /**
     * @param  Collection<int, CmsDownloadAccessRule>  $rules
     * @param  array<int, string>  $downloadGroupKeys
     * @return array<int, array<string, mixed>>
     */
    private function downloadAccessRules(Collection $rules, array $downloadGroupKeys): array
    {
        return $rules
            ->filter(fn (CmsDownloadAccessRule $rule): bool => (bool) $rule->is_active)
            ->map(function (CmsDownloadAccessRule $rule) use ($downloadGroupKeys): ?array {
                if ($rule->rule_type === 'download_group') {
                    $groupImportKey = $downloadGroupKeys[(int) $rule->cms_download_group_id] ?? null;

                    return $groupImportKey ? [
                        'rule_type' => 'download_group',
                        'download_group_import_key' => $groupImportKey,
                    ] : null;
                }

                if ($rule->rule_type === 'profile_field') {
                    return [
                        'rule_type' => 'profile_field',
                        'profile_field_key' => $rule->profile_field_key,
                        'operator' => $rule->operator,
                        'value' => $rule->value,
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int|string, string>  $pageKeys
     * @return array<int, array<string, mixed>>
     */
    private function menuItems(CmsMenu $menu, array $pageKeys): array
    {
        $itemKeys = [];
        $usedItemKeys = [];

        return $menu->items
            ->values()
            ->map(function (CmsMenuItem $item) use ($pageKeys, &$itemKeys, &$usedItemKeys): array {
                $baseKey = $this->importKey($item, 'menu-item', Str::slug((string) $item->label) ?: 'item-'.$item->id);
                $itemKey = $baseKey;
                $counter = 2;

                while (in_array($itemKey, $usedItemKeys, true)) {
                    $itemKey = $baseKey.'-'.$item->id.($counter > 2 ? '-'.$counter : '');
                    $counter++;
                }

                $itemKeys[$item->id] = $itemKey;
                $usedItemKeys[] = $itemKey;
                $pageKey = $item->cms_page_id ? ($pageKeys[$item->cms_page_id] ?? null) : null;
                $type = $item->type === 'page' && $pageKey ? 'page' : $item->type;

                if ($item->type === 'page' && ! $pageKey) {
                    $type = 'custom';
                }

                return array_filter([
                    'import_key' => $itemKey,
                    'parent_import_key' => $item->parent_id ? ($itemKeys[$item->parent_id] ?? null) : null,
                    'type' => $type,
                    'page_import_key' => $type === 'page' ? $pageKey : null,
                    'label' => $item->label,
                    'translation_key' => $item->translation_key,
                    'url' => $type === 'page' ? null : ($item->url ?: $this->pageUrl($item->page)),
                    'target' => $item->target,
                    'rel' => $item->rel,
                    'locale' => $item->locale,
                    'sort_order' => (int) $item->sort_order,
                    'is_active' => (bool) $item->is_active,
                    'metadata' => $this->withoutImportKeys($item->metadata ?? []),
                ], fn (mixed $value): bool => $value !== null && $value !== '');
            })
            ->all();
    }

    private function pageUrl(?CmsPage $page): ?string
    {
        if (! $page instanceof CmsPage) {
            return null;
        }

        return '/'.$page->locale.'/'.$page->slug;
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @param  array<int, string>  $mediaKeys
     * @param  array<int, string>  $categoryKeys
     * @param  array<int, string>  $tagKeys
     * @return array<int, array<string, mixed>>
     */
    private function contentBlocks(array $blocks, array $mediaKeys, array $categoryKeys, array $tagKeys, array $formKeysByTranslationKey = [], array $downloadKeys = [], array $downloadFolderKeys = []): array
    {
        return collect($blocks)
            ->filter(fn (mixed $block): bool => is_array($block))
            ->map(function (array $block) use ($mediaKeys, $categoryKeys, $tagKeys, $formKeysByTranslationKey, $downloadKeys, $downloadFolderKeys): array {
                $block = $this->blockPackageMapper->exportBlockContent(
                    (string) ($block['type'] ?? 'text'),
                    $block,
                    $mediaKeys,
                    $categoryKeys,
                    $tagKeys,
                    $formKeysByTranslationKey,
                    downloadKeys: $downloadKeys,
                    downloadFolderKeys: $downloadFolderKeys,
                );

                return $this->withoutImportKeys($block);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $mediaKeys
     * @return array<string, mixed>
     */
    private function templateData(array $data, ?CmsTemplate $template, array $mediaKeys, array $downloadKeys = [], array $downloadFolderKeys = []): array
    {
        if (! $template instanceof CmsTemplate) {
            return [];
        }

        $contract = app(CmsTemplateDataContract::class)->normalize($template->data_contract, (string) $template->template_key);

        foreach ($contract['template_fields'] as $field) {
            $type = (string) ($field['type'] ?? '');

            if (in_array($type, ['media', 'media_select'], true)) {
                $value = Arr::get($data, $field['key']);
                Arr::set($data, $field['key'], $value ? ($mediaKeys[(int) $value] ?? null) : null);

                continue;
            }

            if (in_array($type, ['download_select', 'download'], true)) {
                $value = Arr::get($data, $field['key']);
                Arr::set($data, $field['key'], $value ? ($downloadKeys[(int) $value] ?? null) : null);

                continue;
            }

            if ($type === 'download_folder_select') {
                $value = Arr::get($data, $field['key']);
                Arr::set($data, $field['key'], $value ? ($downloadFolderKeys[(int) $value] ?? null) : null);
            }
        }

        return $this->withoutImportKeys($data);
    }

    /**
     * @param  array<int|string, string>  $mediaKeys
     */
    private function replaceMediaIdsWithImportKeys(?string $markdown, array $mediaKeys): ?string
    {
        if ($markdown === null || $markdown === '') {
            return $markdown;
        }

        return (string) preg_replace_callback(
            '/!\[([^\]]*)\]\(media:(\d+)\)/',
            function (array $matches) use ($mediaKeys): string {
                $mediaId = (int) $matches[2];
                $importKey = $mediaKeys[$mediaId] ?? null;

                if (! is_string($importKey) || $importKey === '') {
                    return (string) $matches[0];
                }

                return '!['.(string) $matches[1].'](media:'.$importKey.')';
            },
            $markdown
        );
    }

    private function importKey(Model $model, string $prefix, string $fallback): string
    {
        $importKey = trim((string) ($model->getAttribute('import_key') ?? ''));
        $settings = $model->getAttribute('settings');

        if ($importKey === '' && is_array($settings)) {
            $importKey = (string) ($settings['starter_import_key'] ?? $settings['site_package_import_key'] ?? '');
        }

        if (preg_match('/^(starter|site-package):[^:]+:(.+)$/', $importKey, $matches) === 1) {
            return $matches[2];
        }

        return $prefix.'.'.(Str::slug($fallback) ?: (string) $model->getKey());
    }

    private function fieldImportKey(Collection $forms, int $fieldId): ?string
    {
        foreach ($forms as $form) {
            if (! $form instanceof CmsForm) {
                continue;
            }

            $field = $form->fields->firstWhere('id', $fieldId);

            if ($field instanceof CmsFormField) {
                return $this->formFieldImportKey($field);
            }
        }

        return null;
    }

    private function formFieldImportKey(CmsFormField $field): string
    {
        $fallback = $field->translation_key
            ? $field->translation_key.'-'.$field->id
            : $field->label.'-'.$field->id;

        return $this->importKey($field, 'field', $fallback);
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

    private function packageKey(string $key): string
    {
        return Str::slug($key) ?: 'site-package-'.now()->format('Ymd-His');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function withoutImportKeys(array $settings): array
    {
        unset($settings['starter_import_key'], $settings['site_package_import_key']);

        return $settings;
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }
}
