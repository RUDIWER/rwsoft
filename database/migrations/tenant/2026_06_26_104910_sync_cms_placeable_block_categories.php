<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_placeable_blocks')
            || ! Schema::connection($this->connection)->hasTable('cms_placeable_block_revisions')) {
            return;
        }

        foreach ((array) config('cms_blocks.types', []) as $rendererKey => $definition) {
            if (! is_string($rendererKey) || ! is_array($definition)) {
                continue;
            }

            $this->syncConfiguredBlock($rendererKey, $definition);
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: catalog metadata follows current platform config.
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function syncConfiguredBlock(string $rendererKey, array $definition): void
    {
        $block = DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', $rendererKey)
            ->orWhere('renderer_key', $rendererKey)
            ->first(['id', 'schema']);

        if (! $block) {
            return;
        }

        $category = $this->category($definition);
        $schema = json_decode((string) ($block->schema ?? '{}'), true);
        $schema = is_array($schema) ? $schema : [];
        $schema['category'] = $category;
        $schema['fields'] = array_values((array) ($definition['fields'] ?? []));
        $schema['editor_fields'] = array_values((array) data_get($definition, 'editor.fields', []));
        $schema['editor_visible'] = (bool) ($definition['editor_visible'] ?? true);
        $schema['preview'] = is_array($definition['preview'] ?? null) ? $definition['preview'] : [];

        $payload = [
            'category' => $category,
            'allowed_zones' => json_encode($this->zones($definition), JSON_THROW_ON_ERROR),
            'rendering_mode' => (string) ($definition['rendering_mode'] ?? 'platform_blade'),
            'renderer_key' => $rendererKey,
            'schema' => json_encode($schema, JSON_THROW_ON_ERROR),
            'defaults' => json_encode(is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ];

        DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('id', (int) $block->id)
            ->update($payload);

        DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', (int) $block->id)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->update($payload);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function category(array $definition): string
    {
        $category = (string) ($definition['category'] ?? 'content');

        return in_array($category, ['content', 'header', 'navigation', 'system', 'code'], true)
            ? $category
            : 'content';
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, string>
     */
    private function zones(array $definition): array
    {
        return array_values(array_filter(
            (array) ($definition['zones'] ?? []),
            fn (mixed $zone): bool => is_string($zone) && $zone !== ''
        ));
    }
};
