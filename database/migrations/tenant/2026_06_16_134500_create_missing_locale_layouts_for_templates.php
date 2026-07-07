<?php

use App\Models\Cms\CmsLayout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_layouts') || ! Schema::hasTable('cms_templates')) {
            return;
        }

        DB::transaction(function (): void {
            $sourceLayout = $this->sourceLayout();

            if (! $sourceLayout) {
                return;
            }

            foreach ($this->activeLocales() as $locale) {
                if (! DB::table('cms_layouts')->where('locale', $locale)->where('is_active', true)->exists()) {
                    $this->cloneLayoutForLocale($sourceLayout, $locale);
                }

                $layoutId = DB::table('cms_layouts')
                    ->where('locale', $locale)
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->value('id');

                if ($layoutId) {
                    DB::table('cms_templates')
                        ->where('locale', $locale)
                        ->whereNull('layout_id')
                        ->update(['layout_id' => $layoutId]);
                }
            }
        });
    }

    public function down(): void
    {
        // Seeded locale layouts are content defaults and are intentionally not reverted.
    }

    private function sourceLayout(): ?object
    {
        return DB::table('cms_layouts')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array<int, string>
     */
    private function activeLocales(): array
    {
        if (Schema::hasTable('cms_languages')) {
            $locales = DB::table('cms_languages')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('locale')
                ->filter()
                ->values()
                ->all();

            if ($locales !== []) {
                return $locales;
            }
        }

        return DB::table('cms_templates')
            ->distinct()
            ->pluck('locale')
            ->filter()
            ->values()
            ->all();
    }

    private function cloneLayoutForLocale(object $sourceLayout, string $locale): void
    {
        $now = now();
        $layoutImportKey = 'cms.default-layout.'.$locale;

        DB::table('cms_layouts')->updateOrInsert(
            ['import_key' => $layoutImportKey],
            [
                'name' => 'Default layout ('.strtoupper($locale).')',
                'locale' => $locale,
                'translation_key' => $layoutImportKey,
                'translated_from_layout_id' => $sourceLayout->id,
                'is_default' => true,
                'is_active' => true,
                'cache_strategy' => $sourceLayout->cache_strategy ?? 'inherit',
                'settings' => $sourceLayout->settings,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $layoutId = (int) DB::table('cms_layouts')->where('import_key', $layoutImportKey)->value('id');
        $sections = DB::table('cms_sections')
            ->where('owner_type', CmsLayout::class)
            ->where('owner_id', $sourceLayout->id)
            ->orderBy('sort_order')
            ->get();

        foreach ($sections as $section) {
            $sectionImportKey = $layoutImportKey.'.section.'.$section->zone.'.'.$section->sort_order;

            DB::table('cms_sections')->updateOrInsert(
                ['import_key' => $sectionImportKey],
                [
                    'owner_type' => CmsLayout::class,
                    'owner_id' => $layoutId,
                    'zone' => $section->zone,
                    'name' => $section->name,
                    'sort_order' => $section->sort_order,
                    'is_active' => $section->is_active,
                    'visible_mobile' => $section->visible_mobile,
                    'visible_tablet' => $section->visible_tablet,
                    'visible_desktop' => $section->visible_desktop,
                    'settings' => $section->settings,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $sectionId = (int) DB::table('cms_sections')->where('import_key', $sectionImportKey)->value('id');
            $placements = DB::table('cms_block_placements')
                ->where('cms_section_id', $section->id)
                ->orderBy('sort_order')
                ->get();

            foreach ($placements as $placement) {
                $sourceBlock = DB::table('cms_blocks')->where('id', $placement->cms_block_id)->first();

                if (! $sourceBlock) {
                    continue;
                }

                $blockImportKey = $sectionImportKey.'.block.'.$placement->sort_order;
                $placementImportKey = $sectionImportKey.'.placement.'.$placement->sort_order;

                DB::table('cms_blocks')->updateOrInsert(
                    ['import_key' => $blockImportKey],
                    [
                        'type' => $sourceBlock->type,
                        'name' => $sourceBlock->name ?: Str::headline((string) $sourceBlock->type),
                        'content' => $sourceBlock->content,
                        'settings' => $sourceBlock->settings,
                        'is_shared' => false,
                        'is_dynamic' => $sourceBlock->is_dynamic,
                        'cache_strategy' => $sourceBlock->cache_strategy,
                        'created_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                $blockId = (int) DB::table('cms_blocks')->where('import_key', $blockImportKey)->value('id');

                DB::table('cms_block_placements')->updateOrInsert(
                    ['import_key' => $placementImportKey],
                    [
                        'cms_section_id' => $sectionId,
                        'cms_block_id' => $blockId,
                        'sort_order' => $placement->sort_order,
                        'is_active' => $placement->is_active,
                        'visible_mobile' => $placement->visible_mobile,
                        'visible_tablet' => $placement->visible_tablet,
                        'visible_desktop' => $placement->visible_desktop,
                        'mobile_span' => $placement->mobile_span,
                        'tablet_span' => $placement->tablet_span,
                        'desktop_span' => $placement->desktop_span,
                        'height_mode' => $placement->height_mode,
                        'height_value' => $placement->height_value,
                        'cache_strategy' => $placement->cache_strategy,
                        'settings' => $placement->settings,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }
};
