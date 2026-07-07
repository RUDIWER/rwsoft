<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use Illuminate\Support\Str;

class SyncCmsTagLandingPageAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(CmsTag $tag, array $validated, ?int $authorId): CmsPage
    {
        $page = $tag->landingPage instanceof CmsPage
            ? $tag->landingPage
            : new CmsPage;

        $isCreate = ! $page->exists;
        $status = (string) ($validated['status'] ?? 'draft');

        $page->fill([
            'detail_template_id' => $this->defaultPageDetailTemplateId((string) $tag->locale),
            'author_id' => $isCreate ? $authorId : $page->author_id,
            'title' => (string) $tag->title,
            'slug' => (string) $tag->slug,
            'locale' => (string) $tag->locale,
            'translation_key' => $page->translation_key ?: $this->pageTranslationKey($tag),
            'translated_from_page_id' => $page->translated_from_page_id ?: $this->translatedFromPageId($tag),
            'status' => $status,
            'template' => $validated['template'] ?? null,
            'short_description' => $validated['excerpt'] ?? $tag->description,
            'content_blocks' => $this->contentBlocks($validated['content_blocks'] ?? []),
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
            'canonical_url' => $validated['canonical_url'] ?? null,
            'og_image_path' => $validated['og_image_path'] ?? null,
            'noindex' => (bool) ($validated['noindex'] ?? false),
            'is_home' => false,
            'is_searchable' => (bool) ($validated['is_searchable'] ?? true),
            'published_at' => $validated['published_at'] ?? null,
            'settings' => $this->settingsData($validated),
        ]);

        if ($page->status === 'published' && blank($page->published_at)) {
            $page->published_at = now();
        }

        $page->save();

        if ((int) $tag->landing_page_id !== (int) $page->id) {
            $tag->forceFill(['landing_page_id' => $page->id])->save();
        }

        return $page;
    }

    private function defaultPageDetailTemplateId(string $locale): ?int
    {
        return CmsTemplate::query()
            ->active()
            ->defaultFor('page.detail', $locale)
            ->value('id');
    }

    private function pageTranslationKey(CmsTag $tag): string
    {
        $sourcePage = $this->translatedFromPage($tag);

        return $sourcePage instanceof CmsPage && filled($sourcePage->translation_key)
            ? (string) $sourcePage->translation_key
            : (string) ($tag->translation_key ?: Str::ulid());
    }

    private function translatedFromPageId(CmsTag $tag): ?int
    {
        return $this->translatedFromPage($tag)?->id;
    }

    private function translatedFromPage(CmsTag $tag): ?CmsPage
    {
        if (! $tag->translated_from_tag_id) {
            return null;
        }

        $sourceTag = CmsTag::query()
            ->with('landingPage:id,translation_key')
            ->find($tag->translated_from_tag_id, ['id', 'landing_page_id']);

        return $sourceTag?->landingPage;
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
                'source_type' => 'tag',
                'tag_source' => 'current',
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
        ], fn ($value): bool => filled($value));
    }
}
