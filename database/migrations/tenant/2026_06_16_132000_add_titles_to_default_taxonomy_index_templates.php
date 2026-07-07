<?php

use App\Models\Cms\CmsTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_templates') || ! Schema::hasTable('cms_sections') || ! Schema::hasTable('cms_blocks') || ! Schema::hasTable('cms_block_placements')) {
            return;
        }

        DB::transaction(function (): void {
            foreach ($this->locales() as $locale) {
                $this->seedTitleBlock($locale, 'category.index', 'category_index.title');
                $this->seedTitleBlock($locale, 'tag.index', 'tag_index.title');
            }
        });
    }

    public function down(): void
    {
        // These seeded template blocks are content defaults and are intentionally not reverted.
    }

    /**
     * @return array<int, string>
     */
    private function locales(): array
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

        return [(string) config('app.locale', 'nl')];
    }

    private function seedTitleBlock(string $locale, string $templateKey, string $fieldKey): void
    {
        $templateImportKey = implode('.', ['cms', 'default-template', $locale, $templateKey]);
        $templateId = DB::table('cms_templates')->where('import_key', $templateImportKey)->value('id');

        if (! $templateId) {
            return;
        }

        $sectionId = DB::table('cms_sections')
            ->where('owner_type', CmsTemplate::class)
            ->where('owner_id', $templateId)
            ->where('zone', 'content')
            ->orderBy('sort_order')
            ->value('id');

        if (! $sectionId) {
            return;
        }

        $now = now();
        $blockImportKey = $templateImportKey.'.block.0';
        $placementImportKey = $templateImportKey.'.placement.0';
        $block = [
            'type' => 'dynamic_field',
            'field_key' => $fieldKey,
            'title' => null,
            'heading_level' => 'h1',
        ];

        DB::table('cms_blocks')->updateOrInsert(
            ['import_key' => $blockImportKey],
            [
                'type' => 'dynamic_field',
                'name' => Str::headline($fieldKey),
                'content' => json_encode($block, JSON_THROW_ON_ERROR),
                'settings' => json_encode([], JSON_THROW_ON_ERROR),
                'is_shared' => false,
                'is_dynamic' => true,
                'cache_strategy' => 'inherit',
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
                'sort_order' => 5,
                'is_active' => true,
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => true,
                'mobile_span' => 12,
                'tablet_span' => 12,
                'desktop_span' => 12,
                'height_mode' => 'auto',
                'height_value' => null,
                'cache_strategy' => 'inherit',
                'settings' => json_encode([
                    'alignment' => 'left',
                    'content_alignment' => 'left',
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
};
