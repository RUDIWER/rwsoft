<?php

namespace App\Actions\Admin\Cms;

use App\Support\Cms\CmsBlockRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncSiteMenuCmsMenuIdContractAction
{
    public function handle(string $connection = 'tenant'): void
    {
        if (! $this->hasRequiredTables($connection)) {
            return;
        }

        $definition = config('cms_blocks.types.site_menu');

        if (! is_array($definition)) {
            return;
        }

        $blockId = $this->syncPlaceableBlock($connection, $definition);

        if ($blockId === null) {
            return;
        }

        $this->syncExistingBlocks($connection, $blockId);
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
            'category' => (string) ($definition['category'] ?? 'navigation'),
            'source' => 'system',
            'status' => 'published',
            'allowed_zones' => json_encode(array_values((array) ($definition['zones'] ?? [])), JSON_THROW_ON_ERROR),
            'rendering_mode' => $renderingMode,
            'renderer_key' => 'site_menu',
            'template_source' => $renderingMode === 'safe_blade' ? (string) ($definition['safe_blade_template'] ?? '') : null,
            'css_source' => app(CmsBlockRegistry::class)->cssSourceFor('site_menu', $definition),
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

        $blockId = (int) DB::connection($connection)
            ->table('cms_placeable_blocks')
            ->where('key', 'site_menu')
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
                    'key' => 'site_menu',
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

    private function syncExistingBlocks(string $connection, int $blockId): void
    {
        $headerMenuId = $this->menuIdForPlacement($connection, 'header');
        $footerMenuId = $this->menuIdForPlacement($connection, 'footer');
        $revisionId = $this->publishedRevisionId($connection, $blockId);

        DB::connection($connection)
            ->table('cms_blocks')
            ->where('type', 'site_menu')
            ->orderBy('id')
            ->select(['id', 'content'])
            ->lazyById()
            ->each(function (object $block) use ($connection, $blockId, $revisionId, $headerMenuId, $footerMenuId): void {
                $content = $this->decodeJsonObject($block->content);
                $menuId = $this->validExistingMenuId($connection, $content['cms_menu_id'] ?? null)
                    ?? $this->menuIdForBlockPlacement($connection, (int) $block->id, $headerMenuId, $footerMenuId)
                    ?? $this->menuIdForLegacyKey((string) ($content['menu_key'] ?? ''), $headerMenuId, $footerMenuId);

                DB::connection($connection)
                    ->table('cms_blocks')
                    ->where('id', (int) $block->id)
                    ->update([
                        'cms_placeable_block_id' => $blockId,
                        'placeable_block_revision_id' => $revisionId,
                        'content' => json_encode(['cms_menu_id' => $menuId], JSON_THROW_ON_ERROR),
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

    private function menuIdForBlockPlacement(string $connection, int $blockId, ?int $headerMenuId, ?int $footerMenuId): ?int
    {
        $zones = DB::connection($connection)
            ->table('cms_block_placements')
            ->join('cms_sections', 'cms_sections.id', '=', 'cms_block_placements.cms_section_id')
            ->where('cms_block_placements.cms_block_id', $blockId)
            ->where('cms_block_placements.is_active', true)
            ->where('cms_sections.is_active', true)
            ->pluck('cms_sections.zone')
            ->all();

        if (in_array('header', $zones, true)) {
            return $headerMenuId;
        }

        if (in_array('footer', $zones, true)) {
            return $footerMenuId;
        }

        return null;
    }

    private function menuIdForLegacyKey(string $legacyKey, ?int $headerMenuId, ?int $footerMenuId): ?int
    {
        return match ($legacyKey) {
            'header' => $headerMenuId,
            'footer' => $footerMenuId,
            default => null,
        };
    }

    private function menuIdForPlacement(string $connection, string $placement): ?int
    {
        $menuId = DB::connection($connection)
            ->table('cms_menus')
            ->where('is_active', true)
            ->whereJsonContains('placements', $placement)
            ->orderBy('id')
            ->value('id');

        return is_numeric($menuId) ? (int) $menuId : null;
    }

    private function validExistingMenuId(string $connection, mixed $menuId): ?int
    {
        $menuId = is_numeric($menuId) ? (int) $menuId : 0;

        if ($menuId <= 0) {
            return null;
        }

        return DB::connection($connection)
            ->table('cms_menus')
            ->where('id', $menuId)
            ->where('is_active', true)
            ->exists() ? $menuId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function hasRequiredTables(string $connection): bool
    {
        foreach (['cms_placeable_blocks', 'cms_placeable_block_revisions', 'cms_blocks', 'cms_block_placements', 'cms_sections', 'cms_menus'] as $table) {
            if (! Schema::connection($connection)->hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
