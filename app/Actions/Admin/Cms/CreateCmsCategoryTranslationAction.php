<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPage;
use App\Support\Ai\CmsPageTranslationAiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCmsCategoryTranslationAction
{
    public function __construct(
        private readonly SyncCmsCategoryLandingPageAction $syncLandingPage,
        private readonly CmsPageTranslationAiService $translationAiService,
    ) {}

    public function handle(CmsCategory $sourceCategory, string $targetLocale, ?int $authorId, bool $useAi = false): CmsCategory
    {
        $translatedData = $useAi && $sourceCategory->landingPage instanceof CmsPage
            ? $this->translationAiService->translate($sourceCategory->landingPage, $targetLocale)
            : [];

        return DB::transaction(function () use ($sourceCategory, $targetLocale, $authorId, $translatedData, $useAi): CmsCategory {
            $translationKey = $this->ensureTranslationKey($sourceCategory);
            $sourcePage = $sourceCategory->landingPage;
            $title = (string) ($translatedData['title'] ?? $sourceCategory->title);

            $category = new CmsCategory;
            $category->fill([
                'parent_id' => $this->translatedParentId($sourceCategory, $targetLocale),
                'type' => $sourceCategory->type,
                'title' => $title,
                'slug' => $this->uniqueSlug($useAi ? $title : (string) $sourceCategory->slug, $targetLocale, (string) $sourceCategory->type),
                'locale' => $targetLocale,
                'translation_key' => $translationKey,
                'translated_from_category_id' => $sourceCategory->id,
                'description' => array_key_exists('excerpt', $translatedData) ? $translatedData['excerpt'] : $sourceCategory->description,
                'sort_order' => (int) $sourceCategory->sort_order,
                'is_active' => false,
                'settings' => $this->settings($sourceCategory, $useAi),
            ]);
            $category->save();

            $this->syncLandingPage->handle($category, [
                'status' => 'draft',
                'template' => $sourcePage?->template,
                'excerpt' => array_key_exists('excerpt', $translatedData) ? $translatedData['excerpt'] : ($sourcePage?->excerpt ?? $sourceCategory->description),
                'content_blocks' => $translatedData['content_blocks'] ?? $sourcePage?->content_blocks ?? [],
                'seo_title' => array_key_exists('seo_title', $translatedData) ? $translatedData['seo_title'] : $sourcePage?->seo_title,
                'seo_description' => array_key_exists('seo_description', $translatedData) ? $translatedData['seo_description'] : $sourcePage?->seo_description,
                'canonical_url' => null,
                'og_image_path' => $sourcePage?->og_image_path,
                'noindex' => true,
                'is_searchable' => (bool) ($sourcePage?->is_searchable ?? true),
                'sort_order' => (int) $sourceCategory->sort_order,
                'published_at' => null,
                'structured_data_schema_type' => $sourcePage?->settings['structured_data_schema_type'] ?? 'auto',
                'structured_data_extra' => $sourcePage?->settings['structured_data_extra'] ?? null,
            ], $authorId);

            return $category;
        });
    }

    private function ensureTranslationKey(CmsCategory $category): string
    {
        if (filled($category->translation_key)) {
            return (string) $category->translation_key;
        }

        $translationKey = (string) Str::ulid();

        $category->forceFill(['translation_key' => $translationKey])->save();

        return $translationKey;
    }

    private function translatedParentId(CmsCategory $sourceCategory, string $targetLocale): ?int
    {
        if (! $sourceCategory->parent_id) {
            return null;
        }

        $parent = CmsCategory::query()->find($sourceCategory->parent_id, ['id', 'translation_key']);

        if (! $parent instanceof CmsCategory || blank($parent->translation_key)) {
            return null;
        }

        $translatedParentId = CmsCategory::query()
            ->where('translation_key', $parent->translation_key)
            ->where('locale', $targetLocale)
            ->value('id');

        return $translatedParentId ? (int) $translatedParentId : null;
    }

    private function uniqueSlug(string $sourceSlug, string $targetLocale, string $type): string
    {
        $baseSlug = Str::slug($sourceSlug) ?: 'categorie';
        $slug = $baseSlug;
        $counter = 2;

        while (CmsCategory::query()->where('type', $type)->where('locale', $targetLocale)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(CmsCategory $sourceCategory, bool $useAi): array
    {
        $settings = $sourceCategory->settings ?? [];

        if (! $useAi) {
            unset($settings['translation_source'], $settings['translation_review_status']);

            return $settings;
        }

        return array_merge($settings, [
            'translation_source' => 'ai',
            'translation_review_status' => 'pending',
        ]);
    }
}
