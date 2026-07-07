<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_menus') || ! Schema::hasTable('cms_menu_items')) {
            return;
        }

        $headerMenuIds = DB::table('cms_menus')
            ->where('location', 'header')
            ->pluck('id');

        if ($headerMenuIds->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($this->blogMenuItems() as $item) {
            DB::table('cms_menu_items')
                ->whereIn('cms_menu_id', $headerMenuIds)
                ->where('locale', $item['locale'])
                ->where(function ($query) use ($item): void {
                    $query
                        ->where('label', 'like', 'Blog%')
                        ->orWhere('url', $item['legacy_url']);
                })
                ->update([
                    'type' => 'custom',
                    'label' => $item['label'],
                    'url' => $item['url'],
                    'cms_page_id' => null,
                    'cms_post_id' => null,
                    'target' => null,
                    'rel' => null,
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // Menu changes are content updates and are intentionally not reverted.
    }

    /**
     * @return array<int, array{locale: string, label: string, url: string, legacy_url: string}>
     */
    private function blogMenuItems(): array
    {
        return [
            ['locale' => 'nl', 'label' => 'Blogs', 'url' => '/nl/blogs', 'legacy_url' => '/nl/inzichten'],
            ['locale' => 'en', 'label' => 'Blogs', 'url' => '/en/blogs', 'legacy_url' => '/en/insights'],
            ['locale' => 'fr', 'label' => 'Blogs', 'url' => '/fr/blogs', 'legacy_url' => '/fr/insights'],
        ];
    }
};
