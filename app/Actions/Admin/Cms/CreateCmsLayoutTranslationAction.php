<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsSection;
use App\Support\Ai\CmsLayoutTranslationAiService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCmsLayoutTranslationAction
{
    public function __construct(
        private readonly CmsLayoutTranslationAiService $translationAiService,
        private readonly GenerateCmsHtmlAnchorAction $htmlAnchorAction,
    ) {}

    public function handle(CmsLayout $sourceLayout, string $targetLocale, bool $useAi = false): CmsLayout
    {
        $sourceLayout->loadMissing('sections.placements.block');

        $translatedData = $useAi
            ? $this->translationAiService->translate($sourceLayout, $targetLocale)
            : [];

        return DB::transaction(function () use ($sourceLayout, $targetLocale, $translatedData, $useAi): CmsLayout {
            $translationKey = $this->ensureTranslationKey($sourceLayout);
            $layoutName = $this->limit((string) Arr::get($translatedData, 'name', $sourceLayout->name), 255);

            $layout = new CmsLayout;
            $layout->fill([
                'name' => $layoutName,
                'locale' => $targetLocale,
                'translation_key' => $translationKey,
                'translated_from_layout_id' => $sourceLayout->id,
                'is_default' => false,
                'is_active' => false,
                'cache_strategy' => $sourceLayout->cache_strategy,
                'settings' => $this->htmlAnchorAction->handle(
                    $layout,
                    $this->settings($sourceLayout, $useAi),
                    [$layoutName, $targetLocale, 'layout'],
                ),
            ])->save();

            $translatedSections = collect(Arr::get($translatedData, 'sections', []))
                ->filter(fn (mixed $section): bool => is_array($section))
                ->keyBy(fn (array $section): int => (int) Arr::get($section, 'index', -1));

            $sourceLayout->sections
                ->where('is_active', true)
                ->sortBy('sort_order')
                ->values()
                ->each(function (CmsSection $sourceSection, int $sectionIndex) use ($layout, $targetLocale, $translatedSections): void {
                    $translatedSection = $translatedSections->get($sectionIndex, []);
                    $sectionName = $this->nullableLimit(Arr::get($translatedSection, 'name', $sourceSection->name), 255);
                    $section = new CmsSection([
                        'import_key' => null,
                        'zone' => $sourceSection->zone,
                        'name' => $sectionName,
                        'sort_order' => $sourceSection->sort_order,
                        'is_active' => $sourceSection->is_active,
                        'visible_mobile' => $sourceSection->visible_mobile,
                        'visible_tablet' => $sourceSection->visible_tablet,
                        'visible_desktop' => $sourceSection->visible_desktop,
                    ]);
                    $section->settings = $this->htmlAnchorAction->handle(
                        $section,
                        $this->withoutHtmlAnchor($sourceSection->settings ?? []),
                        [$sectionName, $sourceSection->zone, $targetLocale, 'section'],
                    );
                    $layout->sections()->save($section);

                    $translatedPlacements = collect(Arr::get($translatedSection, 'placements', []))
                        ->filter(fn (mixed $placement): bool => is_array($placement))
                        ->keyBy(fn (array $placement): int => (int) Arr::get($placement, 'index', -1));

                    $sourceSection->placements
                        ->where('is_active', true)
                        ->sortBy('sort_order')
                        ->values()
                        ->each(function (CmsBlockPlacement $sourcePlacement, int $placementIndex) use ($section, $targetLocale, $translatedPlacements): void {
                            $block = $this->copyBlock(
                                $sourcePlacement->block,
                                $translatedPlacements->get($placementIndex, []),
                            );

                            $placement = new CmsBlockPlacement([
                                'import_key' => null,
                                'cms_block_id' => $block->id,
                                'sort_order' => $sourcePlacement->sort_order,
                                'is_active' => $sourcePlacement->is_active,
                                'visible_mobile' => $sourcePlacement->visible_mobile,
                                'visible_tablet' => $sourcePlacement->visible_tablet,
                                'visible_desktop' => $sourcePlacement->visible_desktop,
                                'mobile_span' => $sourcePlacement->mobile_span,
                                'tablet_span' => $sourcePlacement->tablet_span,
                                'desktop_span' => $sourcePlacement->desktop_span,
                                'height_mode' => $sourcePlacement->height_mode,
                                'height_value' => $sourcePlacement->height_value,
                                'cache_strategy' => $sourcePlacement->cache_strategy,
                            ]);
                            $placement->settings = $this->htmlAnchorAction->handle(
                                $placement,
                                $this->withoutHtmlAnchor($sourcePlacement->settings ?? []),
                                [$block->name, $block->type, $section->zone, $targetLocale, 'block'],
                            );
                            $section->placements()->save($placement);
                        });
                });

            return $layout;
        });
    }

    private function ensureTranslationKey(CmsLayout $layout): string
    {
        if (filled($layout->translation_key)) {
            return (string) $layout->translation_key;
        }

        $translationKey = (string) Str::ulid();

        $layout->forceFill(['translation_key' => $translationKey])->save();

        return $translationKey;
    }

    /**
     * @param  array<string, mixed>  $translatedPlacement
     */
    private function copyBlock(CmsBlock $sourceBlock, array $translatedPlacement): CmsBlock
    {
        $content = $sourceBlock->content ?? [];

        if ($sourceBlock->type === 'site_logo') {
            unset($content['alt_text']);
        }

        if (! in_array($sourceBlock->type, ['site_head', 'site_header', 'site_footer', 'custom_head_code', 'custom_body_end_code'], true)) {
            foreach (['title', 'text', 'source', 'caption', 'label', 'empty_text'] as $field) {
                $translatedValue = trim((string) Arr::get($translatedPlacement, $field, ''));

                if ($translatedValue !== '') {
                    $content[$field] = $this->limit($translatedValue, $this->blockLimit($field));
                }
            }
        }

        return CmsBlock::query()->create([
            'import_key' => null,
            'type' => $sourceBlock->type,
            'name' => $sourceBlock->name,
            'content' => $content,
            'settings' => $sourceBlock->settings ?? [],
            'is_shared' => false,
            'is_dynamic' => $sourceBlock->is_dynamic,
            'cache_strategy' => $sourceBlock->cache_strategy,
            'created_by' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(CmsLayout $sourceLayout, bool $useAi): array
    {
        $settings = $sourceLayout->settings ?? [];
        $settings = $this->withoutHtmlAnchor($settings);

        if (! $useAi) {
            unset($settings['translation_source'], $settings['translation_review_status']);

            return $settings;
        }

        return array_merge($settings, [
            'translation_source' => 'ai',
            'translation_review_status' => 'pending',
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function withoutHtmlAnchor(array $settings): array
    {
        unset($settings['html_anchor']);

        return $settings;
    }

    private function nullableLimit(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $this->limit($value, $limit) : null;
    }

    private function limit(string $value, int $limit): string
    {
        return mb_substr(trim($value), 0, $limit);
    }

    private function blockLimit(string $field): int
    {
        return match ($field) {
            'title' => 255,
            'source', 'caption', 'empty_text' => 500,
            'label' => 120,
            default => 20000,
        };
    }
}
