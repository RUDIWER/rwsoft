<?php

namespace App\Actions\Admin\Cms\SitePackages;

use App\Actions\Admin\Cms\Themes\PublishThemeAction;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsDocCollection;
use App\Models\Cms\CmsDocVersion;
use App\Models\Cms\CmsDownloadAsset;
use App\Models\Cms\CmsDownloadFolder;
use App\Models\Cms\CmsDownloadGroup;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormField;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsRedirect;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivateCmsSitePackageAction
{
    public function __construct(
        private readonly PublishThemeAction $publishTheme,
    ) {}

    /**
     * @param  array{package_key?: string, modules?: array<int, string>, publish_pages?: bool, publish_blogs?: bool, set_homepage?: bool, set_default_layouts?: bool, set_default_templates?: bool, activate_theme_import_key?: string|null}  $input
     * @return array<string, int>
     */
    public function handle(array $input): array
    {
        $packageKey = $this->packageKey($input['package_key'] ?? null);
        $modules = $this->modules((array) ($input['modules'] ?? []));

        if ($modules === []) {
            throw ValidationException::withMessages([
                'modules' => __('cms_admin_ui.validation.site_package_activation_modules_required'),
            ]);
        }

        $prefix = 'site-package:'.$packageKey.':';

        return DB::transaction(function () use ($input, $modules, $prefix): array {
            $activated = [
                'layouts' => 0,
                'templates' => 0,
                'pages' => 0,
                'menus' => 0,
                'redirects' => 0,
                'taxonomies' => 0,
                'blogs' => 0,
                'forms' => 0,
                'downloads' => 0,
                'docs' => 0,
                'themes' => 0,
                'homepage' => 0,
                'default_layouts' => 0,
                'default_templates' => 0,
            ];

            if (in_array('layouts', $modules, true)) {
                $activated['layouts'] = CmsLayout::query()
                    ->where('import_key', 'like', $prefix.'%')
                    ->update(['is_active' => true]);
            }

            if (in_array('templates', $modules, true)) {
                $activated['templates'] = CmsTemplate::query()
                    ->where('import_key', 'like', $prefix.'%')
                    ->update(['is_active' => true]);
            }

            if (in_array('pages', $modules, true)) {
                $pageQuery = CmsPage::query()->where('settings->site_package_import_key', 'like', $prefix.'%');
                $activated['pages'] = (clone $pageQuery)->count();

                if ((bool) ($input['publish_pages'] ?? false)) {
                    (clone $pageQuery)->update([
                        'status' => 'published',
                        'published_at' => now(),
                    ]);
                }
            }

            if (in_array('menus', $modules, true)) {
                $activated['menus'] = CmsMenu::query()
                    ->where('settings->site_package_import_key', 'like', $prefix.'%')
                    ->update(['is_active' => true]);
            }

            if (in_array('redirects', $modules, true)) {
                $activated['redirects'] = CmsRedirect::query()
                    ->where('import_key', 'like', $prefix.'%')
                    ->update(['is_active' => true]);
            }

            if (in_array('taxonomies', $modules, true)) {
                $activated['taxonomies'] = CmsCategory::query()
                    ->where('settings->site_package_import_key', 'like', $prefix.'%')
                    ->update(['is_active' => true]);
                $activated['taxonomies'] += CmsTag::query()
                    ->where('settings->site_package_import_key', 'like', $prefix.'%')
                    ->update(['is_active' => true]);
            }

            if (in_array('blogs', $modules, true)) {
                $postQuery = CmsPost::query()->where('settings->site_package_import_key', 'like', $prefix.'%');
                $activated['blogs'] = (clone $postQuery)->count();

                if ((bool) ($input['publish_blogs'] ?? false)) {
                    (clone $postQuery)->update([
                        'status' => 'published',
                        'published_at' => now(),
                    ]);
                }
            }

            if (in_array('forms', $modules, true)) {
                $formIds = CmsForm::query()
                    ->where('settings->site_package_import_key', 'like', $prefix.'%')
                    ->pluck('id');
                $activated['forms'] = CmsForm::query()
                    ->whereIn('id', $formIds)
                    ->update(['is_active' => true]);

                if ($formIds->isNotEmpty()) {
                    CmsFormField::query()
                        ->whereIn('cms_form_id', $formIds)
                        ->update(['is_active' => true]);
                }
            }

            if (in_array('downloads', $modules, true)) {
                $activated['downloads'] = CmsDownloadGroup::query()
                    ->where('settings->site_package_import_key', 'like', $prefix.'%')
                    ->update(['is_active' => true]);
                $activated['downloads'] += CmsDownloadFolder::query()
                    ->where('settings->site_package_import_key', 'like', $prefix.'%')
                    ->count();
                $activated['downloads'] += CmsDownloadAsset::query()
                    ->where('metadata->site_package_import_key', 'like', $prefix.'%')
                    ->count();
            }

            if (in_array('docs', $modules, true)) {
                $collectionIds = CmsDocCollection::query()
                    ->where('settings->site_package_import_key', 'like', $prefix.'%')
                    ->pluck('id');
                $versionIds = CmsDocVersion::query()
                    ->where('settings->site_package_import_key', 'like', $prefix.'%')
                    ->pluck('id');

                $activated['docs'] = $collectionIds->count();

                if ($collectionIds->isNotEmpty()) {
                    CmsDocCollection::query()
                        ->whereIn('id', $collectionIds)
                        ->update(['is_active' => true]);
                }

                if ($versionIds->isNotEmpty()) {
                    CmsDocVersion::query()
                        ->whereIn('id', $versionIds)
                        ->update(['is_active' => true]);
                }

            }

            if (in_array('themes', $modules, true)) {
                $themeQuery = CmsTheme::query()->where('import_key', 'like', $prefix.'%');
                $activated['themes'] = (clone $themeQuery)->count();
                $activateThemeImportKey = $this->activationImportKey($input['activate_theme_import_key'] ?? null);

                if ($activateThemeImportKey !== '') {
                    $theme = (clone $themeQuery)
                        ->where('import_key', $prefix.$activateThemeImportKey)
                        ->first();

                    if (! $theme instanceof CmsTheme) {
                        throw ValidationException::withMessages([
                            'activate_theme_import_key' => __('cms_admin_ui.validation.site_package_activation_theme_missing'),
                        ]);
                    }

                    $version = $theme->versions()->first();

                    if (! $version instanceof CmsThemeVersion) {
                        throw ValidationException::withMessages([
                            'activate_theme_import_key' => __('cms_admin_ui.validation.site_package_activation_theme_version_missing'),
                        ]);
                    }

                    $this->publishTheme->handle($theme, $version);
                }
            }

            if ((bool) ($input['set_homepage'] ?? false)) {
                $activated['homepage'] = $this->activateHomepage($prefix);
            }

            if ((bool) ($input['set_default_layouts'] ?? false)) {
                $activated['default_layouts'] = $this->activateDefaultLayouts($prefix);
            }

            if ((bool) ($input['set_default_templates'] ?? false)) {
                $activated['default_templates'] = $this->activateDefaultTemplates($prefix);
            }

            return $activated;
        });
    }

    private function packageKey(mixed $value): string
    {
        $packageKey = is_scalar($value) ? trim((string) $value) : '';

        if ($packageKey === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $packageKey) !== 1) {
            throw ValidationException::withMessages([
                'package_key' => __('cms_admin_ui.validation.site_package_activation_key_required'),
            ]);
        }

        return $packageKey;
    }

    /**
     * @param  array<int, mixed>  $modules
     * @return array<int, string>
     */
    private function modules(array $modules): array
    {
        $allowed = ['layouts', 'templates', 'pages', 'menus', 'downloads', 'redirects', 'taxonomies', 'blogs', 'forms', 'docs', 'themes'];

        return collect($modules)
            ->map(fn (mixed $module): string => is_scalar($module) ? (string) $module : '')
            ->filter(fn (string $module): bool => in_array($module, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    private function activationImportKey(mixed $value): string
    {
        $importKey = is_scalar($value) ? trim((string) $value) : '';

        if ($importKey === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/', $importKey) !== 1) {
            throw ValidationException::withMessages([
                'activate_theme_import_key' => __('cms_admin_ui.validation.site_package_activation_theme_key_format'),
            ]);
        }

        return $importKey;
    }

    private function activateHomepage(string $prefix): int
    {
        $defaultLocale = $this->defaultLocale();
        $pages = CmsPage::query()
            ->where('settings->site_package_import_key', 'like', $prefix.'%')
            ->orderBy('id')
            ->get();
        $page = $pages->first(fn (CmsPage $page): bool => (bool) data_get($page->settings, 'site_package_was_home')
            && $page->locale === $defaultLocale);

        if (! $page instanceof CmsPage) {
            $page = $pages->first(fn (CmsPage $page): bool => (bool) data_get($page->settings, 'site_package_was_home'));
        }

        if (! $page instanceof CmsPage) {
            $page = $pages->first(fn (CmsPage $page): bool => $page->locale === $defaultLocale);
        }

        if (! $page instanceof CmsPage) {
            $page = $pages->first();
        }

        if (! $page instanceof CmsPage) {
            return 0;
        }

        CmsPage::query()->update(['is_home' => false]);
        $page->forceFill(['is_home' => true])->save();

        CmsSetting::query()->updateOrCreate(
            ['group' => 'general', 'key' => 'homepage_id'],
            [
                'label' => __('cms_admin_ui.settings.form.homepage'),
                'type' => 'number',
                'value' => ['value' => $page->id],
                'is_public' => true,
                'sort_order' => 40,
            ],
        );

        return 1;
    }

    private function defaultLocale(): string
    {
        $setting = CmsSetting::query()
            ->where('group', 'general')
            ->where('key', 'default_locale')
            ->first();

        $locale = (string) data_get($setting?->value, 'value', config('app.locale', 'nl'));

        return $locale !== '' ? $locale : (string) config('app.locale', 'nl');
    }

    private function activateDefaultLayouts(string $prefix): int
    {
        $layouts = CmsLayout::query()
            ->where('import_key', 'like', $prefix.'%')
            ->get()
            ->filter(fn (CmsLayout $layout): bool => (bool) data_get($layout->settings, 'site_package_was_default'));
        $count = 0;

        foreach ($layouts as $layout) {
            CmsLayout::query()
                ->where('locale', $layout->locale)
                ->where('id', '!=', $layout->id)
                ->update(['is_default' => false]);

            $layout->forceFill(['is_default' => true])->save();
            $count++;
        }

        return $count;
    }

    private function activateDefaultTemplates(string $prefix): int
    {
        $templates = CmsTemplate::query()
            ->where('import_key', 'like', $prefix.'%')
            ->get()
            ->filter(fn (CmsTemplate $template): bool => (bool) data_get($template->settings, 'site_package_was_default'));
        $count = 0;

        foreach ($templates as $template) {
            CmsTemplate::query()
                ->where('locale', $template->locale)
                ->where('template_key', $template->template_key)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);

            $template->forceFill(['is_default' => true])->save();
            $count++;
        }

        return $count;
    }
}
