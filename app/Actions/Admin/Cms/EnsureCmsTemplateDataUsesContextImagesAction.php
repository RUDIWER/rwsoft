<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsTemplate;
use App\Support\Cms\CmsTemplateBlockDataContractBuilder;
use Illuminate\Support\Arr;

class EnsureCmsTemplateDataUsesContextImagesAction
{
    public function __construct(
        private readonly CmsTemplateBlockDataContractBuilder $contractBuilder,
        private readonly CopyCmsMediaAssetToContextAction $copyMediaAssetToContext,
    ) {}

    /**
     * @param  array<string, mixed>  $templateData
     * @return array<string, mixed>
     */
    public function handle(CmsTemplate $template, array $templateData, string $contextType, int $contextId): array
    {
        $blocks = data_get($templateData, 'blocks');

        if (! is_array($blocks) || $contextId <= 0) {
            return $templateData;
        }

        foreach ($this->contractBuilder->handle($template)['blocks'] as $block) {
            $contentKey = (string) ($block['content_key'] ?? '');

            if ($contentKey === '' || ! is_array($blocks[$contentKey] ?? null)) {
                continue;
            }

            $blockData = $blocks[$contentKey];

            foreach ($block['fields'] ?? [] as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $blockData = $this->copyFieldImages($blockData, $field, $contextType, $contextId);
            }

            $blocks[$contentKey] = $blockData;
        }

        Arr::set($templateData, 'blocks', $blocks);

        return $templateData;
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @return array<int, mixed>
     */
    public function contentBlocks(array $blocks, string $contextType, int $contextId): array
    {
        if ($contextId <= 0) {
            return $blocks;
        }

        return collect($blocks)
            ->map(fn (mixed $block): mixed => is_array($block)
                ? $this->copyMediaKeysRecursive($block, $contextType, $contextId)
                : $block)
            ->values()
            ->all();
    }

    public function mediaAssetId(mixed $assetId, string $contextType, int $contextId): ?int
    {
        $contextAssetId = $this->contextAssetId($assetId, $contextType, $contextId);

        return (int) $contextAssetId > 0 ? (int) $contextAssetId : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function copyFieldImages(array $data, array $field, string $contextType, int $contextId): array
    {
        $fieldKey = (string) ($field['key'] ?? '');

        if ($fieldKey === '') {
            return $data;
        }

        $fieldType = (string) ($field['type'] ?? '');

        if (in_array($fieldType, ['media_select', 'media'], true)) {
            Arr::set($data, $fieldKey, $this->contextAssetId(Arr::get($data, $fieldKey), $contextType, $contextId));
        }

        if ($fieldType === 'media_list') {
            Arr::set($data, $fieldKey, $this->contextAssetIds(Arr::get($data, $fieldKey), $contextType, $contextId));
        }

        if ($fieldType === 'repeater') {
            $items = Arr::get($data, $fieldKey);

            if (is_array($items)) {
                Arr::set($data, $fieldKey, $this->copyRepeaterImages($items, $field, $contextType, $contextId));
            }
        }

        return $data;
    }

    private function contextAssetId(mixed $assetId, string $contextType, int $contextId): mixed
    {
        $assetId = (int) $assetId;

        if ($assetId <= 0) {
            return null;
        }

        $asset = CmsMediaAsset::query()->find($assetId);

        if (! $asset instanceof CmsMediaAsset) {
            return null;
        }

        return (int) $this->copyMediaAssetToContext->handle($asset, $contextType, $contextId)->id;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function copyMediaKeysRecursive(array $data, string $contextType, int $contextId): array
    {
        foreach ($data as $key => $value) {
            if ($key === 'media_asset_id') {
                $data[$key] = $this->contextAssetId($value, $contextType, $contextId);

                continue;
            }

            if ($key === 'media_asset_ids') {
                $data[$key] = $this->contextAssetIds($value, $contextType, $contextId);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->copyMediaKeysRecursive($value, $contextType, $contextId);
            }
        }

        return $data;
    }

    /**
     * @return array<int, int>
     */
    private function contextAssetIds(mixed $assetIds, string $contextType, int $contextId): array
    {
        if (! is_array($assetIds)) {
            return [];
        }

        return collect($assetIds)
            ->map(fn (mixed $assetId): mixed => $this->contextAssetId($assetId, $contextType, $contextId))
            ->filter(fn (mixed $assetId): bool => (int) $assetId > 0)
            ->map(fn (mixed $assetId): int => (int) $assetId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private function copyRepeaterImages(array $items, array $field, string $contextType, int $contextId): array
    {
        $nestedFields = is_array($field['fields'] ?? null) ? $field['fields'] : [];

        return collect($items)
            ->map(function (mixed $item) use ($nestedFields, $contextType, $contextId): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                foreach ($nestedFields as $nestedField) {
                    if (is_array($nestedField)) {
                        $item = $this->copyFieldImages($item, $nestedField, $contextType, $contextId);
                    }
                }

                return $item;
            })
            ->values()
            ->all();
    }
}
