<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! $this->hasRequiredTables()) {
            return;
        }

        DB::table('cms_layouts')
            ->where('is_default', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $layout): void {
                $this->ensureSystemPlacement((int) $layout->id, 'header', 'site_header', 'System header', ['sticky_header' => true]);
                $this->ensureSystemPlacement((int) $layout->id, 'footer', 'site_footer', 'System footer', ['sticky_footer' => true]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: generated system blocks remain editable layout data.
    }

    private function hasRequiredTables(): bool
    {
        return Schema::hasTable('cms_layouts')
            && Schema::hasTable('cms_sections')
            && Schema::hasTable('cms_blocks')
            && Schema::hasTable('cms_block_placements');
    }

    /**
     * @param  array<string, bool>  $placementSettings
     */
    private function ensureSystemPlacement(int $layoutId, string $zone, string $blockType, string $name, array $placementSettings): void
    {
        $now = now();
        $sectionImportKey = "system-layout-{$layoutId}-{$zone}";
        $blockImportKey = "system-layout-{$layoutId}-{$zone}-{$blockType}";
        $placementImportKey = "system-layout-{$layoutId}-{$zone}-placement";

        DB::table('cms_sections')->updateOrInsert(
            ['import_key' => $sectionImportKey],
            [
                'owner_type' => 'App\\Models\\Cms\\CmsLayout',
                'owner_id' => $layoutId,
                'zone' => $zone,
                'name' => $name,
                'sort_order' => 0,
                'is_active' => true,
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => true,
                'settings' => json_encode(['source' => 'system_partial_migration']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $sectionId = (int) DB::table('cms_sections')
            ->where('import_key', $sectionImportKey)
            ->value('id');

        DB::table('cms_blocks')->updateOrInsert(
            ['import_key' => $blockImportKey],
            [
                'type' => $blockType,
                'name' => $name,
                'content' => json_encode([]),
                'settings' => json_encode(['source' => 'system_partial_migration']),
                'is_shared' => false,
                'is_dynamic' => false,
                'cache_strategy' => 'inherit',
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $blockId = (int) DB::table('cms_blocks')
            ->where('import_key', $blockImportKey)
            ->value('id');

        DB::table('cms_block_placements')->updateOrInsert(
            ['import_key' => $placementImportKey],
            [
                'cms_section_id' => $sectionId,
                'cms_block_id' => $blockId,
                'sort_order' => 0,
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
                'settings' => json_encode($placementSettings),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }
};
