<?php

namespace App\Actions\Admin\Cms\Starters;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsTemplate;
use App\Support\Cms\CmsTemplateDataContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class BuildCmsStarterZipFromSelectionAction
{
    /**
     * @param  array{starter_key?: string, starter_name?: string, layout_id: int, template_id: int, page_id: int, menu_id: int}  $selection
     * @return array{path: string, filename: string, key: string}
     */
    public function handle(array $selection): array
    {
        $layout = $this->layout((int) $selection['layout_id']);
        $template = $this->template((int) $selection['template_id'], $layout);
        $page = $this->page((int) $selection['page_id'], $layout, $template);
        $menu = $this->menu((int) $selection['menu_id']);
        $key = $this->starterKey((string) ($selection['starter_key'] ?? ''), $page);
        $name = $this->starterName((string) ($selection['starter_name'] ?? ''), $page);
        $filename = $key.'.zip';
        $directory = storage_path('app/starter-exports');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/'.Str::uuid().'-'.$filename;
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(__('cms_admin_ui.flash.starter_zip_create_failed'));
        }

        foreach ($this->entries($key, $name, $layout, $template, $page, $menu) as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }

        $zip->close();

        return [
            'path' => $path,
            'filename' => $filename,
            'key' => $key,
        ];
    }

    private function layout(int $id): CmsLayout
    {
        return CmsLayout::query()
            ->with('sections.placements.block')
            ->findOrFail($id);
    }

    private function template(int $id, CmsLayout $layout): CmsTemplate
    {
        $template = CmsTemplate::query()
            ->with('sections.placements.block')
            ->findOrFail($id);

        if ((int) $template->layout_id !== (int) $layout->id) {
            throw ValidationException::withMessages([
                'template_id' => __('cms_admin_ui.validation.starter_export_template_layout_mismatch'),
            ]);
        }

        return $template;
    }

    private function page(int $id, CmsLayout $layout, CmsTemplate $template): CmsPage
    {
        $page = CmsPage::query()
            ->with('sections.placements.block')
            ->findOrFail($id);

        if ((int) $page->detail_template_id !== (int) $template->id) {
            throw ValidationException::withMessages([
                'page_id' => __('cms_admin_ui.validation.starter_export_page_mapping_mismatch'),
            ]);
        }

        return $page;
    }

    private function menu(int $id): CmsMenu
    {
        return CmsMenu::query()
            ->with('items.page')
            ->findOrFail($id);
    }

    /**
     * @return array<string, string>
     */
    private function entries(string $key, string $name, CmsLayout $layout, CmsTemplate $template, CmsPage $page, CmsMenu $menu): array
    {
        $layoutKey = $this->importKey($layout, 'layout', 'main');
        $templateKey = $this->importKey($template, 'template', $template->template_key);
        $pageKey = $this->importKey($page, 'page', $page->slug);
        $menuKey = $this->importKey($menu, 'menu', $menu->title ?: 'main');

        return [
            'manifest.json' => $this->json([
                'type' => (string) config('cms_starters.import.manifest_type', 'rwsoft-cms-starter'),
                'key' => $key,
                'name' => $name,
                'version' => 1,
                'modules' => ['layouts', 'templates', 'pages', 'menus'],
            ]),
            'layouts.json' => $this->json([
                [
                    'import_key' => $layoutKey,
                    'name' => $layout->name,
                    'locale' => $layout->locale,
                    'is_active' => (bool) $layout->is_active,
                    'is_default' => (bool) $layout->is_default,
                    'cache_strategy' => $layout->cache_strategy,
                    'settings' => $layout->settings ?? [],
                    'sections' => $this->sections($layout, (int) $menu->id, $menuKey),
                ],
            ]),
            'templates.json' => $this->json([
                [
                    'import_key' => $templateKey,
                    'layout_import_key' => $layoutKey,
                    'name' => $template->name,
                    'locale' => $template->locale,
                    'template_class' => $template->template_class,
                    'template_key' => $template->template_key,
                    'is_active' => (bool) $template->is_active,
                    'is_default' => (bool) $template->is_default,
                    'cache_strategy' => $template->cache_strategy,
                    'settings' => $template->settings ?? [],
                    'data_contract' => $template->data_contract ?? [],
                    'sections' => $this->sections($template, (int) $menu->id, $menuKey),
                ],
            ]),
            'pages.json' => $this->json([
                [
                    'import_key' => $pageKey,
                    'detail_template_import_key' => $templateKey,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'locale' => $page->locale,
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
                    'template_data' => $this->templateData($page->template_data ?? [], $template),
                    'settings' => $this->withoutStarterImportKey($page->settings ?? []),
                ],
            ]),
            'menus.json' => $this->json([
                [
                    'import_key' => $menuKey,
                    'title' => $menu->title,
                    'placements' => array_values((array) ($menu->placements ?? [])),
                    'is_active' => (bool) $menu->is_active,
                    'settings' => $this->withoutStarterImportKey($menu->settings ?? []),
                    'items' => $this->menuItems($menu, $page, $pageKey),
                ],
            ]),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function sections(Model $owner, int $menuId, string $menuKey): array
    {
        return $owner->sections
            ->filter(fn (CmsSection $section): bool => (bool) $section->is_active)
            ->groupBy('zone')
            ->map(fn ($sections) => $sections->values()->map(fn (CmsSection $section): array => [
                'name' => $section->name,
                'is_active' => (bool) $section->is_active,
                'visible_mobile' => (bool) $section->visible_mobile,
                'visible_tablet' => (bool) $section->visible_tablet,
                'visible_desktop' => (bool) $section->visible_desktop,
                'settings' => $section->settings ?? [],
                'placements' => $section->placements
                    ->filter(fn (CmsBlockPlacement $placement): bool => (bool) $placement->is_active)
                    ->values()
                    ->map(fn (CmsBlockPlacement $placement): array => [
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
                        'block' => $this->block($placement->block, $menuId, $menuKey),
                    ])->all(),
            ])->all())
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function block(?CmsBlock $block, int $menuId, string $menuKey): array
    {
        if (! $block instanceof CmsBlock) {
            throw ValidationException::withMessages([
                'starter_export' => __('cms_admin_ui.validation.starter_export_missing_block'),
            ]);
        }

        if (in_array($block->type, ['custom_head_code', 'custom_body_end_code'], true)) {
            throw ValidationException::withMessages([
                'starter_export' => __('cms_admin_ui.validation.starter_export_code_block_forbidden'),
            ]);
        }

        $content = $block->content ?? [];

        if ($block->type === 'site_menu' && (int) ($content['cms_menu_id'] ?? 0) === $menuId) {
            unset($content['cms_menu_id']);
            $content['cms_menu_import_key'] = $menuKey;
        }

        return array_filter([
            'type' => $block->type,
            'cache_strategy' => $block->cache_strategy,
            ...$content,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function templateData(array $data, CmsTemplate $template): array
    {
        $contract = app(CmsTemplateDataContract::class)->normalize($template->data_contract, (string) $template->template_key);

        foreach ($contract['template_fields'] as $field) {
            if (($field['type'] ?? null) === 'media') {
                Arr::set($data, $field['key'], null);
            }
        }

        return $this->withoutStarterImportKey($data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function menuItems(CmsMenu $menu, CmsPage $page, string $pageKey): array
    {
        $itemKeys = [];

        return $menu->items
            ->values()
            ->map(function (CmsMenuItem $item) use ($page, $pageKey, &$itemKeys): array {
                $itemKey = $this->importKey($item, 'menu-item', Str::slug((string) $item->label) ?: 'item-'.$item->id);
                $itemKeys[$item->id] = $itemKey;
                $parentKey = $item->parent_id ? ($itemKeys[$item->parent_id] ?? null) : null;
                $type = $item->type === 'page' && (int) $item->cms_page_id === (int) $page->id ? 'page' : $item->type;

                if ($item->type === 'page' && (int) $item->cms_page_id !== (int) $page->id) {
                    $type = 'custom';
                }

                return array_filter([
                    'import_key' => $itemKey,
                    'parent_import_key' => $parentKey,
                    'type' => $type,
                    'page_import_key' => $type === 'page' ? $pageKey : null,
                    'label' => $item->label,
                    'url' => $type === 'page' ? null : ($item->url ?: $this->pageUrl($item->page)),
                    'target' => $item->target,
                    'rel' => $item->rel,
                    'locale' => $item->locale,
                    'sort_order' => (int) $item->sort_order,
                    'is_active' => (bool) $item->is_active,
                    'metadata' => $this->withoutStarterImportKey($item->metadata ?? []),
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

    private function importKey(Model $model, string $prefix, string $fallback): string
    {
        $importKey = trim((string) ($model->getAttribute('import_key') ?? ''));

        if ($importKey === '') {
            $settings = $model->getAttribute('settings');
            $importKey = is_array($settings) ? (string) ($settings['starter_import_key'] ?? '') : '';
        }

        if (preg_match('/^starter:[^:]+:(.+)$/', $importKey, $matches) === 1) {
            return $matches[1];
        }

        return $prefix.'.'.(Str::slug($fallback) ?: (string) $model->getKey());
    }

    private function starterKey(string $key, CmsPage $page): string
    {
        return Str::slug($key) ?: 'starter-'.$page->id;
    }

    private function starterName(string $name, CmsPage $page): string
    {
        return trim($name) !== '' ? trim($name) : $page->title.' starter';
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function withoutStarterImportKey(array $settings): array
    {
        unset($settings['starter_import_key']);

        return $settings;
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }
}
