<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsLanguage;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class FindCmsMediaAssetUsagesAction
{
    /**
     * @return array<int, array{type: string, id: int, title: string, field: string}>
     */
    public function handle(CmsMediaAsset|int $asset): array
    {
        $assetId = $asset instanceof CmsMediaAsset ? (int) $asset->id : (int) $asset;

        if ($assetId <= 0) {
            return [];
        }

        return collect()
            ->merge($this->pageUsages($assetId))
            ->merge($this->postUsages($assetId))
            ->merge($this->categoryUsages($assetId))
            ->merge($this->tagUsages($assetId))
            ->merge($this->layoutUsages($assetId))
            ->merge($this->templateUsages($assetId))
            ->merge($this->blockUsages($assetId))
            ->merge($this->sectionUsages($assetId))
            ->merge($this->placementUsages($assetId))
            ->merge($this->languageUsages($assetId))
            ->merge($this->settingUsages($assetId))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{type: string, id: int, title: string, field: string}>
     */
    private function pageUsages(int $assetId): array
    {
        return CmsPage::query()
            ->get(['id', 'title', 'content_blocks', 'template_data', 'settings'])
            ->filter(fn (CmsPage $page): bool => $this->containsMediaAssetId([$page->content_blocks, $page->template_data, $page->settings], $assetId))
            ->map(fn (CmsPage $page): array => $this->usage('page', (int) $page->id, (string) $page->title, 'content'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{type: string, id: int, title: string, field: string}>
     */
    private function postUsages(int $assetId): array
    {
        return CmsPost::query()
            ->get(['id', 'title', 'featured_media_asset_id', 'content_blocks', 'settings'])
            ->filter(fn (CmsPost $post): bool => (int) $post->featured_media_asset_id === $assetId || $this->containsMediaAssetId([$post->content_blocks, $post->settings], $assetId))
            ->map(fn (CmsPost $post): array => $this->usage('post', (int) $post->id, (string) $post->title, (int) $post->featured_media_asset_id === $assetId ? 'featured_media_asset_id' : 'content'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{type: string, id: int, title: string, field: string}>
     */
    private function categoryUsages(int $assetId): array
    {
        return $this->jsonModelUsages(CmsCategory::query()->get(['id', 'title', 'settings']), 'category', $assetId, 'settings');
    }

    /**
     * @return array<int, array{type: string, id: int, title: string, field: string}>
     */
    private function tagUsages(int $assetId): array
    {
        return $this->jsonModelUsages(CmsTag::query()->get(['id', 'title', 'settings']), 'tag', $assetId, 'settings');
    }

    /**
     * @return array<int, array{type: string, id: int, title: string, field: string}>
     */
    private function layoutUsages(int $assetId): array
    {
        return $this->jsonModelUsages(CmsLayout::query()->get(['id', 'name as title', 'settings']), 'layout', $assetId, 'settings');
    }

    /**
     * @return array<int, array{type: string, id: int, title: string, field: string}>
     */
    private function templateUsages(int $assetId): array
    {
        return CmsTemplate::query()
            ->get(['id', 'name', 'settings', 'data_contract'])
            ->filter(fn (CmsTemplate $template): bool => $this->containsMediaAssetId([$template->settings, $template->data_contract], $assetId))
            ->map(fn (CmsTemplate $template): array => $this->usage('template', (int) $template->id, (string) $template->name, 'settings'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{type: string, id: int, title: string, field: string}>
     */
    private function blockUsages(int $assetId): array
    {
        return CmsBlock::query()
            ->get(['id', 'name', 'content', 'settings'])
            ->filter(fn (CmsBlock $block): bool => $this->containsMediaAssetId([$block->content, $block->settings], $assetId))
            ->map(fn (CmsBlock $block): array => $this->usage('block', (int) $block->id, (string) $block->name, 'content'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{type: string, id: int, title: string, field: string}>
     */
    private function sectionUsages(int $assetId): array
    {
        return CmsSection::query()
            ->get(['id', 'name', 'settings'])
            ->filter(fn (CmsSection $section): bool => $this->containsMediaAssetId([$section->settings], $assetId))
            ->map(fn (CmsSection $section): array => $this->usage('section', (int) $section->id, (string) $section->name, 'settings'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{type: string, id: int, title: string, field: string}>
     */
    private function placementUsages(int $assetId): array
    {
        return CmsBlockPlacement::query()
            ->with('block:id,name')
            ->get(['id', 'cms_block_id', 'layout_config', 'style_config', 'settings'])
            ->filter(fn (CmsBlockPlacement $placement): bool => $this->containsMediaAssetId([$placement->layout_config, $placement->style_config, $placement->settings], $assetId))
            ->map(fn (CmsBlockPlacement $placement): array => $this->usage('placement', (int) $placement->id, (string) ($placement->block?->name ?: 'Placement'), 'settings'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{type: string, id: int, title: string, field: string}>
     */
    private function languageUsages(int $assetId): array
    {
        return CmsLanguage::query()
            ->where('flag_media_asset_id', $assetId)
            ->get(['id', 'name', 'locale'])
            ->map(fn (CmsLanguage $language): array => $this->usage('language', (int) $language->id, (string) ($language->name ?: $language->locale), 'flag_media_asset_id'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{type: string, id: int, title: string, field: string}>
     */
    private function settingUsages(int $assetId): array
    {
        return CmsSetting::query()
            ->get(['id', 'group', 'key', 'label', 'value'])
            ->filter(fn (CmsSetting $setting): bool => $this->containsMediaAssetId([$setting->value], $assetId))
            ->map(fn (CmsSetting $setting): array => $this->usage('setting', (int) $setting->id, trim($setting->group.'.'.$setting->key, '.'), 'value'))
            ->values()
            ->all();
    }

    /**
     * @param  EloquentCollection<int, object>  $models
     * @return array<int, array{type: string, id: int, title: string, field: string}>
     */
    private function jsonModelUsages(EloquentCollection $models, string $type, int $assetId, string $field): array
    {
        return $models
            ->filter(fn (object $model): bool => $this->containsMediaAssetId([$model->{$field} ?? null], $assetId))
            ->map(fn (object $model): array => $this->usage($type, (int) $model->id, (string) ($model->title ?? $model->name ?? $type), $field))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function containsMediaAssetId(array $values, int $assetId): bool
    {
        foreach ($values as $value) {
            if ($this->valueContainsMediaAssetId($value, $assetId)) {
                return true;
            }
        }

        return false;
    }

    private function valueContainsMediaAssetId(mixed $value, int $assetId, ?string $key = null): bool
    {
        if (in_array($key, ['media_asset_id', 'featured_media_asset_id', 'flag_media_asset_id', 'image_media_asset_id'], true) && (int) $value === $assetId) {
            return true;
        }

        if ($key === 'media_asset_ids' && is_array($value)) {
            return collect($value)->contains(fn (mixed $mediaAssetId): bool => (int) $mediaAssetId === $assetId);
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $childKey => $childValue) {
            if ($this->valueContainsMediaAssetId($childValue, $assetId, is_string($childKey) ? $childKey : null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{type: string, id: int, title: string, field: string}
     */
    private function usage(string $type, int $id, string $title, string $field): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'title' => $title !== '' ? $title : '#'.$id,
            'field' => $field,
        ];
    }
}
