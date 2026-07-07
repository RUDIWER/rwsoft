<?php

namespace App\Actions\Admin\Cms;

use App\Support\Cms\CmsBlockRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncSiteLanguageSwitcherContractAction
{
    public function handle(string $connection = 'tenant'): void
    {
        if (! $this->hasRequiredTables($connection)) {
            return;
        }

        $definition = config('cms_blocks.types.site_language_switcher');

        if (! is_array($definition)) {
            return;
        }

        $blockId = $this->syncPlaceableBlock($connection, $definition);

        if ($blockId === null) {
            return;
        }

        $this->syncExistingBlocks($connection, $blockId, $definition);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function syncPlaceableBlock(string $connection, array $definition): ?int
    {
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
            'category' => (string) ($definition['category'] ?? 'header'),
            'source' => 'system',
            'status' => 'published',
            'allowed_zones' => json_encode(array_values((array) ($definition['zones'] ?? [])), JSON_THROW_ON_ERROR),
            'rendering_mode' => $renderingMode,
            'renderer_key' => 'site_language_switcher',
            'template_source' => $renderingMode === 'safe_blade' ? (string) ($definition['safe_blade_template'] ?? '') : null,
            'css_source' => app(CmsBlockRegistry::class)->cssSourceFor('site_language_switcher', $definition),
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
            'name' => __('cms_admin_ui.'.($definition['label_key'] ?? 'components.block_editor.site_language_switcher')),
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

        $blockId = (int) DB::connection($connection)
            ->table('cms_placeable_blocks')
            ->where('key', 'site_language_switcher')
            ->value('id');

        if ($blockId > 0) {
            DB::connection($connection)
                ->table('cms_placeable_blocks')
                ->where('id', $blockId)
                ->update($blockPayload);
        } else {
            $blockId = (int) DB::connection($connection)
                ->table('cms_placeable_blocks')
                ->insertGetId(array_merge($blockPayload, [
                    'key' => 'site_language_switcher',
                    'created_at' => $now,
                ]));
        }

        DB::connection($connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $blockId)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->update($revisionPayload);

        if (! DB::connection($connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $blockId)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->exists()) {
            $revisionNumber = ((int) DB::connection($connection)
                ->table('cms_placeable_block_revisions')
                ->where('cms_placeable_block_id', $blockId)
                ->max('revision_number')) + 1;

            DB::connection($connection)
                ->table('cms_placeable_block_revisions')
                ->insert(array_merge($revisionPayload, [
                    'cms_placeable_block_id' => $blockId,
                    'revision_number' => $revisionNumber,
                    'created_at' => $now,
                ]));
        }

        return $blockId;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function syncExistingBlocks(string $connection, int $blockId, array $definition): void
    {
        $revisionId = $this->publishedRevisionId($connection, $blockId);
        $defaults = is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [];

        DB::connection($connection)
            ->table('cms_blocks')
            ->where('type', 'site_language_switcher')
            ->orderBy('id')
            ->select(['id', 'content'])
            ->lazyById()
            ->each(function (object $block) use ($connection, $blockId, $revisionId, $defaults): void {
                $content = array_replace($defaults, $this->decodeJsonObject($block->content));

                unset($content['label']);

                DB::connection($connection)
                    ->table('cms_blocks')
                    ->where('id', (int) $block->id)
                    ->update([
                        'cms_placeable_block_id' => $blockId,
                        'placeable_block_revision_id' => $revisionId,
                        'content' => json_encode($content, JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);
            });
    }

    private function publishedRevisionId(string $connection, int $blockId): ?int
    {
        $revisionId = DB::connection($connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $blockId)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->orderByDesc('revision_number')
            ->value('id');

        return is_numeric($revisionId) ? (int) $revisionId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function hasRequiredTables(string $connection): bool
    {
        return Schema::connection($connection)->hasTable('cms_placeable_blocks')
            && Schema::connection($connection)->hasTable('cms_placeable_block_revisions')
            && Schema::connection($connection)->hasTable('cms_blocks');
    }
}
