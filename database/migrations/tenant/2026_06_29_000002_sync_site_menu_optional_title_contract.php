<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_placeable_blocks') || ! Schema::connection($this->connection)->hasTable('cms_placeable_block_revisions')) {
            return;
        }

        $definition = config('cms_blocks.types.site_menu');

        if (! is_array($definition)) {
            return;
        }

        $schema = [
            'category' => $definition['category'] ?? 'navigation',
            'fields' => array_values((array) ($definition['fields'] ?? [])),
            'editor_fields' => array_values((array) data_get($definition, 'editor.fields', [])),
            'editor_visible' => (bool) ($definition['editor_visible'] ?? true),
            'preview' => is_array($definition['preview'] ?? null) ? $definition['preview'] : [],
        ];
        $now = now();
        $updates = [
            'schema' => json_encode($schema, JSON_THROW_ON_ERROR),
            'defaults' => json_encode(is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [], JSON_THROW_ON_ERROR),
            'updated_at' => $now,
        ];

        DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', 'site_menu')
            ->update($updates);

        $blockId = (int) DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', 'site_menu')
            ->value('id');

        if ($blockId <= 0) {
            return;
        }

        DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $blockId)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->update($updates);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: this syncs site_menu to the current system contract.
    }
};
