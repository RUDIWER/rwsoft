<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_blocks')
            || ! Schema::connection($this->connection)->hasColumn('cms_blocks', 'content')) {
            return;
        }

        $allowedFieldsByType = [
            'site_brand' => ['title', 'link_url'],
            'site_logo' => ['media_asset_id', 'alt_text', 'link_url', 'logo_size'],
            'site_baseline' => ['text'],
        ];

        DB::connection($this->connection)
            ->table('cms_blocks')
            ->whereIn('type', array_keys($allowedFieldsByType))
            ->orderBy('id')
            ->chunkById(100, function ($blocks) use ($allowedFieldsByType): void {
                foreach ($blocks as $block) {
                    $content = json_decode((string) ($block->content ?? '{}'), true);
                    $content = is_array($content) ? $content : [];
                    $allowedFields = array_flip($allowedFieldsByType[(string) $block->type] ?? []);

                    DB::connection($this->connection)
                        ->table('cms_blocks')
                        ->where('id', (int) $block->id)
                        ->update([
                            'content' => json_encode(array_intersect_key($content, $allowedFields), JSON_THROW_ON_ERROR),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally irreversible: obsolete development fields are removed from header blocks.
    }
};
