<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        foreach ($this->blocks() as $index => $block) {
            $this->upsertBlock($block, $index);
        }

        $this->seedEmailIndexPermission();
        $this->seedEmailTestPermission();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Seed migration: mail-safe blocks may already be used by templates.
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function upsertBlock(array $block, int $index): void
    {
        $now = now();
        $payload = [
            'name' => $block['name'],
            'description' => $block['description'],
            'category' => 'mail',
            'source' => 'system',
            'status' => 'published',
            'allowed_zones' => json_encode(['content'], JSON_THROW_ON_ERROR),
            'rendering_mode' => 'safe_blade',
            'renderer_key' => $block['key'],
            'template_source' => $block['template_source'],
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
            'sort_order' => 1000 + $index,
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

        $this->publishRevision($blockId, $block, $payload, $now);
    }

    private function seedEmailIndexPermission(): void
    {
        $this->seedPermission('admin.cms.emails.index', '[CMS] E-mails overzicht', 'Overzicht', true, 'admin/cms/emails');
    }

    private function seedEmailTestPermission(): void
    {
        $this->seedPermission('admin.cms.emails.test-send', '[CMS] E-mail test versturen', 'Versturen', false, 'admin/cms/emails/{id}/test-send');
    }

    private function seedPermission(string $routeName, string $description, string $action, bool $menu, string $url): void
    {
        if (! Schema::connection($this->connection)->hasTable('acl_permissions')) {
            return;
        }

        $now = now();
        $payload = [
            'description' => $description,
            'menu' => $menu,
            'url' => $url,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::connection($this->connection)->hasColumn('acl_permissions', 'module_id')) {
            $payload['module_id'] = $this->lookupId('acl_permission_modules', 'cms', 'CMS');
            $payload['action_id'] = $this->lookupId('acl_permission_actions', Str::slug($action, '_'), $action);
            $payload['type_id'] = $this->lookupId('acl_permission_types', 'core', 'Core');
        } else {
            $payload['module'] = 'CMS';
            $payload['action'] = $action;
            $payload['type'] = 'core';
        }

        DB::connection($this->connection)->table('acl_permissions')->updateOrInsert(
            ['route_name' => $routeName],
            $payload,
        );

        if (! Schema::connection($this->connection)->hasTable('acl_roles') || ! Schema::connection($this->connection)->hasTable('acl_permission_role')) {
            return;
        }

        $adminRoleId = DB::connection($this->connection)->table('acl_roles')->where('key', 'admin')->value('id');
        $permissionId = DB::connection($this->connection)->table('acl_permissions')->where('route_name', $routeName)->value('id');

        if (! $adminRoleId || ! $permissionId) {
            return;
        }

        DB::connection($this->connection)->table('acl_permission_role')->updateOrInsert(
            ['acl_role_id' => $adminRoleId, 'acl_permission_id' => $permissionId],
            ['active' => true, 'created_at' => $now, 'updated_at' => $now],
        );
    }

    private function lookupId(string $table, string $key, string $name): ?int
    {
        if (! Schema::connection($this->connection)->hasTable($table)) {
            return null;
        }

        DB::connection($this->connection)->table($table)->updateOrInsert(
            ['key' => $key],
            [
                'name' => $name,
                'active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return DB::connection($this->connection)->table($table)->where('key', $key)->value('id');
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $payload
     */
    private function publishRevision(int $blockId, array $block, array $payload, mixed $now): void
    {
        $revisionNumber = (int) DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $blockId)
            ->max('revision_number');

        DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->insert([
                'cms_placeable_block_id' => $blockId,
                'revision_number' => $revisionNumber + 1,
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
                    'template_source' => $payload['template_source'],
                    'schema' => $payload['schema'],
                    'defaults' => $payload['defaults'],
                ], JSON_THROW_ON_ERROR)),
                'author_id' => null,
                'metadata' => json_encode(['source' => 'mail_safe_seed'], JSON_THROW_ON_ERROR),
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blocks(): array
    {
        return [
            $this->block('mail_company_logo', 'Mail company logo', 'Company logo configured in CMS settings.', [], [], []),
            $this->block('mail_heading', 'Mail heading', 'Large heading text.', ['text'], [
                ['name' => 'text', 'type' => 'textarea', 'required' => true, 'sort_order' => 10],
            ], ['text' => 'Heading']),
            $this->block('mail_text', 'Mail text', 'Paragraph text.', ['text'], [
                ['name' => 'text', 'type' => 'textarea', 'required' => true, 'sort_order' => 10],
            ], ['text' => 'Text']),
            $this->block('mail_button', 'Mail button', 'Call-to-action button.', ['label', 'url'], [
                ['name' => 'label', 'type' => 'text', 'required' => true, 'sort_order' => 10],
                ['name' => 'url', 'type' => 'text', 'required' => false, 'sort_order' => 20],
            ], ['label' => 'Open', 'url' => '']),
            $this->block('mail_image', 'Mail image', 'Public image for emails.', ['media_asset_id', 'alt', 'caption'], [
                ['name' => 'media_asset_id', 'type' => 'media_select', 'required' => false, 'sort_order' => 10],
                ['name' => 'alt', 'type' => 'text', 'required' => false, 'sort_order' => 20],
                ['name' => 'caption', 'type' => 'text', 'required' => false, 'sort_order' => 30],
            ], []),
            $this->block('mail_divider', 'Mail divider', 'Simple horizontal divider.', [], [], []),
            $this->block('mail_spacer', 'Mail spacer', 'Vertical whitespace.', ['height'], [
                ['name' => 'height', 'type' => 'number', 'required' => false, 'sort_order' => 10],
            ], ['height' => 24]),
            $this->block('mail_form_answers', 'Mail form answers', 'Generated table with submitted form answers.', [], [], []),
            $this->block('mail_footer_text', 'Mail footer text', 'Small footer paragraph.', ['text'], [
                ['name' => 'text', 'type' => 'textarea', 'required' => false, 'sort_order' => 10],
            ], ['text' => '']),
        ];
    }

    /**
     * @param  array<int, string>  $fields
     * @param  array<int, array<string, mixed>>  $editorFields
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function block(string $key, string $name, string $description, array $fields, array $editorFields, array $defaults): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'description' => $description,
            'template_source' => '<div>{{ '.$key.' }}</div>',
            'schema' => [
                'category' => 'mail',
                'fields' => $fields,
                'editor_fields' => $editorFields,
                'preview' => [],
                'slots' => [],
            ],
            'defaults' => $defaults,
        ];
    }
};
