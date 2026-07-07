<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TranslationAvailabilityTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function locales(): array
    {
        return ['en', 'nl', 'fr', 'de', 'es'];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadTranslation(string $locale, string $file): array
    {
        $path = dirname(__DIR__, 2)."/lang/{$locale}/{$file}.php";

        $translations = require $path;

        $this->assertIsArray($translations, "{$locale}/{$file}.php must return an array.");

        return $translations;
    }

    /**
     * @return array<int, string>
     */
    private function cmsBlockEditorTranslationKeys(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [$root.'/config/cms_blocks.php'];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root.'/resources/js/Pages/Admin/Cms')
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['js', 'vue'], true)) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        $keys = [];

        foreach ($files as $file) {
            preg_match_all(
                '/components\.block_editor\.[A-Za-z0-9_]+/',
                file_get_contents($file) ?: '',
                $matches
            );

            $keys = [...$keys, ...$matches[0]];
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    /**
     * @return array<int, string>
     */
    private function cmsBlockRegistryTranslationKeys(): array
    {
        $config = require dirname(__DIR__, 2).'/config/cms_blocks.php';
        $keys = [];

        $collect = function (mixed $value, string|int|null $key = null) use (&$collect, &$keys): void {
            if (is_array($value)) {
                foreach ($value as $childKey => $childValue) {
                    $collect($childValue, $childKey);
                }

                return;
            }

            if (! in_array($key, ['label_key', 'placeholder_key'], true) || ! is_string($value)) {
                return;
            }

            $keys[] = $value;
        };

        $collect($config);

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    private function assertHasPath(array $translations, string $path, string $context): void
    {
        $current = $translations;

        foreach (explode('.', $path) as $segment) {
            $this->assertIsArray($current, "{$context}: segment [{$segment}] is not traversable for [{$path}].");
            $this->assertArrayHasKey($segment, $current, "{$context}: missing translation key [{$path}].");
            $current = $current[$segment];
        }

        $this->assertIsString($current, "{$context}: translation key [{$path}] must resolve to a string.");
        $this->assertNotSame('', trim($current), "{$context}: translation key [{$path}] may not be empty.");
    }

    public function test_admin_common_ui_contains_shared_table_column_labels_for_all_locales(): void
    {
        $requiredPaths = [
            'actions.back',
            'actions.new',
            'actions.edit',
            'actions.delete',
            'columns.id',
            'columns.name',
            'columns.title',
            'columns.locale',
            'columns.code',
            'columns.status',
            'columns.active',
            'columns.updated_at',
            'columns.created_at',
            'columns.actions',
            'columns.description',
            'columns.type',
        ];

        foreach ($this->locales() as $locale) {
            $translations = $this->loadTranslation($locale, 'admin_common_ui');

            foreach ($requiredPaths as $path) {
                $this->assertHasPath($translations, $path, "admin_common_ui {$locale}");
            }
        }
    }

    public function test_cms_admin_ui_contains_required_rwtable_screen_translations_for_all_locales(): void
    {
        $requiredPaths = [
            'forms.page_title',
            'forms.title',
            'forms.description',
            'forms.columns.fields',
            'forms.columns.submissions',
            'layouts.page_title',
            'layouts.title',
            'layouts.description',
            'layouts.name',
            'layouts.default',
            'layouts.pages',
            'layouts.actions',
            'themes.page_title',
            'themes.title',
            'themes.description',
            'themes.import_title',
            'themes.zip_file',
            'themes.no_file_chosen',
            'themes.version',
            'themes.actions',
            'templates.page_title',
            'templates.title',
            'templates.description',
            'templates.columns.name',
            'templates.columns.template_type',
            'templates.columns.layout',
            'templates.columns.default',
            'templates.columns.usage_count',
            'templates.builder.title',
            'templates.builder.description',
            'templates.classes.page',
            'templates.classes.blog',
            'templates.classes.category',
            'templates.classes.tag',
            'templates.types.page_detail',
            'templates.types.blog_index',
            'templates.types.blog_detail',
            'templates.types.category_index',
            'templates.types.category_archive',
            'templates.types.category_detail',
            'templates.types.tag_index',
            'templates.types.tag_archive',
            'templates.types.tag_detail',
            'languages.page_title',
            'languages.title',
            'languages.description',
            'languages.tabs.languages',
            'languages.tabs.order',
            'languages.order.title',
            'languages.order.subtitle',
            'languages.order.help',
            'languages.order.saving',
            'languages.order.saved',
            'languages.order.autosave',
            'languages.order.active',
            'languages.order.inactive',
            'languages.order.move_up',
            'languages.order.move_down',
            'languages.columns.name',
            'languages.columns.native_name',
            'languages.columns.direction',
            'taxonomy.page_title',
            'taxonomy.title',
            'taxonomy.description',
            'taxonomy.tabs.categories',
            'taxonomy.tabs.tags',
            'pages.page_title',
            'pages.title',
            'pages.description',
            'pages.new',
            'pages.columns.home',
            'posts.page_title',
            'posts.title',
            'posts.description',
            'posts.new',
            'posts.columns.featured',
            'menus.page_title',
            'menus.title',
            'menus.description',
            'menus.new',
            'menus.columns.location',
            'menus.columns.item_groups',
            'redirects.page_title',
            'redirects.title',
            'redirects.description',
            'redirects.columns.source',
            'redirects.columns.target',
            'redirects.columns.hits',
            'redirects.columns.start',
            'redirects.columns.end',
            'components.block_editor.add_item',
            'components.block_editor.drag_item',
            'components.block_editor.up',
            'components.block_editor.down',
            'components.block_editor.expand',
            'components.block_editor.collapse',
            'components.block_editor.delete',
            'components.block_editor.repeater_empty',
            'settings.page_title',
            'settings.description',
            'settings.tabs.admin',
            'settings.tabs.general',
            'settings.tabs.seo',
            'settings.tabs.robots',
            'settings.tabs.branding',
            'settings.tabs.ai',
            'settings.admin.title',
            'settings.admin.subtitle',
            'settings.form.admin_default_locale',
            'settings.form.site_name',
            'settings.form.default_locale',
            'settings.form.tagline',
            'settings.form.homepage',
            'settings.form.public_text_cache_enabled',
            'settings.form.seo_default_title',
            'settings.form.robots_info',
            'settings.form.branding_info',
            'settings.form.provider',
            'settings.form.model',
        ];

        foreach ($this->locales() as $locale) {
            $translations = $this->loadTranslation($locale, 'cms_admin_ui');

            foreach ($requiredPaths as $path) {
                $this->assertHasPath($translations, $path, "cms_admin_ui {$locale}");
            }
        }
    }

    public function test_cms_admin_ui_contains_all_used_block_editor_translations_for_all_locales(): void
    {
        $requiredPaths = $this->cmsBlockEditorTranslationKeys();

        foreach ($this->locales() as $locale) {
            $translations = $this->loadTranslation($locale, 'cms_admin_ui');

            foreach ($requiredPaths as $path) {
                $this->assertHasPath($translations, $path, "cms_admin_ui {$locale}");
            }
        }
    }

    public function test_cms_admin_ui_contains_all_registry_label_and_placeholder_translations_for_all_locales(): void
    {
        $requiredPaths = $this->cmsBlockRegistryTranslationKeys();

        foreach ($this->locales() as $locale) {
            $translations = $this->loadTranslation($locale, 'cms_admin_ui');

            foreach ($requiredPaths as $path) {
                $this->assertHasPath($translations, $path, "cms_admin_ui {$locale}");
            }
        }
    }

    public function test_admin_security_ui_contains_required_screen_translations_for_all_locales(): void
    {
        $requiredPaths = [
            'users.meta_title',
            'users.index_title',
            'users.index_subtitle',
            'users.edit_title',
            'users.create_title',
            'users.form_title_new',
            'users.form_subtitle',
            'users.name',
            'users.email',
            'users.password',
            'users.roles',
            'roles.meta_title',
            'roles.index_title',
            'roles.index_subtitle',
            'roles.edit_title',
            'roles.create_title',
            'roles.form_title_new',
            'roles.form_subtitle',
            'roles.key',
            'roles.name',
            'roles.description',
            'roles.permissions',
            'permissions.meta_title',
            'permissions.index_title',
            'permissions.index_subtitle',
            'permissions.edit_title',
            'permissions.create_title',
            'permissions.form_title_new',
            'permissions.form_subtitle',
            'permissions.route_name',
            'permissions.description',
            'permissions.module',
            'permissions.action',
            'permissions.type',
            'permissions.query_id',
            'permissions.url',
            'permissions.menu',
            'columns.id',
            'columns.name',
            'columns.email',
            'columns.roles',
            'columns.users',
            'columns.permissions',
            'columns.key',
            'columns.route',
            'columns.description',
            'columns.module',
            'columns.type',
            'columns.in_menu',
            'columns.action',
            'validation.summary_title',
            'validation.summary_description',
            'validation.required',
            'validation.max_chars',
            'validation.invalid_choice',
        ];

        foreach ($this->locales() as $locale) {
            $translations = $this->loadTranslation($locale, 'admin_security_ui');

            foreach ($requiredPaths as $path) {
                $this->assertHasPath($translations, $path, "admin_security_ui {$locale}");
            }
        }
    }
}
