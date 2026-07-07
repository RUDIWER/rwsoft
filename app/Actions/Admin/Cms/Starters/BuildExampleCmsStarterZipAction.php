<?php

namespace App\Actions\Admin\Cms\Starters;

use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class BuildExampleCmsStarterZipAction
{
    /**
     * @return array{path: string, filename: string, key: string}
     */
    public function handle(): array
    {
        $key = 'example-starter';
        $filename = 'rwsoft-example-starter.zip';
        $directory = storage_path('app/starter-exports');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/'.Str::uuid().'-'.$filename;
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(__('cms_admin_ui.flash.starter_zip_create_failed'));
        }

        foreach ($this->entries($key) as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return [
            'path' => $path,
            'filename' => $filename,
            'key' => $key,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function entries(string $key): array
    {
        return [
            'manifest.json' => $this->json([
                'type' => (string) config('cms_starters.import.manifest_type', 'rwsoft-cms-starter'),
                'key' => $key,
                'name' => $this->text('starter_name'),
                'version' => 1,
                'modules' => ['layouts', 'templates', 'pages', 'menus'],
            ]),
            'layouts.json' => $this->json([
                [
                    'import_key' => 'layout.main',
                    'name' => $this->text('layout_name'),
                    'locale' => 'nl',
                    'is_active' => true,
                    'is_default' => true,
                    'cache_strategy' => 'inherit',
                    'sections' => $this->layoutSections(),
                ],
            ]),
            'templates.json' => $this->json([
                [
                    'import_key' => 'template.page-detail',
                    'layout_import_key' => 'layout.main',
                    'name' => $this->text('template_name'),
                    'locale' => 'nl',
                    'template_class' => 'page',
                    'template_key' => 'page.detail',
                    'is_active' => true,
                    'is_default' => true,
                    'cache_strategy' => 'inherit',
                    'data_contract' => [
                        'system_fields' => [
                            ['key' => 'page.title', 'enabled' => true],
                        ],
                        'template_fields' => [
                            [
                                'key' => 'intro_text',
                                'type' => 'textarea',
                                'required' => false,
                                'sort_order' => 10,
                                'translations' => [
                                    'en' => ['label' => 'Intro text'],
                                    'nl' => ['label' => 'Introtekst'],
                                ],
                            ],
                        ],
                    ],
                    'sections' => $this->templateSections(),
                ],
            ]),
            'pages.json' => $this->json([
                [
                    'import_key' => 'page.home',
                    'detail_template_import_key' => 'template.page-detail',
                    'title' => $this->text('page_title'),
                    'slug' => 'example-home',
                    'locale' => 'nl',
                    'short_description' => $this->text('page_short_description'),
                    'status' => 'published',
                    'is_home' => true,
                    'published_at' => '2026-01-01 12:00:00',
                    'is_searchable' => true,
                    'seo_title' => $this->text('seo_title'),
                    'seo_description' => $this->text('seo_description'),
                    'template_data' => [
                        'intro_text' => $this->text('hero_text'),
                    ],
                ],
            ]),
            'menus.json' => $this->json([
                [
                    'import_key' => 'menu.header',
                    'title' => $this->text('menu_title'),
                    'placements' => ['header'],
                    'is_active' => true,
                    'items' => [
                        [
                            'import_key' => 'menu.header.home',
                            'type' => 'page',
                            'page_import_key' => 'page.home',
                            'label' => $this->text('menu_home'),
                            'locale' => 'nl',
                            'sort_order' => 0,
                        ],
                        [
                            'import_key' => 'menu.header.contact',
                            'type' => 'custom',
                            'label' => $this->text('menu_contact'),
                            'url' => '/nl/contact',
                            'locale' => 'nl',
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ]),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function layoutSections(): array
    {
        return [
            'head' => [
                $this->section($this->text('section_system_head'), [
                    $this->placement(['type' => 'site_head']),
                ]),
            ],
            'header' => [
                $this->section($this->text('section_site_header'), [
                    $this->placement(['type' => 'site_header']),
                ]),
            ],
            'footer' => [
                $this->section($this->text('section_site_footer'), [
                    $this->placement(['type' => 'site_footer']),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function templateSections(): array
    {
        return [
            'content' => [
                $this->section($this->text('section_page_header'), [
                    $this->placement([
                        'type' => 'dynamic_field',
                        'field_key' => 'page.title',
                        'heading_level' => 'h1',
                    ]),
                    $this->placement([
                        'type' => 'breadcrumb',
                        'show_current' => true,
                        'compact' => true,
                    ]),
                ]),
                $this->section($this->text('section_page_content'), [
                    $this->placement([
                        'type' => 'dynamic_field',
                        'field_key' => 'template.intro_text',
                    ]),
                ]),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $placements
     * @return array<string, mixed>
     */
    private function section(string $name, array $placements): array
    {
        return [
            'name' => $name,
            'is_active' => true,
            'placements' => $placements,
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function placement(array $block, int $desktopSpan = 12): array
    {
        return [
            'desktop_span' => $desktopSpan,
            'tablet_span' => 12,
            'mobile_span' => 12,
            'block' => $block,
        ];
    }

    private function text(string $key): string
    {
        return (string) __('cms_admin_ui.starter_example_content.'.$key);
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }
}
