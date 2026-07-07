<?php

use App\Models\Cms\CmsTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_templates')
            || ! Schema::connection($this->connection)->hasTable('cms_sections')
            || ! Schema::connection($this->connection)->hasTable('cms_blocks')
            || ! Schema::connection($this->connection)->hasTable('cms_block_placements')) {
            return;
        }

        DB::connection($this->connection)->transaction(function (): void {
            foreach (['site_search', 'docs_search'] as $blockKey) {
                $definition = config('cms_blocks.types.'.$blockKey);

                if (is_array($definition)) {
                    $this->syncPlaceableBlock($blockKey, $definition);
                }
            }

            foreach ($this->locales() as $locale) {
                $this->seedSearchTemplate($locale);
            }
        });
    }

    public function down(): void
    {
        // Generated search template defaults are intentionally kept.
    }

    /**
     * @return array<int, string>
     */
    private function locales(): array
    {
        if (Schema::connection($this->connection)->hasTable('cms_languages')) {
            $locales = DB::connection($this->connection)
                ->table('cms_languages')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('locale')
                ->filter()
                ->values()
                ->all();

            if ($locales !== []) {
                return $locales;
            }
        }

        return [(string) config('app.locale', 'en')];
    }

    private function seedSearchTemplate(string $locale): void
    {
        $now = now();
        $templateImportKey = implode('.', ['cms', 'default-template', $locale, 'search.index']);
        $layoutId = Schema::connection($this->connection)->hasTable('cms_layouts')
            ? DB::connection($this->connection)->table('cms_layouts')->where('locale', $locale)->where('is_default', true)->value('id')
            : null;
        $payload = [
            'name' => 'Search ('.strtoupper($locale).')',
            'locale' => $locale,
            'translation_key' => 'cms.default-template.search.index',
            'layout_id' => $layoutId,
            'template_class' => 'search',
            'template_key' => 'search.index',
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => json_encode([], JSON_THROW_ON_ERROR),
            'updated_at' => $now,
        ];

        if (Schema::connection($this->connection)->hasColumn('cms_templates', 'module_key')) {
            $payload['module_key'] = null;
        }

        DB::connection($this->connection)->table('cms_templates')->updateOrInsert(
            ['import_key' => $templateImportKey],
            array_merge($payload, ['created_at' => $now])
        );

        $templateId = (int) DB::connection($this->connection)->table('cms_templates')->where('import_key', $templateImportKey)->value('id');
        $sectionId = $this->seedSection($templateId, $templateImportKey, $now);
        $this->seedBlockPlacement($sectionId, $templateImportKey, $now);
    }

    private function seedSection(int $templateId, string $templateImportKey, mixed $now): int
    {
        $importKey = $templateImportKey.'.content';

        DB::connection($this->connection)->table('cms_sections')->updateOrInsert(
            ['import_key' => $importKey],
            [
                'owner_type' => CmsTemplate::class,
                'owner_id' => $templateId,
                'zone' => 'content',
                'name' => 'Search',
                'sort_order' => 10,
                'is_active' => true,
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => true,
                'settings' => json_encode(['layout_type' => 'standard', 'width_mode' => 'content', 'spacing' => 'normal'], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::connection($this->connection)->table('cms_sections')->where('import_key', $importKey)->value('id');
    }

    private function seedBlockPlacement(int $sectionId, string $templateImportKey, mixed $now): void
    {
        $blockImportKey = $templateImportKey.'.block.site_search';
        $placementImportKey = $templateImportKey.'.placement.site_search';
        $placeable = $this->placeableBlock('site_search');
        $blockPayload = [
            'type' => 'site_search',
            'name' => 'Site search',
            'content' => json_encode(['type' => 'site_search'], JSON_THROW_ON_ERROR),
            'settings' => json_encode([], JSON_THROW_ON_ERROR),
            'is_shared' => false,
            'is_dynamic' => true,
            'cache_strategy' => 'inherit',
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::connection($this->connection)->hasColumn('cms_blocks', 'cms_placeable_block_id')) {
            $blockPayload['cms_placeable_block_id'] = $placeable['id'];
        }

        if (Schema::connection($this->connection)->hasColumn('cms_blocks', 'placeable_block_revision_id')) {
            $blockPayload['placeable_block_revision_id'] = $placeable['revision_id'];
        }

        DB::connection($this->connection)->table('cms_blocks')->updateOrInsert(['import_key' => $blockImportKey], $blockPayload);

        $blockId = (int) DB::connection($this->connection)->table('cms_blocks')->where('import_key', $blockImportKey)->value('id');

        DB::connection($this->connection)->table('cms_block_placements')->updateOrInsert(
            ['import_key' => $placementImportKey],
            [
                'cms_section_id' => $sectionId,
                'cms_block_id' => $blockId,
                'sort_order' => 10,
                'is_active' => true,
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => true,
                'mobile_span' => 12,
                'tablet_span' => 12,
                'desktop_span' => 12,
                'height_mode' => 'auto',
                'height_value' => null,
                'cache_strategy' => 'inherit',
                'settings' => json_encode(['alignment' => 'left', 'content_alignment' => 'left'], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function syncPlaceableBlock(string $blockKey, array $definition): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_placeable_blocks')) {
            return;
        }

        $now = now();
        $payload = [
            'name' => __('cms_admin_ui.'.(string) ($definition['label_key'] ?? 'components.block_editor.block_fallback')),
            'description' => null,
            'category' => (string) ($definition['category'] ?? 'system'),
            'source' => 'system',
            'status' => 'published',
            'allowed_zones' => json_encode(array_values((array) ($definition['zones'] ?? ['content'])), JSON_THROW_ON_ERROR),
            'rendering_mode' => (string) ($definition['rendering_mode'] ?? 'platform_blade'),
            'renderer_key' => $blockKey,
            'template_source' => null,
            'css_source' => null,
            'schema' => json_encode(['category' => $definition['category'] ?? 'system', 'fields' => array_values((array) ($definition['fields'] ?? [])), 'editor_fields' => array_values((array) data_get($definition, 'editor.fields', [])), 'preview' => $definition['preview'] ?? []], JSON_THROW_ON_ERROR),
            'defaults' => json_encode((array) ($definition['defaults'] ?? []), JSON_THROW_ON_ERROR),
            'capabilities' => json_encode(['can_edit_template' => false, 'can_edit_css' => false, 'can_edit_fields' => false, 'can_edit_allowed_zones' => false, 'can_edit_renderer' => false, 'can_edit_defaults' => false, 'can_edit_category' => false, 'can_edit_admin_component' => false, 'can_edit_slots' => false], JSON_THROW_ON_ERROR),
            'behavior_config' => json_encode([], JSON_THROW_ON_ERROR),
            'context_config' => json_encode([], JSON_THROW_ON_ERROR),
            'admin_component_key' => null,
            'package_key' => null,
            'sort_order' => 0,
            'is_locked' => true,
            'requires_permission' => null,
            'published_at' => $now,
            'deleted_at' => null,
            'updated_at' => $now,
        ];
        $blockId = (int) DB::connection($this->connection)->table('cms_placeable_blocks')->where('key', $blockKey)->value('id');

        if ($blockId > 0) {
            DB::connection($this->connection)->table('cms_placeable_blocks')->where('id', $blockId)->update($payload);
        } else {
            $blockId = (int) DB::connection($this->connection)->table('cms_placeable_blocks')->insertGetId(array_merge($payload, ['key' => $blockKey, 'created_at' => $now]));
        }

        $this->publishRevision($blockId, $blockKey, $payload, $now);
    }

    /**
     * @return array{id: int|null, revision_id: int|null}
     */
    private function placeableBlock(string $blockKey): array
    {
        $id = Schema::connection($this->connection)->hasTable('cms_placeable_blocks')
            ? (int) DB::connection($this->connection)->table('cms_placeable_blocks')->where('key', $blockKey)->value('id')
            : null;
        $revisionId = $id && Schema::connection($this->connection)->hasTable('cms_placeable_block_revisions')
            ? (int) DB::connection($this->connection)->table('cms_placeable_block_revisions')->where('cms_placeable_block_id', $id)->where('status', 'published')->orderByDesc('revision_number')->value('id')
            : null;

        return ['id' => $id ?: null, 'revision_id' => $revisionId ?: null];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publishRevision(int $blockId, string $blockKey, array $payload, mixed $now): void
    {
        if ($blockId <= 0 || ! Schema::connection($this->connection)->hasTable('cms_placeable_block_revisions')) {
            return;
        }

        $snapshotHash = hash('sha256', json_encode([$blockKey, $payload], JSON_THROW_ON_ERROR));

        if (DB::connection($this->connection)->table('cms_placeable_block_revisions')->where('cms_placeable_block_id', $blockId)->where('snapshot_hash', $snapshotHash)->exists()) {
            return;
        }

        $revisionNumber = ((int) DB::connection($this->connection)->table('cms_placeable_block_revisions')->where('cms_placeable_block_id', $blockId)->max('revision_number')) + 1;

        DB::connection($this->connection)->table('cms_placeable_block_revisions')->insert([
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
            'metadata' => json_encode(['source' => 'cms_search_template_sync'], JSON_THROW_ON_ERROR),
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
