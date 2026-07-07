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
        if (! Schema::connection($this->connection)->hasTable('cms_placeable_blocks')) {
            return;
        }

        foreach (['button', 'site_logo', 'site_brand', 'site_promo', 'site_link', 'site_button'] as $blockKey) {
            $this->syncBlockDefinition($blockKey);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Development data sync only. Do not strip fields from existing definitions.
    }

    private function syncBlockDefinition(string $blockKey): void
    {
        $definition = config("cms_blocks.types.{$blockKey}");

        if (! is_array($definition)) {
            return;
        }

        $schema = DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', $blockKey)
            ->value('schema');

        $schema = is_string($schema) && $schema !== '' ? json_decode($schema, true) : [];
        $schema = is_array($schema) ? $schema : [];
        $schema['fields'] = array_values((array) ($definition['fields'] ?? []));
        $schema['editor_fields'] = array_values((array) data_get($definition, 'editor.fields', []));

        if (array_key_exists('slots', $definition)) {
            $schema['slots'] = array_values((array) ($definition['slots'] ?? []));
        }

        $payload = [
            'schema' => json_encode($schema, JSON_THROW_ON_ERROR),
            'defaults' => json_encode((array) ($definition['defaults'] ?? []), JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ];

        if (isset($definition['safe_blade_template'])) {
            $payload['template_source'] = (string) $definition['safe_blade_template'];
        }

        DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', $blockKey)
            ->update($payload);

        if (! Schema::connection($this->connection)->hasTable('cms_placeable_block_revisions')) {
            return;
        }

        DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->whereIn('cms_placeable_block_id', function ($query) use ($blockKey): void {
                $query->select('id')
                    ->from('cms_placeable_blocks')
                    ->where('key', $blockKey);
            })
            ->where('status', 'published')
            ->update($payload);
    }
};
