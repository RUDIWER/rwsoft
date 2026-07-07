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

        foreach ((array) config('cms_blocks.types', []) as $blockKey => $definition) {
            if (! is_string($blockKey) || ! is_array($definition)) {
                continue;
            }

            $this->syncBlockDefinition($blockKey, $definition);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Development data sync only. Do not strip fields, slots or templates from existing definitions.
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function syncBlockDefinition(string $blockKey, array $definition): void
    {
        $blockId = DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', $blockKey)
            ->value('id');

        if (! $blockId) {
            return;
        }

        $zones = $this->zones($definition);
        $category = $this->category($definition);
        $renderingMode = (string) ($definition['rendering_mode'] ?? 'platform_blade');

        $payload = [
            'category' => $category,
            'allowed_zones' => json_encode($zones, JSON_THROW_ON_ERROR),
            'rendering_mode' => $renderingMode,
            'renderer_key' => $blockKey,
            'template_source' => $renderingMode === 'safe_blade' ? (string) ($definition['safe_blade_template'] ?? '') : null,
            'schema' => json_encode($this->editorSchema($definition), JSON_THROW_ON_ERROR),
            'defaults' => json_encode((array) ($definition['defaults'] ?? []), JSON_THROW_ON_ERROR),
            'capabilities' => json_encode($this->capabilities($category, $renderingMode), JSON_THROW_ON_ERROR),
            'requires_permission' => is_string($definition['requires_permission'] ?? null) ? $definition['requires_permission'] : null,
            'updated_at' => now(),
        ];

        DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('id', $blockId)
            ->update($payload);

        if (! Schema::connection($this->connection)->hasTable('cms_placeable_block_revisions')) {
            return;
        }

        DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $blockId)
            ->where('status', 'published')
            ->update($payload);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, string>
     */
    private function zones(array $definition): array
    {
        return array_values(array_filter(
            (array) ($definition['zones'] ?? []),
            fn (mixed $zone): bool => is_string($zone) && $zone !== '',
        ));
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
     * @return array<string, bool>
     */
    private function capabilities(string $category, string $renderingMode): array
    {
        return [
            'can_edit_template' => $renderingMode === 'safe_blade' && $category !== 'system',
            'can_edit_css' => $category !== 'system' || $renderingMode !== 'platform_blade',
            'can_edit_fields' => $category !== 'system',
            'can_edit_allowed_zones' => $category !== 'system',
            'can_edit_renderer' => false,
            'can_edit_defaults' => $category !== 'system',
            'can_edit_category' => $category !== 'system',
            'can_edit_admin_component' => false,
            'can_edit_slots' => $category !== 'system',
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function editorSchema(array $definition): array
    {
        return [
            'category' => $definition['category'] ?? null,
            'fields' => array_values((array) ($definition['fields'] ?? [])),
            'editor_fields' => array_values((array) data_get($definition, 'editor.fields', [])),
            'editor_visible' => (bool) ($definition['editor_visible'] ?? true),
            'preview' => is_array($definition['preview'] ?? null) ? $definition['preview'] : [],
            'slots' => array_values((array) ($definition['slots'] ?? [])),
        ];
    }
};
