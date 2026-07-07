<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Support\Ai\CmsMenuItemTranslationAiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CreateCmsMenuItemTranslationAction
{
    public function __construct(private readonly CmsMenuItemTranslationAiService $translationAiService) {}

    public function handle(CmsMenuItem $sourceItem, string $targetLocale, bool $useAi = false): CmsMenuItem
    {
        $translatedData = $useAi
            ? $this->translationAiService->translate($sourceItem, $targetLocale)
            : [];

        return DB::transaction(function () use ($sourceItem, $targetLocale, $translatedData, $useAi): CmsMenuItem {
            $translationKey = $this->ensureTranslationKey($sourceItem);
            $existing = CmsMenuItem::query()
                ->where('cms_menu_id', $sourceItem->cms_menu_id)
                ->where('translation_key', $translationKey)
                ->where('locale', $targetLocale)
                ->first();

            if ($existing instanceof CmsMenuItem) {
                return $existing;
            }

            $targetPage = $this->targetPage($sourceItem, $targetLocale);
            $targetPost = $this->targetPost($sourceItem, $targetLocale);
            $label = $this->label($sourceItem, $translatedData, $targetPage, $targetPost);
            $url = $this->url($sourceItem, $translatedData);

            $translation = new CmsMenuItem;
            $translation->fill([
                'cms_menu_id' => $sourceItem->cms_menu_id,
                'locale' => $targetLocale,
                'translation_key' => $translationKey,
                'translated_from_menu_item_id' => $sourceItem->id,
                'parent_id' => $this->translatedParentId($sourceItem, $targetLocale),
                'type' => $sourceItem->type,
                'label' => $label,
                'url' => in_array($sourceItem->type, ['custom', 'external'], true)
                    ? $url
                    : null,
                'cms_page_id' => $targetPage?->id,
                'cms_post_id' => $targetPost?->id,
                'target' => $sourceItem->target,
                'rel' => $sourceItem->rel,
                'sort_order' => (int) $sourceItem->sort_order,
                'is_active' => false,
                'metadata' => $this->metadata($sourceItem, $useAi),
            ]);
            $translation->save();

            return $translation;
        });
    }

    private function ensureTranslationKey(CmsMenuItem $item): string
    {
        if (filled($item->translation_key)) {
            return (string) $item->translation_key;
        }

        $translationKey = (string) Str::ulid();

        $item->forceFill(['translation_key' => $translationKey])->save();

        return $translationKey;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(CmsMenuItem $sourceItem, bool $useAi): array
    {
        $metadata = $sourceItem->metadata ?? [];

        if (! $useAi) {
            unset($metadata['translation_source'], $metadata['translation_review_status']);

            return $metadata;
        }

        return array_merge($metadata, [
            'translation_source' => 'ai',
            'translation_review_status' => 'pending',
        ]);
    }

    private function targetPage(CmsMenuItem $sourceItem, string $targetLocale): ?CmsPage
    {
        if (! in_array($sourceItem->type, ['page', 'category'], true)) {
            return null;
        }

        $sourcePage = $sourceItem->page instanceof CmsPage
            ? $sourceItem->page
            : CmsPage::query()->find($sourceItem->cms_page_id);

        if (! $sourcePage instanceof CmsPage || blank($sourcePage->translation_key)) {
            throw new RuntimeException(__('cms_admin_ui.flash.linked_page_translation_missing'));
        }

        $targetPage = CmsPage::query()
            ->where('translation_key', $sourcePage->translation_key)
            ->where('locale', $targetLocale)
            ->first();

        if (! $targetPage instanceof CmsPage) {
            throw new RuntimeException(__('cms_admin_ui.flash.linked_page_translation_missing'));
        }

        return $targetPage;
    }

    private function targetPost(CmsMenuItem $sourceItem, string $targetLocale): ?CmsPost
    {
        if ($sourceItem->type !== 'post') {
            return null;
        }

        $sourcePost = $sourceItem->post instanceof CmsPost
            ? $sourceItem->post
            : CmsPost::query()->find($sourceItem->cms_post_id);

        if (! $sourcePost instanceof CmsPost || blank($sourcePost->translation_key)) {
            throw new RuntimeException(__('cms_admin_ui.flash.linked_post_translation_missing'));
        }

        $targetPost = CmsPost::query()
            ->where('translation_key', $sourcePost->translation_key)
            ->where('locale', $targetLocale)
            ->first();

        if (! $targetPost instanceof CmsPost) {
            throw new RuntimeException(__('cms_admin_ui.flash.linked_post_translation_missing'));
        }

        return $targetPost;
    }

    private function translatedParentId(CmsMenuItem $sourceItem, string $targetLocale): ?int
    {
        if (! $sourceItem->parent_id) {
            return null;
        }

        $parent = CmsMenuItem::query()->find($sourceItem->parent_id, ['id', 'translation_key']);

        if (! $parent instanceof CmsMenuItem || blank($parent->translation_key)) {
            return null;
        }

        $translatedParentId = CmsMenuItem::query()
            ->where('cms_menu_id', $sourceItem->cms_menu_id)
            ->where('translation_key', $parent->translation_key)
            ->where('locale', $targetLocale)
            ->value('id');

        return $translatedParentId ? (int) $translatedParentId : null;
    }

    /**
     * @param  array{label?: string, url?: string|null}  $translatedData
     */
    private function label(
        CmsMenuItem $sourceItem,
        array $translatedData,
        ?CmsPage $targetPage,
        ?CmsPost $targetPost,
    ): string {
        $aiLabel = trim((string) ($translatedData['label'] ?? ''));

        if ($aiLabel !== '') {
            return mb_substr($aiLabel, 0, 160);
        }

        $sourceLabel = trim((string) $sourceItem->label);
        $sourceTargetTitle = trim((string) ($sourceItem->page?->title ?? $sourceItem->post?->title ?? ''));
        $translatedTargetTitle = trim((string) ($targetPage?->title ?? $targetPost?->title ?? ''));

        if ($sourceLabel === '' || ($sourceTargetTitle !== '' && $sourceLabel === $sourceTargetTitle)) {
            return mb_substr($translatedTargetTitle !== '' ? $translatedTargetTitle : $sourceLabel, 0, 160);
        }

        return mb_substr($sourceLabel, 0, 160);
    }

    /**
     * @param  array{label?: string, url?: string|null}  $translatedData
     */
    private function url(CmsMenuItem $sourceItem, array $translatedData): ?string
    {
        $aiUrl = trim((string) ($translatedData['url'] ?? ''));

        if ($aiUrl !== '') {
            return $aiUrl;
        }

        return $sourceItem->url;
    }
}
