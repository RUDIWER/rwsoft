<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsTemplate;
use App\Support\Ai\CmsTemplateTranslationAiService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CreateCmsTemplateTranslationAction
{
    public function __construct(
        private readonly CmsTemplateTranslationAiService $translationAiService,
        private readonly GenerateCmsHtmlAnchorAction $htmlAnchorAction,
    ) {}

    public function handle(CmsTemplate $sourceTemplate, string $targetLocale, bool $useAi = false): CmsTemplate
    {
        $sourceTemplate->loadMissing(['layout', 'sections.placements.block']);

        $translatedData = $useAi
            ? $this->translationAiService->translate($sourceTemplate, $targetLocale)
            : [];

        return DB::transaction(function () use ($sourceTemplate, $targetLocale, $translatedData, $useAi): CmsTemplate {
            $translationKey = $this->ensureTranslationKey($sourceTemplate);
            $layoutId = $this->resolveLayoutId($sourceTemplate, $targetLocale);

            if ($layoutId === null) {
                throw new RuntimeException(__('cms_admin_ui.validation.template_translation_missing_layout'));
            }

            $template = new CmsTemplate;
            $template->fill([
                'name' => $this->limit((string) Arr::get($translatedData, 'name', $sourceTemplate->name), 255),
                'locale' => $targetLocale,
                'translation_key' => $translationKey,
                'translated_from_template_id' => $sourceTemplate->id,
                'layout_id' => $layoutId,
                'template_class' => $sourceTemplate->template_class,
                'template_key' => $sourceTemplate->template_key,
                'is_default' => false,
                'is_active' => false,
                'cache_strategy' => $sourceTemplate->cache_strategy,
                'settings' => $this->settings($sourceTemplate, $useAi),
            ])->save();

            $translatedSections = collect(Arr::get($translatedData, 'sections', []))
                ->filter(fn (mixed $section): bool => is_array($section))
                ->keyBy(fn (array $section): int => (int) Arr::get($section, 'index', -1));

            $sourceTemplate->sections
                ->where('zone', 'content')
                ->where('is_active', true)
                ->sortBy('sort_order')
                ->values()
                ->each(function (CmsSection $sourceSection, int $sectionIndex) use ($template, $targetLocale, $translatedSections): void {
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
                    $template->sections()->save($section);

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

            return $template;
        });
    }

    private function ensureTranslationKey(CmsTemplate $template): string
    {
        if (filled($template->translation_key)) {
            return (string) $template->translation_key;
        }

        $translationKey = (string) Str::ulid();

        $template->forceFill(['translation_key' => $translationKey])->save();

        return $translationKey;
    }

    private function resolveLayoutId(CmsTemplate $sourceTemplate, string $targetLocale): ?int
    {
        $layoutTranslationKey = $sourceTemplate->layout?->translation_key;

        if (filled($layoutTranslationKey)) {
            $translatedLayoutId = CmsLayout::query()
                ->where('translation_key', $layoutTranslationKey)
                ->where('locale', $targetLocale)
                ->where('is_active', true)
                ->value('id');

            if ($translatedLayoutId) {
                return (int) $translatedLayoutId;
            }
        }

        $defaultLayoutId = CmsLayout::query()
            ->where('locale', $targetLocale)
            ->where('is_active', true)
            ->where('is_default', true)
            ->value('id');

        if ($defaultLayoutId) {
            return (int) $defaultLayoutId;
        }

        $fallbackLayoutId = CmsLayout::query()
            ->where('locale', $targetLocale)
            ->where('is_active', true)
            ->orderBy('name')
            ->value('id');

        return $fallbackLayoutId ? (int) $fallbackLayoutId : null;
    }

    /**
     * @param  array<string, mixed>  $translatedPlacement
     */
    private function copyBlock(CmsBlock $sourceBlock, array $translatedPlacement): CmsBlock
    {
        $content = $sourceBlock->content ?? [];

        foreach (['title', 'text', 'source', 'caption', 'label', 'empty_text'] as $field) {
            $translatedValue = trim((string) Arr::get($translatedPlacement, $field, ''));

            if ($translatedValue !== '') {
                $content[$field] = $this->limit($translatedValue, $this->blockLimit($field));
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
    private function settings(CmsTemplate $sourceTemplate, bool $useAi): array
    {
        $settings = $sourceTemplate->settings ?? [];
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
