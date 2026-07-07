<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsTemplate;
use Illuminate\Support\Str;

class SyncCmsCategoryLandingPageAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(CmsCategory $category, array $validated, ?int $authorId): CmsPage
    {
        $page = $category->landingPage instanceof CmsPage
            ? $category->landingPage
            : new CmsPage;

        $isCreate = ! $page->exists;
        $status = (string) ($validated['status'] ?? 'draft');

        $page->fill([
            'parent_id' => $this->parentLandingPageId($category),
            'detail_template_id' => $this->defaultPageDetailTemplateId((string) $category->locale),
            'author_id' => $isCreate ? $authorId : $page->author_id,
            'title' => (string) $category->title,
            'slug' => (string) $category->slug,
            'locale' => (string) $category->locale,
            'translation_key' => $page->translation_key ?: $this->pageTranslationKey($category),
            'translated_from_page_id' => $page->translated_from_page_id ?: $this->translatedFromPageId($category),
            'status' => $status,
            'template' => $validated['template'] ?? null,
            'short_description' => $validated['excerpt'] ?? $category->description,
            'content_blocks' => $this->contentBlocks($validated['content_blocks'] ?? []),
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
            'canonical_url' => $validated['canonical_url'] ?? null,
            'og_image_path' => $validated['og_image_path'] ?? null,
            'noindex' => (bool) ($validated['noindex'] ?? false),
            'is_home' => false,
            'is_searchable' => (bool) ($validated['is_searchable'] ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? $category->sort_order ?? 0),
            'published_at' => $validated['published_at'] ?? null,
            'settings' => $this->settingsData($validated),
        ]);

        if ($page->status === 'published' && blank($page->published_at)) {
            $page->published_at = now();
        }

        $page->save();

        if ((int) $category->landing_page_id !== (int) $page->id) {
            $category->forceFill(['landing_page_id' => $page->id])->save();
        }

        return $page;
    }

    private function parentLandingPageId(CmsCategory $category): ?int
    {
        if (! $category->parent_id) {
            return null;
        }

        $parent = CmsCategory::query()->find($category->parent_id, ['id', 'landing_page_id']);

        return $parent?->landing_page_id ? (int) $parent->landing_page_id : null;
    }

    private function defaultPageDetailTemplateId(string $locale): ?int
    {
        return CmsTemplate::query()
            ->active()
            ->defaultFor('page.detail', $locale)
            ->value('id');
    }

    private function pageTranslationKey(CmsCategory $category): string
    {
        $sourcePage = $this->translatedFromPage($category);

        return $sourcePage instanceof CmsPage && filled($sourcePage->translation_key)
            ? (string) $sourcePage->translation_key
            : (string) Str::ulid();
    }

    private function translatedFromPageId(CmsCategory $category): ?int
    {
        return $this->translatedFromPage($category)?->id;
    }

    private function translatedFromPage(CmsCategory $category): ?CmsPage
    {
        if (! $category->translated_from_category_id) {
            return null;
        }

        $sourceCategory = CmsCategory::query()
            ->with('landingPage:id,translation_key')
            ->find($category->translated_from_category_id, ['id', 'landing_page_id']);

        return $sourceCategory?->landingPage;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function contentBlocks(array $blocks): array
    {
        if ($blocks === []) {
            return [$this->defaultContentBlock('breadcrumb', [
                'show_current' => true,
                'compact' => false,
            ]), $this->defaultContentBlock('list_grid', [
                'title' => null,
                'category_source' => 'current',
                'show_only_subcategories' => true,
                'limit' => 24,
                'sort_field' => 'published_at',
                'sort_direction' => 'desc',
                'show_search' => false,
            ])];
        }

        return $blocks;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function defaultContentBlock(string $rendererKey, array $data): array
    {
        $block = CmsPlaceableBlock::query()
            ->with('latestPublishedRevision')
            ->where('renderer_key', $rendererKey)
            ->where('status', 'published')
            ->firstOrFail();

        return [
            'cms_placeable_block_id' => (int) $block->id,
            'placeable_block_revision_id' => $block->latestPublishedRevision?->id,
            ...$data,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function settingsData(array $validated): array
    {
        return array_filter([
            'structured_data_schema_type' => $validated['structured_data_schema_type'] ?? 'auto',
            'structured_data_extra' => $validated['structured_data_extra'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
