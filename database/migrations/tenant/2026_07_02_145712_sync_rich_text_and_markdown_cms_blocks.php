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

        foreach (['rich_text', 'markdown_text'] as $blockKey) {
            $definition = config('cms_blocks.types.'.$blockKey);

            if (! is_array($definition)) {
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
        // Development data sync only. Do not remove block definitions from existing tenants.
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function syncBlockDefinition(string $blockKey, array $definition): void
    {
        $now = now();
        $category = $this->category($definition);
        $renderingMode = (string) ($definition['rendering_mode'] ?? 'platform_blade');
        $payload = [
            'name' => $this->placeableBlockName($blockKey, $definition),
            'description' => null,
            'category' => $category,
            'source' => 'system',
            'status' => 'published',
            'allowed_zones' => json_encode($this->zones($definition), JSON_THROW_ON_ERROR),
            'rendering_mode' => $renderingMode,
            'renderer_key' => $blockKey,
            'template_source' => $renderingMode === 'safe_blade' ? (string) ($definition['safe_blade_template'] ?? '') : null,
            'css_source' => null,
            'schema' => json_encode($this->editorSchema($definition), JSON_THROW_ON_ERROR),
            'defaults' => json_encode((array) ($definition['defaults'] ?? []), JSON_THROW_ON_ERROR),
            'capabilities' => json_encode($this->capabilities($category, $renderingMode), JSON_THROW_ON_ERROR),
            'behavior_config' => json_encode([], JSON_THROW_ON_ERROR),
            'context_config' => json_encode([], JSON_THROW_ON_ERROR),
            'admin_component_key' => null,
            'package_key' => null,
            'sort_order' => 0,
            'is_locked' => false,
            'requires_permission' => is_string($definition['requires_permission'] ?? null) ? $definition['requires_permission'] : null,
            'published_at' => $now,
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        $blockId = (int) DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', $blockKey)
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
                    'key' => $blockKey,
                    'created_at' => $now,
                ]));
        }

        $this->publishRevision($blockId, $blockKey, $payload, $now);
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
     */
    private function placeableBlockName(string $blockKey, array $definition): string
    {
        $labelKey = (string) ($definition['label_key'] ?? '');

        if ($labelKey !== '') {
            return __('cms_admin_ui.'.$labelKey);
        }

        return str($blockKey)->replace('_', ' ')->title()->toString();
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publishRevision(int $blockId, string $blockKey, array $payload, mixed $now): void
    {
        if ($blockId <= 0 || ! Schema::connection($this->connection)->hasTable('cms_placeable_block_revisions')) {
            return;
        }

        $snapshotHash = hash('sha256', json_encode([
            'key' => $blockKey,
            'category' => $payload['category'],
            'source' => $payload['source'],
            'allowed_zones' => $payload['allowed_zones'],
            'rendering_mode' => $payload['rendering_mode'],
            'renderer_key' => $blockKey,
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
                'renderer_key' => $blockKey,
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
                'metadata' => json_encode(['source' => 'configured_placeable_blocks'], JSON_THROW_ON_ERROR),
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
    }
};
