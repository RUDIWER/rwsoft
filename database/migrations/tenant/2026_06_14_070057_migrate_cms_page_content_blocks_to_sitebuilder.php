<?php

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
        if (! $this->hasRequiredTables()) {
            return;
        }

        DB::table('cms_pages')
            ->select(['id', 'content_blocks'])
            ->whereNotNull('content_blocks')
            ->orderBy('id')
            ->chunkById(100, function ($pages): void {
                foreach ($pages as $page) {
                    $contentBlocks = $this->decodeContentBlocks($page->content_blocks);

                    if ($contentBlocks === []) {
                        continue;
                    }

                    DB::transaction(function () use ($page, $contentBlocks): void {
                        $this->migratePageBlocks((int) $page->id, $contentBlocks);
                    });
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: legacy JSON remains available and no generated CMS records are deleted automatically.
    }

    private function hasRequiredTables(): bool
    {
        return Schema::hasTable('cms_pages')
            && Schema::hasTable('cms_sections')
            && Schema::hasTable('cms_blocks')
            && Schema::hasTable('cms_block_placements');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeContentBlocks(mixed $contentBlocks): array
    {
        if (is_string($contentBlocks)) {
            $decoded = json_decode($contentBlocks, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }
        } elseif (is_array($contentBlocks)) {
            $decoded = $contentBlocks;
        } else {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($block): bool => is_array($block))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $contentBlocks
     */
    private function migratePageBlocks(int $pageId, array $contentBlocks): void
    {
        $now = now();
        $sectionImportKey = $this->importKey($pageId, 'section');

        DB::table('cms_sections')->updateOrInsert(
            ['import_key' => $sectionImportKey],
            [
                'owner_type' => 'App\\Models\\Cms\\CmsPage',
                'owner_id' => $pageId,
                'zone' => 'content',
                'name' => null,
                'sort_order' => 0,
                'is_active' => true,
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => true,
                'settings' => json_encode(['source' => 'legacy_content_blocks']),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $sectionId = (int) DB::table('cms_sections')
            ->where('import_key', $sectionImportKey)
            ->value('id');

        foreach ($contentBlocks as $index => $contentBlock) {
            $blockType = $this->normalizeBlockType($contentBlock['type'] ?? null);
            $blockImportKey = $this->importKey($pageId, 'block', $index);
            $placementImportKey = $this->importKey($pageId, 'placement', $index);

            DB::table('cms_blocks')->updateOrInsert(
                ['import_key' => $blockImportKey],
                [
                    'type' => $blockType,
                    'name' => $this->nullableString($contentBlock['title'] ?? $contentBlock['heading'] ?? null),
                    'content' => json_encode($this->blockContent($contentBlock)),
                    'settings' => json_encode(['source' => 'legacy_content_blocks']),
                    'is_shared' => false,
                    'is_dynamic' => false,
                    'cache_strategy' => 'inherit',
                    'created_by' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $blockId = (int) DB::table('cms_blocks')
                ->where('import_key', $blockImportKey)
                ->value('id');

            DB::table('cms_block_placements')->updateOrInsert(
                ['import_key' => $placementImportKey],
                [
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
                    'settings' => json_encode(['source' => 'legacy_content_blocks']),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function importKey(int $pageId, string $type, ?int $index = null): string
    {
        return collect(['legacy-content-blocks', 'page', $pageId, $type, $index])
            ->filter(fn ($part): bool => $part !== null)
            ->implode('-');
    }

    private function normalizeBlockType(mixed $type): string
    {
        return in_array($type, ['breadcrumb', 'text', 'quote', 'image', 'button', 'form', 'list_rows', 'list_grid'], true)
            ? $type
            : 'text';
    }

    /**
     * @param  array<string, mixed>  $contentBlock
     * @return array<string, mixed>
     */
    private function blockContent(array $contentBlock): array
    {
        unset($contentBlock['type']);

        return $contentBlock;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
};
