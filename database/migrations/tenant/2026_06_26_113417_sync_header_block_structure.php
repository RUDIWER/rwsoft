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

        foreach (['site_brand', 'site_logo', 'site_baseline'] as $rendererKey) {
            $this->publishConfiguredBlock($rendererKey);
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: header block structure follows the current platform config.
    }

    private function publishConfiguredBlock(string $rendererKey): void
    {
        $definition = config("cms_blocks.types.{$rendererKey}");

        if (! is_array($definition)) {
            return;
        }

        $now = now();
        $renderingMode = (string) ($definition['rendering_mode'] ?? 'platform_blade');
        $name = $this->placeableBlockName($rendererKey, $definition);
        $sharedPayload = [
            'category' => $this->category($definition),
            'source' => 'system',
            'status' => 'published',
            'allowed_zones' => json_encode($this->zones($definition), JSON_THROW_ON_ERROR),
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
            'is_locked' => false,
            'requires_permission' => is_string($definition['requires_permission'] ?? null) ? $definition['requires_permission'] : null,
        ];
        $blockPayload = array_merge($sharedPayload, [
            'name' => $name,
            'description' => null,
            'published_at' => $now,
            'deleted_at' => null,
            'updated_at' => $now,
        ]);
        $revisionPayload = array_merge($sharedPayload, [
            'title' => $name,
            'published_at' => $now,
            'snapshot_hash' => $this->snapshotHash($sharedPayload),
            'updated_at' => $now,
        ]);

        $blockId = (int) DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', $rendererKey)
            ->value('id');

        if ($blockId > 0) {
            DB::connection($this->connection)
                ->table('cms_placeable_blocks')
                ->where('id', $blockId)
                ->update($blockPayload);
        } else {
            $blockId = (int) DB::connection($this->connection)
                ->table('cms_placeable_blocks')
                ->insertGetId(array_merge($blockPayload, [
                    'key' => $rendererKey,
                    'created_at' => $now,
                ]));
        }

        DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $blockId)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->update($revisionPayload);

        if (! DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $blockId)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->exists()) {
            $revisionNumber = ((int) DB::connection($this->connection)
                ->table('cms_placeable_block_revisions')
                ->where('cms_placeable_block_id', $blockId)
                ->max('revision_number')) + 1;

            DB::connection($this->connection)
                ->table('cms_placeable_block_revisions')
                ->insert(array_merge($revisionPayload, [
                    'cms_placeable_block_id' => $blockId,
                    'revision_number' => $revisionNumber,
                    'created_at' => $now,
                ]));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function snapshotHash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
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
        return array_values(array_filter((array) ($definition['zones'] ?? []), fn (mixed $zone): bool => is_string($zone) && $zone !== ''));
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
};
