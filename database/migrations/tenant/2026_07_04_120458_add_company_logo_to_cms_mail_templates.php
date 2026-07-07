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
        if (! $this->hasRequiredTables()) {
            return;
        }

        $now = now();

        $this->upsertCompanyLogoBlock($now);

        foreach (['auth_action', 'reset_password', 'form_notification'] as $templateKey) {
            $this->addCompanyLogoToTemplate($templateKey, $now);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Seed/data migration: existing templates may depend on this block.
    }

    private function hasRequiredTables(): bool
    {
        foreach (['cms_placeable_blocks', 'cms_placeable_block_revisions', 'cms_mail_templates', 'cms_sections', 'cms_blocks', 'cms_block_placements'] as $table) {
            if (! Schema::connection($this->connection)->hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function upsertCompanyLogoBlock(mixed $now): void
    {
        $block = [
            'key' => 'mail_company_logo',
            'name' => 'Mail company logo',
            'description' => 'Company logo configured in CMS settings.',
            'schema' => ['fields' => [], 'editor_fields' => []],
            'defaults' => [],
        ];
        $payload = [
            'name' => $block['name'],
            'description' => $block['description'],
            'category' => 'mail',
            'source' => 'system',
            'status' => 'published',
            'allowed_zones' => json_encode(['content'], JSON_THROW_ON_ERROR),
            'rendering_mode' => 'safe_blade',
            'renderer_key' => $block['key'],
            'template_source' => null,
            'css_source' => null,
            'schema' => json_encode($block['schema'], JSON_THROW_ON_ERROR),
            'defaults' => json_encode($block['defaults'], JSON_THROW_ON_ERROR),
            'capabilities' => json_encode([
                'can_edit_template' => false,
                'can_edit_css' => false,
                'can_edit_fields' => false,
                'can_edit_allowed_zones' => false,
                'can_edit_renderer' => false,
                'can_edit_defaults' => false,
                'can_edit_category' => false,
                'can_edit_admin_component' => false,
                'can_edit_slots' => false,
            ], JSON_THROW_ON_ERROR),
            'behavior_config' => json_encode([], JSON_THROW_ON_ERROR),
            'context_config' => json_encode([], JSON_THROW_ON_ERROR),
            'admin_component_key' => null,
            'package_key' => null,
            'sort_order' => 999,
            'is_locked' => true,
            'requires_permission' => null,
            'published_at' => $now,
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        $blockId = (int) DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', $block['key'])
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
                    'key' => $block['key'],
                    'created_at' => $now,
                ]));
        }

        $this->ensurePublishedRevision($blockId, $block, $payload, $now);
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $payload
     */
    private function ensurePublishedRevision(int $blockId, array $block, array $payload, mixed $now): void
    {
        if (DB::connection($this->connection)->table('cms_placeable_block_revisions')->where('cms_placeable_block_id', $blockId)->where('status', 'published')->exists()) {
            return;
        }

        DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->insert([
                'cms_placeable_block_id' => $blockId,
                'revision_number' => 1,
                'status' => 'published',
                'title' => $block['name'],
                'category' => $payload['category'],
                'source' => $payload['source'],
                'allowed_zones' => $payload['allowed_zones'],
                'rendering_mode' => $payload['rendering_mode'],
                'renderer_key' => $payload['renderer_key'],
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
                'snapshot_hash' => hash('sha256', json_encode([
                    'key' => $block['key'],
                    'schema' => $payload['schema'],
                    'defaults' => $payload['defaults'],
                ], JSON_THROW_ON_ERROR)),
                'author_id' => null,
                'metadata' => json_encode(['source' => 'company_logo_mail_seed'], JSON_THROW_ON_ERROR),
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
    }

    private function addCompanyLogoToTemplate(string $templateKey, mixed $now): void
    {
        $template = DB::connection($this->connection)
            ->table('cms_mail_templates')
            ->where('key', $templateKey)
            ->first();

        if (! $template) {
            return;
        }

        $this->addBodyBlock((int) $template->id, $template->body_blocks ?? null, $now);

        $sectionId = DB::connection($this->connection)
            ->table('cms_sections')
            ->where('owner_type', 'App\\Models\\Cms\\CmsMailTemplate')
            ->where('owner_id', $template->id)
            ->where('zone', 'content')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->value('id');

        if (! $sectionId) {
            return;
        }

        $this->addLogoPlacement((int) $sectionId, $templateKey, $now);
    }

    private function addBodyBlock(int $templateId, mixed $bodyBlocks, mixed $now): void
    {
        $blocks = is_string($bodyBlocks) ? json_decode($bodyBlocks, true) : $bodyBlocks;
        $blocks = is_array($blocks) ? array_values($blocks) : [];

        if (collect($blocks)->contains(fn (mixed $block): bool => is_array($block) && ($block['key'] ?? null) === 'company_logo')) {
            return;
        }

        array_unshift($blocks, ['key' => 'company_logo', 'type' => 'company_logo', 'label' => 'Company logo', 'required' => false]);

        DB::connection($this->connection)
            ->table('cms_mail_templates')
            ->where('id', $templateId)
            ->update([
                'body_blocks' => json_encode($blocks, JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ]);
    }

    private function addLogoPlacement(int $sectionId, string $templateKey, mixed $now): void
    {
        $placementImportKey = "mail-template.{$templateKey}.placement.company_logo";

        if (DB::connection($this->connection)->table('cms_block_placements')->where('import_key', $placementImportKey)->exists()) {
            return;
        }

        $placeable = DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', 'mail_company_logo')
            ->where('category', 'mail')
            ->first();

        if (! $placeable) {
            return;
        }

        DB::connection($this->connection)
            ->table('cms_block_placements')
            ->where('cms_section_id', $sectionId)
            ->increment('sort_order', 10);

        $revisionId = DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $placeable->id)
            ->where('status', 'published')
            ->orderByDesc('revision_number')
            ->value('id');
        $blockImportKey = "mail-template.{$templateKey}.block.company_logo";

        DB::connection($this->connection)->table('cms_blocks')->updateOrInsert(
            ['import_key' => $blockImportKey],
            $this->blockPayload((int) $placeable->id, $revisionId ? (int) $revisionId : null, $now),
        );

        $blockId = (int) DB::connection($this->connection)
            ->table('cms_blocks')
            ->where('import_key', $blockImportKey)
            ->value('id');

        DB::connection($this->connection)->table('cms_block_placements')->updateOrInsert(
            ['import_key' => $placementImportKey],
            $this->placementPayload($sectionId, $blockId, $now),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function blockPayload(int $placeableBlockId, ?int $revisionId, mixed $now): array
    {
        $payload = [
            'cms_placeable_block_id' => $placeableBlockId,
            'placeable_block_revision_id' => $revisionId,
            'type' => 'mail_company_logo',
            'name' => 'Company logo',
            'content' => json_encode([], JSON_THROW_ON_ERROR),
            'settings' => json_encode(['source' => 'company_logo_mail_template_backfill'], JSON_THROW_ON_ERROR),
            'is_shared' => false,
            'is_dynamic' => false,
            'cache_strategy' => 'inherit',
            'updated_at' => $now,
            'created_at' => $now,
        ];

        if (Schema::connection($this->connection)->hasColumn('cms_blocks', 'updated_by')) {
            $payload['updated_by'] = null;
        }

        if (Schema::connection($this->connection)->hasColumn('cms_blocks', 'created_by')) {
            $payload['created_by'] = null;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function placementPayload(int $sectionId, int $blockId, mixed $now): array
    {
        $payload = [
            'cms_section_id' => $sectionId,
            'cms_block_id' => $blockId,
            'sort_order' => 0,
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
            'settings' => json_encode([
                'source' => 'company_logo_mail_template_backfill',
                'content_key' => 'company_logo',
                'editor_label' => 'Company logo',
                'page_editable' => false,
            ], JSON_THROW_ON_ERROR),
            'updated_at' => $now,
            'created_at' => $now,
        ];

        if (Schema::connection($this->connection)->hasColumn('cms_block_placements', 'parent_placement_id')) {
            $payload['parent_placement_id'] = null;
        }

        if (Schema::connection($this->connection)->hasColumn('cms_block_placements', 'slot_key')) {
            $payload['slot_key'] = null;
        }

        if (Schema::connection($this->connection)->hasColumn('cms_block_placements', 'layout_config')) {
            $payload['layout_config'] = json_encode([
                'desktop' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 1],
                'tablet' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 1],
                'mobile' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 1],
            ], JSON_THROW_ON_ERROR);
        }

        if (Schema::connection($this->connection)->hasColumn('cms_block_placements', 'style_config')) {
            $payload['style_config'] = json_encode([], JSON_THROW_ON_ERROR);
        }

        return $payload;
    }
};
