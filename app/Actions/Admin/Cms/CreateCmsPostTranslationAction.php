<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsPost;
use App\Support\Ai\CmsPostTranslationAiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCmsPostTranslationAction
{
    public function __construct(private readonly CmsPostTranslationAiService $translationAiService) {}

    public function handle(CmsPost $sourcePost, string $targetLocale, int $authorId, bool $useAi = false): CmsPost
    {
        $translatedData = $useAi
            ? $this->translationAiService->translate($sourcePost, $targetLocale)
            : [];

        return DB::transaction(function () use ($sourcePost, $targetLocale, $authorId, $translatedData, $useAi): CmsPost {
            $translationKey = $this->ensureTranslationKey($sourcePost);
            $title = (string) ($translatedData['title'] ?? $sourcePost->title);

            $post = new CmsPost;
            $post->fill([
                'author_id' => $authorId,
                'featured_media_asset_id' => $sourcePost->featured_media_asset_id,
                'title' => $title,
                'slug' => $this->uniqueSlug($useAi ? $title : $sourcePost->slug, $targetLocale),
                'locale' => $targetLocale,
                'translation_key' => $translationKey,
                'translated_from_post_id' => $sourcePost->id,
                'status' => 'draft',
                'excerpt' => array_key_exists('excerpt', $translatedData) ? $translatedData['excerpt'] : $sourcePost->excerpt,
                'content_blocks' => $this->contentBlocks($sourcePost, $targetLocale, $translatedData),
                'seo_title' => array_key_exists('seo_title', $translatedData) ? $translatedData['seo_title'] : $sourcePost->seo_title,
                'seo_description' => array_key_exists('seo_description', $translatedData) ? $translatedData['seo_description'] : $sourcePost->seo_description,
                'canonical_url' => null,
                'og_image_path' => $sourcePost->og_image_path,
                'noindex' => true,
                'is_featured' => false,
                'is_searchable' => (bool) $sourcePost->is_searchable,
                'published_at' => null,
                'settings' => $this->settings($sourcePost, $useAi),
            ]);
            $post->save();

            return $post;
        });
    }

    private function ensureTranslationKey(CmsPost $post): string
    {
        if (filled($post->translation_key)) {
            return (string) $post->translation_key;
        }

        $translationKey = (string) Str::ulid();

        $post->forceFill(['translation_key' => $translationKey])->save();

        return $translationKey;
    }

    /**
     * @param  array<string, mixed>  $translatedData
     * @return array<int, array<string, mixed>>
     */
    private function contentBlocks(CmsPost $sourcePost, string $targetLocale, array $translatedData): array
    {
        $contentBlocks = $translatedData['content_blocks'] ?? $sourcePost->content_blocks ?? [];

        return collect($contentBlocks)
            ->filter(fn (mixed $block): bool => is_array($block))
            ->map(fn (array $block): array => $this->contentBlock($block, $targetLocale))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function contentBlock(array $block, string $targetLocale): array
    {
        if (($block['type'] ?? null) !== 'form' || blank($block['form_translation_key'] ?? null)) {
            return $block;
        }

        $translatedFormTranslationKey = CmsForm::query()
            ->where('locale', $targetLocale)
            ->where('translation_key', $block['form_translation_key'])
            ->where('is_active', true)
            ->value('translation_key');

        if (! $translatedFormTranslationKey) {
            return array_diff_key($block, ['form_translation_key' => true]);
        }

        return array_merge($block, ['form_translation_key' => $translatedFormTranslationKey]);
    }

    private function uniqueSlug(string $sourceSlug, string $targetLocale): string
    {
        $baseSlug = Str::slug($sourceSlug) ?: 'bericht';
        $slug = $baseSlug;
        $counter = 2;

        while (CmsPost::query()->where('locale', $targetLocale)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(CmsPost $sourcePost, bool $useAi): array
    {
        $settings = $sourcePost->settings ?? [];

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
