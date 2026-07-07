<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsTag;
use App\Support\Ai\CmsPageTranslationAiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCmsTagTranslationAction
{
    public function __construct(
        private readonly SyncCmsTagLandingPageAction $syncLandingPage,
        private readonly CmsPageTranslationAiService $translationAiService,
    ) {}

    public function handle(CmsTag $sourceTag, string $targetLocale, ?int $authorId, bool $useAi = false): CmsTag
    {
        $translatedData = $useAi && $sourceTag->landingPage instanceof CmsPage
            ? $this->translationAiService->translate($sourceTag->landingPage, $targetLocale)
            : [];

        return DB::transaction(function () use ($sourceTag, $targetLocale, $authorId, $translatedData, $useAi): CmsTag {
            $translationKey = $this->ensureTranslationKey($sourceTag);
            $sourcePage = $sourceTag->landingPage;
            $title = (string) ($translatedData['title'] ?? $sourceTag->title);

            $tag = new CmsTag;
            $tag->fill([
                'title' => $title,
                'slug' => $this->uniqueSlug($useAi ? $title : (string) $sourceTag->slug, $targetLocale),
                'locale' => $targetLocale,
                'translation_key' => $translationKey,
                'translated_from_tag_id' => $sourceTag->id,
                'description' => array_key_exists('excerpt', $translatedData) ? $translatedData['excerpt'] : $sourceTag->description,
                'is_active' => false,
                'settings' => $this->settings($sourceTag, $useAi),
            ]);
            $tag->save();

            $this->syncLandingPage->handle($tag, [
                'status' => 'draft',
                'template' => $sourcePage?->template,
                'excerpt' => array_key_exists('excerpt', $translatedData) ? $translatedData['excerpt'] : ($sourcePage?->excerpt ?? $sourceTag->description),
                'content_blocks' => $translatedData['content_blocks'] ?? $sourcePage?->content_blocks ?? [],
                'seo_title' => array_key_exists('seo_title', $translatedData) ? $translatedData['seo_title'] : $sourcePage?->seo_title,
                'seo_description' => array_key_exists('seo_description', $translatedData) ? $translatedData['seo_description'] : $sourcePage?->seo_description,
                'canonical_url' => null,
                'og_image_path' => $sourcePage?->og_image_path,
                'noindex' => true,
                'is_searchable' => (bool) ($sourcePage?->is_searchable ?? true),
                'published_at' => null,
                'structured_data_schema_type' => $sourcePage?->settings['structured_data_schema_type'] ?? 'auto',
                'structured_data_extra' => $sourcePage?->settings['structured_data_extra'] ?? null,
            ], $authorId);

            return $tag;
        });
    }

    private function ensureTranslationKey(CmsTag $tag): string
    {
        if (filled($tag->translation_key)) {
            return (string) $tag->translation_key;
        }

        $translationKey = (string) Str::ulid();

        $tag->forceFill(['translation_key' => $translationKey])->save();

        return $translationKey;
    }

    private function uniqueSlug(string $sourceSlug, string $targetLocale): string
    {
        $baseSlug = Str::slug($sourceSlug) ?: 'tag';
        $slug = $baseSlug;
        $counter = 2;

        while (CmsTag::query()->where('locale', $targetLocale)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(CmsTag $sourceTag, bool $useAi): array
    {
        $settings = $sourceTag->settings ?? [];

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
