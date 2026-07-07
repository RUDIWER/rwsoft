<?php

use App\Models\Cms\CmsTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_templates') || ! Schema::hasTable('cms_sections') || ! Schema::hasTable('cms_blocks') || ! Schema::hasTable('cms_block_placements')) {
            return;
        }

        DB::transaction(function (): void {
            foreach ($this->locales() as $locale) {
                foreach ($this->templates() as $template) {
                    $this->seedTemplate($locale, $template);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cms_templates')) {
            return;
        }

        $templateImportKeys = collect($this->locales())
            ->flatMap(fn (string $locale): array => collect($this->templates())
                ->map(fn (array $template): string => $this->importKey($locale, $template))
                ->all())
            ->all();

        $templateIds = DB::table('cms_templates')
            ->whereIn('import_key', $templateImportKeys)
            ->pluck('id');

        if ($templateIds->isEmpty()) {
            return;
        }

        $sectionIds = DB::table('cms_sections')
            ->where('owner_type', CmsTemplate::class)
            ->whereIn('owner_id', $templateIds)
            ->pluck('id');

        $blockIds = DB::table('cms_block_placements')
            ->whereIn('cms_section_id', $sectionIds)
            ->pluck('cms_block_id');

        DB::table('cms_block_placements')->whereIn('cms_section_id', $sectionIds)->delete();
        DB::table('cms_sections')->whereIn('id', $sectionIds)->delete();
        DB::table('cms_blocks')->whereIn('id', $blockIds)->delete();
        DB::table('cms_templates')->whereIn('id', $templateIds)->delete();
    }

    /**
     * @return array<int, string>
     */
    private function locales(): array
    {
        if (Schema::hasTable('cms_languages')) {
            $locales = DB::table('cms_languages')
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

        return [(string) config('app.locale', 'nl')];
    }

    /**
     * @return array<int, array{template_class: string, template_key: string, name: string, blocks: array<int, array<string, mixed>>}>
     */
    private function templates(): array
    {
        return [
            [
                'template_class' => 'page',
                'template_key' => 'page.detail',
                'name' => 'Default page detail',
                'blocks' => [
                    $this->dynamicField('page.title', 'h1'),
                    $this->dynamicField('page.short_description'),
                    $this->contentSlot('content'),
                ],
            ],
            [
                'template_class' => 'blog',
                'template_key' => 'blog.index',
                'name' => 'Default blog index',
                'blocks' => [
                    $this->dynamicField('blog_index.title', 'h1'),
                    $this->dynamicField('blog_index.lead'),
                    $this->dynamicField('blogs'),
                ],
            ],
            [
                'template_class' => 'blog',
                'template_key' => 'blog.detail',
                'name' => 'Default blog detail',
                'blocks' => [
                    $this->dynamicField('blog.title', 'h1'),
                    $this->dynamicField('blog.published_at'),
                    $this->dynamicField('blog.featured_media'),
                    $this->dynamicField('blog.excerpt'),
                    $this->contentSlot('content'),
                    $this->dynamicField('blog.categories'),
                    $this->dynamicField('blog.tags'),
                ],
            ],
            [
                'template_class' => 'category',
                'template_key' => 'category.index',
                'name' => 'Default category index',
                'blocks' => [
                    $this->dynamicField('category_index.title', 'h1'),
                    $this->dynamicField('root_categories', 'h1'),
                ],
            ],
            [
                'template_class' => 'category',
                'template_key' => 'category.archive',
                'name' => 'Default category archive',
                'blocks' => [
                    $this->dynamicField('category.title', 'h1'),
                    $this->dynamicField('category.description'),
                    $this->contentSlot('content'),
                    $this->dynamicField('category.children'),
                    $this->dynamicField('category.blogs'),
                ],
            ],
            [
                'template_class' => 'category',
                'template_key' => 'category.detail',
                'name' => 'Default category detail',
                'blocks' => [
                    $this->dynamicField('category.title', 'h1'),
                    $this->dynamicField('category.description'),
                    $this->contentSlot('content'),
                ],
            ],
            [
                'template_class' => 'tag',
                'template_key' => 'tag.index',
                'name' => 'Default tag index',
                'blocks' => [
                    $this->dynamicField('tag_index.title', 'h1'),
                    $this->dynamicField('tags', 'h1'),
                ],
            ],
            [
                'template_class' => 'tag',
                'template_key' => 'tag.archive',
                'name' => 'Default tag archive',
                'blocks' => [
                    $this->dynamicField('tag.title', 'h1'),
                    $this->dynamicField('tag.description'),
                    $this->contentSlot('content'),
                    $this->dynamicField('tag.blogs'),
                ],
            ],
            [
                'template_class' => 'tag',
                'template_key' => 'tag.detail',
                'name' => 'Default tag detail',
                'blocks' => [
                    $this->dynamicField('tag.title', 'h1'),
                    $this->dynamicField('tag.description'),
                    $this->contentSlot('content'),
                ],
            ],
        ];
    }

    /**
     * @param  array{template_class: string, template_key: string, name: string, blocks: array<int, array<string, mixed>>}  $template
     */
    private function seedTemplate(string $locale, array $template): void
    {
        $now = now();
        $importKey = $this->importKey($locale, $template);
        $translationKey = $this->translationGroupKey($template);

        DB::table('cms_templates')->updateOrInsert(
            ['import_key' => $importKey],
            [
                'name' => $template['name'].' ('.strtoupper($locale).')',
                'locale' => $locale,
                'translation_key' => $translationKey,
                'template_class' => $template['template_class'],
                'template_key' => $template['template_key'],
                'is_default' => true,
                'is_active' => true,
                'cache_strategy' => 'inherit',
                'settings' => $this->json([]),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $templateId = (int) DB::table('cms_templates')->where('import_key', $importKey)->value('id');
        $sectionId = $this->seedSection($templateId, $importKey, $now);

        foreach ($template['blocks'] as $index => $block) {
            $this->seedPlacement($sectionId, $importKey, $block, $index, $now);
        }
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
                'name' => 'Content',
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
                'name' => Str::headline((string) ($block['field_key'] ?? $block['slot_key'] ?? $block['type'])),
                'content' => $this->json($block),
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
     * @return array<string, mixed>
     */
    private function dynamicField(string $fieldKey, string $headingLevel = 'none'): array
    {
        return [
            'type' => 'dynamic_field',
            'field_key' => $fieldKey,
            'title' => null,
            'heading_level' => $headingLevel,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contentSlot(string $slotKey): array
    {
        return [
            'type' => 'content_slot',
            'slot_key' => $slotKey,
            'title' => null,
        ];
    }

    /**
     * @param  array{template_class: string, template_key: string}  $template
     */
    private function importKey(string $locale, array $template): string
    {
        return implode('.', ['cms', 'default-template', $locale, $template['template_key']]);
    }

    /**
     * @param  array{template_class: string, template_key: string}  $template
     */
    private function translationGroupKey(array $template): string
    {
        return implode('.', ['cms', 'default-template', $template['template_key']]);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
};
