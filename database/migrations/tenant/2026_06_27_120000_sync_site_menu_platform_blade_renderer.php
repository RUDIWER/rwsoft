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

        $definition = config('cms_blocks.types.site_menu');

        if (! is_array($definition)) {
            return;
        }

        $now = now();
        $renderingMode = (string) ($definition['rendering_mode'] ?? 'platform_blade');
        $schema = [
            'category' => $definition['category'] ?? null,
            'fields' => array_values((array) ($definition['fields'] ?? [])),
            'editor_fields' => array_values((array) data_get($definition, 'editor.fields', [])),
            'editor_visible' => (bool) ($definition['editor_visible'] ?? true),
            'preview' => is_array($definition['preview'] ?? null) ? $definition['preview'] : [],
        ];
        $sharedPayload = [
            'category' => (string) ($definition['category'] ?? 'navigation'),
            'source' => 'system',
            'status' => 'published',
            'allowed_zones' => json_encode(array_values((array) ($definition['zones'] ?? [])), JSON_THROW_ON_ERROR),
            'rendering_mode' => $renderingMode,
            'renderer_key' => 'site_menu',
            'template_source' => $renderingMode === 'safe_blade' ? (string) ($definition['safe_blade_template'] ?? '') : null,
            'css_source' => null,
            'schema' => json_encode($schema, JSON_THROW_ON_ERROR),
            'defaults' => json_encode(is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [], JSON_THROW_ON_ERROR),
            'capabilities' => json_encode([
                'can_edit_template' => true,
                'can_edit_css' => true,
                'can_edit_fields' => true,
                'can_edit_allowed_zones' => true,
                'can_edit_renderer' => false,
                'can_edit_defaults' => true,
                'can_edit_category' => true,
                'can_edit_admin_component' => false,
            ], JSON_THROW_ON_ERROR),
            'behavior_config' => json_encode([], JSON_THROW_ON_ERROR),
            'context_config' => json_encode([], JSON_THROW_ON_ERROR),
            'admin_component_key' => null,
            'package_key' => null,
            'sort_order' => 0,
            'is_locked' => false,
            'requires_permission' => is_string($definition['requires_permission'] ?? null) ? $definition['requires_permission'] : null,
        ];
        $blockPayload = array_merge($sharedPayload, [
            'name' => __('cms_admin_ui.'.($definition['label_key'] ?? 'components.block_editor.site_menu')),
            'description' => null,
            'published_at' => $now,
            'deleted_at' => null,
            'updated_at' => $now,
        ]);
        $revisionPayload = array_merge($sharedPayload, [
            'title' => $blockPayload['name'],
            'published_at' => $now,
            'snapshot_hash' => hash('sha256', json_encode($sharedPayload, JSON_THROW_ON_ERROR)),
            'updated_at' => $now,
        ]);

        $blockId = (int) DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', 'site_menu')
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
                    'key' => 'site_menu',
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

    public function down(): void
    {
        // Intentionally irreversible: site_menu now renders through the platform Blade component.
    }
};
