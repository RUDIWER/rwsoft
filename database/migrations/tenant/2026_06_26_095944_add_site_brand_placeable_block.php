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

        $this->publishConfiguredBlock('site_brand');
        $this->syncEditorVisibility('site_header');
        $this->syncEditorVisibility('site_footer');
    }

    public function down(): void
    {
        // Intentionally irreversible: published block catalog records remain tenant data.
    }

    private function publishConfiguredBlock(string $rendererKey): void
    {
        $definition = config("cms_blocks.types.{$rendererKey}");

        if (! is_array($definition)) {
            return;
        }

        $now = now();
        $renderingMode = (string) ($definition['rendering_mode'] ?? 'platform_blade');
        $zones = $this->zones($definition);

        if ($zones === []) {
            return;
        }

        $payload = [
            'name' => $this->placeableBlockName($rendererKey, $definition),
            'description' => null,
            'category' => $this->category($definition),
            'source' => 'system',
            'status' => 'published',
            'allowed_zones' => json_encode($zones, JSON_THROW_ON_ERROR),
            'rendering_mode' => $renderingMode,
            'renderer_key' => $rendererKey,
            'template_source' => $renderingMode === 'safe_blade' ? (string) ($definition['safe_blade_template'] ?? '') : null,
            'css_source' => null,
            'schema' => json_encode($this->editorSchema($definition), JSON_THROW_ON_ERROR),
            'defaults' => json_encode(is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [], JSON_THROW_ON_ERROR),
            'capabilities' => json_encode($this->capabilities($definition, $renderingMode), JSON_THROW_ON_ERROR),
            'behavior_config' => json_encode([], JSON_THROW_ON_ERROR),
            'context_config' => json_encode([], JSON_THROW_ON_ERROR),
            'admin_component_key' => null,
            'package_key' => null,
            'sort_order' => 0,
            'is_locked' => $this->category($definition) === 'system',
            'requires_permission' => is_string($definition['requires_permission'] ?? null) ? $definition['requires_permission'] : null,
            'published_at' => $now,
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        $blockId = (int) DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', $rendererKey)
            ->value('id');

        if ($blockId > 0) {
            DB::connection($this->connection)
                ->table('cms_placeable_blocks')
                ->where('id', $blockId)
                ->update($payload);
        } else {
            $blockId = (int) DB::connection($this->connection)
                ->table('cms_placeable_blocks')
                ->insertGetId(array_merge($payload, [
                    'key' => $rendererKey,
                    'created_at' => $now,
                ]));
        }

        $this->publishRevision($blockId, $rendererKey, $payload, $now);
    }

    private function syncEditorVisibility(string $rendererKey): void
    {
        $definition = config("cms_blocks.types.{$rendererKey}");

        if (! is_array($definition)) {
            return;
        }

        $block = DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', $rendererKey)
            ->first(['id', 'schema']);

        if (! $block) {
            return;
        }

        $schema = json_decode((string) ($block->schema ?? '{}'), true);
        $schema = is_array($schema) ? $schema : [];
        $schema['editor_visible'] = (bool) ($definition['editor_visible'] ?? true);
        $encodedSchema = json_encode($schema, JSON_THROW_ON_ERROR);

        DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('id', (int) $block->id)
            ->update([
                'schema' => $encodedSchema,
                'updated_at' => now(),
            ]);

        DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', (int) $block->id)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->update([
                'schema' => $encodedSchema,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, string>
     */
    private function zones(array $definition): array
    {
        return array_values(array_filter((array) ($definition['zones'] ?? []), fn (mixed $zone): bool => is_string($zone) && $zone !== ''));
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
     * @return array<string, bool>
     */
    private function capabilities(array $definition, string $renderingMode): array
    {
        $category = $this->category($definition);

        return [
            'can_edit_template' => $renderingMode === 'safe_blade' && $category !== 'system',
            'can_edit_css' => $category !== 'system' || $renderingMode !== 'platform_blade',
            'can_edit_fields' => $category !== 'system',
            'can_edit_allowed_zones' => $category !== 'system',
            'can_edit_renderer' => false,
            'can_edit_defaults' => $category !== 'system',
            'can_edit_category' => $category !== 'system',
            'can_edit_admin_component' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function placeableBlockName(string $rendererKey, array $definition): string
    {
        $labelKey = (string) ($definition['label_key'] ?? '');

        if ($labelKey !== '') {
            return __('cms_admin_ui.'.$labelKey);
        }

        return str($rendererKey)->replace('_', ' ')->title()->toString();
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
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publishRevision(int $blockId, string $rendererKey, array $payload, mixed $now): void
    {
        if ($blockId <= 0) {
            return;
        }

        $snapshotHash = hash('sha256', json_encode([
            'key' => $rendererKey,
            'category' => $payload['category'],
            'source' => $payload['source'],
            'allowed_zones' => $payload['allowed_zones'],
            'rendering_mode' => $payload['rendering_mode'],
            'renderer_key' => $rendererKey,
            'template_source' => $payload['template_source'],
            'css_source' => $payload['css_source'],
            'schema' => $payload['schema'],
            'defaults' => $payload['defaults'],
            'capabilities' => $payload['capabilities'],
            'admin_component_key' => $payload['admin_component_key'],
            'package_key' => $payload['package_key'],
            'sort_order' => $payload['sort_order'],
            'is_locked' => $payload['is_locked'],
        ], JSON_THROW_ON_ERROR));

        $exists = DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $blockId)
            ->where('snapshot_hash', $snapshotHash)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->exists();

        if ($exists) {
            return;
        }

        $revisionNumber = ((int) DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $blockId)
            ->max('revision_number')) + 1;

        DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->insert([
                'cms_placeable_block_id' => $blockId,
                'revision_number' => $revisionNumber,
                'status' => 'published',
                'title' => $payload['name'],
                'category' => $payload['category'],
                'source' => $payload['source'],
                'allowed_zones' => $payload['allowed_zones'],
                'rendering_mode' => $payload['rendering_mode'],
                'renderer_key' => $rendererKey,
                'template_source' => $payload['template_source'],
                'css_source' => $payload['css_source'],
                'schema' => $payload['schema'],
                'defaults' => $payload['defaults'],
                'capabilities' => $payload['capabilities'],
                'behavior_config' => $payload['behavior_config'],
                'context_config' => $payload['context_config'],
                'admin_component_key' => $payload['admin_component_key'],
                'package_key' => $payload['package_key'],
                'sort_order' => $payload['sort_order'],
                'is_locked' => $payload['is_locked'],
                'requires_permission' => $payload['requires_permission'],
                'snapshot_hash' => $snapshotHash,
                'metadata' => json_encode(['source' => 'add_site_brand_placeable_block'], JSON_THROW_ON_ERROR),
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
    }
};
