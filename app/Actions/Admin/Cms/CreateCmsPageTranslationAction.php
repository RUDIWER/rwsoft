<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsTemplate;
use App\Support\Ai\CmsPageTranslationAiService;
use App\Support\Cms\CmsTemplateDataContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCmsPageTranslationAction
{
    public function __construct(private readonly CmsPageTranslationAiService $translationAiService) {}

    public function handle(CmsPage $sourcePage, string $targetLocale, int $authorId, bool $useAi = false): CmsPage
    {
        $translatedData = $useAi
            ? $this->translationAiService->translate($sourcePage, $targetLocale)
            : [];

        return DB::transaction(function () use ($sourcePage, $targetLocale, $authorId, $translatedData, $useAi): CmsPage {
            $translationKey = $this->ensureTranslationKey($sourcePage);
            $title = (string) ($translatedData['title'] ?? $sourcePage->title);
            $detailTemplateId = $this->translatedDetailTemplateId($sourcePage, $targetLocale);

            $page = new CmsPage;
            $page->fill([
                'parent_id' => $this->translatedParentId($sourcePage, $targetLocale),
                'detail_template_id' => $detailTemplateId,
                'author_id' => $authorId,
                'title' => $title,
                'slug' => $this->uniqueSlug($useAi ? $title : $sourcePage->slug, $targetLocale),
                'locale' => $targetLocale,
                'translation_key' => $translationKey,
                'translated_from_page_id' => $sourcePage->id,
                'status' => 'draft',
                'template' => $sourcePage->template,
                'short_description' => array_key_exists('short_description', $translatedData) ? $translatedData['short_description'] : $sourcePage->short_description,
                'content_blocks' => [],
                'template_data' => $this->templateData($sourcePage, $detailTemplateId, $translatedData),
                'seo_title' => array_key_exists('seo_title', $translatedData) ? $translatedData['seo_title'] : $sourcePage->seo_title,
                'seo_description' => array_key_exists('seo_description', $translatedData) ? $translatedData['seo_description'] : $sourcePage->seo_description,
                'canonical_url' => null,
                'og_image_path' => $sourcePage->og_image_path,
                'noindex' => true,
                'is_home' => false,
                'is_searchable' => (bool) $sourcePage->is_searchable,
                'sort_order' => (int) $sourcePage->sort_order,
                'published_at' => null,
                'settings' => $this->settings($sourcePage, $useAi),
            ]);
            $page->save();

            return $page;
        });
    }

    private function ensureTranslationKey(CmsPage $page): string
    {
        if (filled($page->translation_key)) {
            return (string) $page->translation_key;
        }

        $translationKey = (string) Str::ulid();

        $page->forceFill(['translation_key' => $translationKey])->save();

        return $translationKey;
    }

    private function translatedParentId(CmsPage $sourcePage, string $targetLocale): ?int
    {
        if (! $sourcePage->parent_id) {
            return null;
        }

        $parent = CmsPage::query()->find($sourcePage->parent_id, ['id', 'translation_key']);

        if (! $parent instanceof CmsPage || blank($parent->translation_key)) {
            return null;
        }

        $translatedParentId = CmsPage::query()
            ->where('translation_key', $parent->translation_key)
            ->where('locale', $targetLocale)
            ->value('id');

        return $translatedParentId ? (int) $translatedParentId : null;
    }

    private function translatedDetailTemplateId(CmsPage $sourcePage, string $targetLocale): ?int
    {
        $sourcePage->loadMissing('detailTemplate');
        $sourceTemplate = $sourcePage->detailTemplate;

        if ($sourceTemplate instanceof CmsTemplate && filled($sourceTemplate->translation_key)) {
            $translatedTemplateId = CmsTemplate::query()
                ->where('translation_key', $sourceTemplate->translation_key)
                ->where('template_class', 'page')
                ->where('template_key', 'page.detail')
                ->where('locale', $targetLocale)
                ->where('is_active', true)
                ->value('id');

            if ($translatedTemplateId) {
                return (int) $translatedTemplateId;
            }
        }

        return CmsTemplate::query()
            ->active()
            ->defaultFor('page.detail', $targetLocale)
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $translatedData
     * @return array<string, mixed>
     */
    private function templateData(CmsPage $sourcePage, ?int $detailTemplateId, array $translatedData): array
    {
        $template = $detailTemplateId ? CmsTemplate::query()->find($detailTemplateId) : null;

        if (! $template instanceof CmsTemplate) {
            return [];
        }

        $templateData = is_array($sourcePage->template_data ?? null) ? $sourcePage->template_data : [];

        foreach ((array) ($translatedData['template_data'] ?? []) as $key => $value) {
            if (is_string($key)) {
                Arr::set($templateData, $key, $value);
            }
        }

        return app(CmsTemplateDataContract::class)->cleanTemplateData($templateData, $template);
    }

    private function uniqueSlug(string $sourceSlug, string $targetLocale): string
    {
        $baseSlug = Str::slug($sourceSlug) ?: 'page';
        $slug = $baseSlug;
        $counter = 2;

        while (CmsPage::query()->where('locale', $targetLocale)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(CmsPage $sourcePage, bool $useAi): array
    {
        $settings = $sourcePage->settings ?? [];
        unset($settings['html_anchor']);

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
