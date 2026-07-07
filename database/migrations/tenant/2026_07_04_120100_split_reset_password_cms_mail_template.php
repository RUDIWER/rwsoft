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

        $this->upsertResetPasswordTemplate($now);

        $templateId = DB::connection($this->connection)
            ->table('cms_mail_templates')
            ->where('key', 'reset_password')
            ->value('id');

        if (! $templateId) {
            return;
        }

        DB::connection($this->connection)
            ->table('cms_emails')
            ->where('system_key', 'site_user.reset_password')
            ->update([
                'cms_mail_template_id' => $templateId,
                'updated_at' => $now,
            ]);

        foreach ($this->templates() as $templateKey => $definition) {
            $templateId = DB::connection($this->connection)
                ->table('cms_mail_templates')
                ->where('key', $templateKey)
                ->value('id');

            if (! $templateId || $this->templateHasActiveBuilderSections((int) $templateId)) {
                continue;
            }

            $this->createBuilderSection((int) $templateId, $templateKey, $definition, $now);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data migration: do not move edited reset-password emails back automatically.
    }

    private function hasRequiredTables(): bool
    {
        foreach (['cms_mail_templates', 'cms_emails', 'cms_placeable_blocks', 'cms_placeable_block_revisions', 'cms_sections', 'cms_blocks', 'cms_block_placements'] as $table) {
            if (! Schema::connection($this->connection)->hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function upsertResetPasswordTemplate(mixed $now): void
    {
        DB::connection($this->connection)->table('cms_mail_templates')->updateOrInsert(
            ['key' => 'reset_password'],
            [
                'name' => 'Reset password email',
                'description' => 'System email layout for password reset links.',
                'context_key' => 'public_site.auth_email',
                'body_blocks' => json_encode([
                    ['key' => 'heading', 'type' => 'heading', 'label' => 'Heading', 'required' => true],
                    ['key' => 'intro', 'type' => 'text', 'label' => 'Intro text', 'required' => true],
                    ['key' => 'action', 'type' => 'button', 'label' => 'Reset password button', 'required' => true, 'url_source' => 'action.url'],
                    ['key' => 'outro', 'type' => 'text', 'label' => 'Outro text', 'required' => false],
                ], JSON_THROW_ON_ERROR),
                'settings' => json_encode(['system_seeded' => true], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    private function templateHasActiveBuilderSections(int $templateId): bool
    {
        return DB::connection($this->connection)
            ->table('cms_sections')
            ->where('owner_type', 'App\\Models\\Cms\\CmsMailTemplate')
            ->where('owner_id', $templateId)
            ->where('zone', 'content')
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @param  array{name: string, placements: array<int, array<string, mixed>>}  $definition
     */
    private function createBuilderSection(int $templateId, string $templateKey, array $definition, mixed $now): void
    {
        $sectionImportKey = "mail-template.{$templateKey}.content";

        DB::connection($this->connection)->table('cms_sections')->updateOrInsert(
            ['import_key' => $sectionImportKey],
            [
                'owner_type' => 'App\\Models\\Cms\\CmsMailTemplate',
                'owner_id' => $templateId,
                'zone' => 'content',
                'name' => $definition['name'],
                'sort_order' => 0,
                'is_active' => true,
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => true,
                'settings' => json_encode([
                    'source' => 'reset_password_mail_template_split',
                    'layout_type' => 'standard',
                    'width_mode' => 'content',
                    'spacing' => 'normal',
                    'scroll_behavior' => 'normal',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $sectionId = (int) DB::connection($this->connection)
            ->table('cms_sections')
            ->where('import_key', $sectionImportKey)
            ->value('id');

        foreach ($definition['placements'] as $index => $placement) {
            $this->createPlacement($sectionId, $templateKey, $placement, $index, $now);
        }
    }

    /**
     * @param  array<string, mixed>  $placement
     */
    private function createPlacement(int $sectionId, string $templateKey, array $placement, int $index, mixed $now): void
    {
        $placeable = $this->mailPlaceableBlock((string) $placement['block_key']);

        if ($placeable === null) {
            return;
        }

        $blockImportKey = "mail-template.{$templateKey}.block.{$placement['content_key']}";
        $placementImportKey = "mail-template.{$templateKey}.placement.{$placement['content_key']}";

        DB::connection($this->connection)->table('cms_blocks')->updateOrInsert(
            ['import_key' => $blockImportKey],
            $this->blockPayload($placeable, $placement, $now),
        );

        $blockId = (int) DB::connection($this->connection)
            ->table('cms_blocks')
            ->where('import_key', $blockImportKey)
            ->value('id');

        DB::connection($this->connection)->table('cms_block_placements')->updateOrInsert(
            ['import_key' => $placementImportKey],
            $this->placementPayload($sectionId, $blockId, $placement, $index, $now),
        );
    }

    /**
     * @return array{id: int, revision_id: int|null, renderer_key: string}|null
     */
    private function mailPlaceableBlock(string $key): ?array
    {
        $block = DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', $key)
            ->where('category', 'mail')
            ->where('status', 'published')
            ->first();

        if (! $block) {
            return null;
        }

        $revisionId = DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $block->id)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->orderByDesc('revision_number')
            ->value('id');

        return [
            'id' => (int) $block->id,
            'revision_id' => $revisionId ? (int) $revisionId : null,
            'renderer_key' => (string) $block->renderer_key,
        ];
    }

    /**
     * @param  array{id: int, revision_id: int|null, renderer_key: string}  $placeable
     * @param  array<string, mixed>  $placement
     * @return array<string, mixed>
     */
    private function blockPayload(array $placeable, array $placement, mixed $now): array
    {
        $payload = [
            'cms_placeable_block_id' => $placeable['id'],
            'placeable_block_revision_id' => $placeable['revision_id'],
            'type' => $placeable['renderer_key'],
            'name' => $placement['label'],
            'content' => json_encode($placement['content'] ?? [], JSON_THROW_ON_ERROR),
            'settings' => json_encode(['source' => 'reset_password_mail_template_split'], JSON_THROW_ON_ERROR),
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
     * @param  array<string, mixed>  $placement
     * @return array<string, mixed>
     */
    private function placementPayload(int $sectionId, int $blockId, array $placement, int $index, mixed $now): array
    {
        $payload = [
            'cms_section_id' => $sectionId,
            'cms_block_id' => $blockId,
            'sort_order' => $index,
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
                'source' => 'reset_password_mail_template_split',
                'content_key' => $placement['content_key'],
                'editor_label' => $placement['label'],
                'page_editable' => true,
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
                'desktop' => ['x' => 0, 'y' => $index * 2, 'w' => 12, 'h' => 2],
                'tablet' => ['x' => 0, 'y' => $index * 2, 'w' => 12, 'h' => 2],
                'mobile' => ['x' => 0, 'y' => $index * 2, 'w' => 12, 'h' => 2],
            ], JSON_THROW_ON_ERROR);
        }

        if (Schema::connection($this->connection)->hasColumn('cms_block_placements', 'style_config')) {
            $payload['style_config'] = json_encode([], JSON_THROW_ON_ERROR);
        }

        return $payload;
    }

    /**
     * @return array<string, array{name: string, placements: array<int, array<string, mixed>>}>
     */
    private function templates(): array
    {
        return [
            'auth_action' => [
                'name' => 'Authentication email',
                'placements' => [
                    $this->placement('mail_company_logo', 'company_logo', 'Company logo'),
                    $this->placement('mail_heading', 'heading', 'Heading'),
                    $this->placement('mail_text', 'intro', 'Intro text'),
                    $this->placement('mail_button', 'action', 'Action button', ['url' => '{{ action.url }}']),
                    $this->placement('mail_footer_text', 'outro', 'Outro text'),
                ],
            ],
            'reset_password' => [
                'name' => 'Reset password email',
                'placements' => [
                    $this->placement('mail_company_logo', 'company_logo', 'Company logo'),
                    $this->placement('mail_heading', 'heading', 'Heading'),
                    $this->placement('mail_text', 'intro', 'Intro text'),
                    $this->placement('mail_button', 'action', 'Reset password button', ['url' => '{{ action.url }}']),
                    $this->placement('mail_footer_text', 'outro', 'Outro text'),
                ],
            ],
            'form_notification' => [
                'name' => 'Form notification email',
                'placements' => [
                    $this->placement('mail_company_logo', 'company_logo', 'Company logo'),
                    $this->placement('mail_heading', 'heading', 'Heading'),
                    $this->placement('mail_text', 'intro', 'Intro text'),
                    $this->placement('mail_form_answers', 'answers', 'Form answers'),
                    $this->placement('mail_footer_text', 'outro', 'Outro text'),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function placement(string $blockKey, string $contentKey, string $label, array $content = []): array
    {
        return [
            'block_key' => $blockKey,
            'content_key' => $contentKey,
            'label' => $label,
            'content' => $content,
        ];
    }
};
