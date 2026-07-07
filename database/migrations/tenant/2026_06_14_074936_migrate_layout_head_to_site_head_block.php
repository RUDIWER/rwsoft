<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('cms_layouts')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $layout) use ($now): void {
                $hasSiteHead = DB::table('cms_sections')
                    ->join('cms_block_placements', 'cms_block_placements.cms_section_id', '=', 'cms_sections.id')
                    ->join('cms_blocks', 'cms_blocks.id', '=', 'cms_block_placements.cms_block_id')
                    ->where('cms_sections.owner_type', 'App\\Models\\Cms\\CmsLayout')
                    ->where('cms_sections.owner_id', $layout->id)
                    ->where('cms_sections.zone', 'head')
                    ->where('cms_blocks.type', 'site_head')
                    ->exists();

                if ($hasSiteHead) {
                    return;
                }

                $sectionId = DB::table('cms_sections')->insertGetId([
                    'import_key' => 'layout:'.$layout->id.':head',
                    'owner_type' => 'App\\Models\\Cms\\CmsLayout',
                    'owner_id' => $layout->id,
                    'zone' => 'head',
                    'name' => 'System head',
                    'sort_order' => 0,
                    'is_active' => true,
                    'visible_mobile' => true,
                    'visible_tablet' => true,
                    'visible_desktop' => true,
                    'settings' => json_encode([], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $blockId = DB::table('cms_blocks')->insertGetId([
                    'import_key' => 'layout:'.$layout->id.':site_head',
                    'type' => 'site_head',
                    'name' => 'System head',
                    'content' => json_encode([], JSON_THROW_ON_ERROR),
                    'settings' => json_encode([], JSON_THROW_ON_ERROR),
                    'is_shared' => false,
                    'is_dynamic' => false,
                    'cache_strategy' => 'inherit',
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('cms_block_placements')->insert([
                    'import_key' => 'layout:'.$layout->id.':site_head:placement',
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
                    'settings' => json_encode([], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep generated layout records intact. Removing them could break existing public rendering.
    }
};
