<?php

use App\Models\Cms\CmsTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cms_templates') || ! Schema::hasTable('cms_sections') || ! Schema::hasTable('cms_blocks') || ! Schema::hasTable('cms_block_placements')) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('cms_templates')
                ->where('template_class', 'error')
                ->whereIn('template_key', $this->templateKeys())
                ->orderBy('id')
                ->get(['id', 'import_key', 'template_key'])
                ->each(function (object $template): void {
                    $this->seedTemplateContent($template);
                });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left empty: seeded error template content may be customized.
    }

    private function seedTemplateContent(object $template): void
    {
        if ($this->templateHasContent((int) $template->id)) {
            return;
        }

        $now = now();
        $templateImportKey = $this->templateImportKey($template);
        $sectionId = $this->seedSection((int) $template->id, $templateImportKey, $now);

        foreach ($this->blocks() as $index => $block) {
            $this->seedPlacement($sectionId, $templateImportKey, $block, $index, $now);
        }
    }

    private function templateHasContent(int $templateId): bool
    {
        $sectionIds = DB::table('cms_sections')
            ->where('owner_type', CmsTemplate::class)
            ->where('owner_id', $templateId)
            ->where('zone', 'content')
            ->pluck('id');

        if ($sectionIds->isEmpty()) {
            return false;
        }

        return DB::table('cms_block_placements')
            ->whereIn('cms_section_id', $sectionIds)
            ->exists();
    }

    private function seedSection(int $templateId, string $templateImportKey, mixed $now): int
    {
        $importKey = $templateImportKey.'.content';

        DB::table('cms_sections')->updateOrInsert(
            ['import_key' => $importKey],
            [
                'owner_type' => CmsTemplate::class,
                'owner_id' => $templateId,
                'zone' => 'content',
                'name' => 'Error content',
                'sort_order' => 10,
                'is_active' => true,
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => true,
                'settings' => $this->json([
                    'layout_type' => 'standard',
                    'width_mode' => 'content',
                    'spacing' => 'normal',
                    'background' => 'none',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::table('cms_sections')->where('import_key', $importKey)->value('id');
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function seedPlacement(int $sectionId, string $templateImportKey, array $block, int $index, mixed $now): void
    {
        $blockImportKey = $templateImportKey.'.block.'.($index + 1);
        $placementImportKey = $templateImportKey.'.placement.'.($index + 1);

        DB::table('cms_blocks')->updateOrInsert(
            ['import_key' => $blockImportKey],
            [
                'type' => $block['type'],
                'name' => $block['name'],
                'content' => $this->json($block['content']),
                'settings' => $this->json([]),
                'is_shared' => false,
                'is_dynamic' => true,
                'cache_strategy' => 'inherit',
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $blockId = (int) DB::table('cms_blocks')->where('import_key', $blockImportKey)->value('id');

        DB::table('cms_block_placements')->updateOrInsert(
            ['import_key' => $placementImportKey],
            [
                'cms_section_id' => $sectionId,
                'cms_block_id' => $blockId,
                'sort_order' => ($index + 1) * 10,
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
                'settings' => $this->json([
                    'alignment' => 'left',
                    'content_alignment' => 'left',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * @return array<int, string>
     */
    private function templateKeys(): array
    {
        return [
            'error.default',
            'error.403',
            'error.404',
            'error.419',
            'error.500',
            'error.503',
        ];
    }

    /**
     * @return array<int, array{type: string, name: string, content: array<string, mixed>}>
     */
    private function blocks(): array
    {
        return [
            [
                'type' => 'dynamic_field',
                'name' => 'Error title',
                'content' => [
                    'type' => 'dynamic_field',
                    'field_key' => 'error.title',
                    'title' => null,
                    'heading_level' => 'h1',
                ],
            ],
            [
                'type' => 'dynamic_field',
                'name' => 'Error message',
                'content' => [
                    'type' => 'dynamic_field',
                    'field_key' => 'error.message',
                    'title' => null,
                    'heading_level' => 'none',
                ],
            ],
        ];
    }

    private function templateImportKey(object $template): string
    {
        $importKey = (string) ($template->import_key ?? '');

        if ($importKey !== '') {
            return $importKey;
        }

        return 'cms.error_templates.'.(string) $template->template_key.'.'.(int) $template->id;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
};
