<?php

namespace App\Actions\Admin\Cms\Health;

use App\Actions\Admin\Cms\Seo\ValidateCmsSeoRulesAction;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsTag;

class ValidateCmsPublishReadinessAction
{
    public function __construct(private readonly ValidateCmsSeoRulesAction $seoRules) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{errors: array<int, string>, warnings: array<int, string>}
     */
    public function content(array $data, string $type = 'page'): array
    {
        $errors = [];
        $warnings = [];

        foreach ($this->blocksFromData($data) as $block) {
            foreach ($this->blockErrors($block) as $error) {
                $errors[] = $error;
            }
        }

        $seoResult = $this->seoRules->handle($data, $type, true);
        $errors = array_merge($errors, $seoResult['errors']);
        $warnings = array_merge($warnings, $seoResult['warnings']);

        return ['errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{errors: array<int, string>, warnings: array<int, string>}
     */
    public function form(array $data): array
    {
        $activeFields = collect((array) ($data['fields'] ?? []))
            ->filter(fn (mixed $field): bool => is_array($field) && (bool) ($field['is_active'] ?? false));

        return [
            'errors' => $activeFields->isEmpty() ? [__('cms_admin_ui.health.publish_errors.form_no_active_fields')] : [],
            'warnings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<int, string>
     */
    private function blockErrors(array $block): array
    {
        $errors = [];

        if (($block['type'] ?? null) === 'form' && filled($block['form_translation_key'] ?? null) && ! CmsForm::query()->where('translation_key', $block['form_translation_key'])->where('is_active', true)->exists()) {
            $errors[] = __('cms_admin_ui.health.publish_errors.block_missing_form');
        }

        if (($block['type'] ?? null) === 'image' && filled($block['media_asset_id'] ?? null) && ! CmsMediaAsset::query()->whereKey((int) $block['media_asset_id'])->exists()) {
            $errors[] = __('cms_admin_ui.health.publish_errors.block_missing_media');
        }

        foreach ((array) ($block['media_asset_ids'] ?? []) as $mediaAssetId) {
            if (! CmsMediaAsset::query()->whereKey((int) $mediaAssetId)->exists()) {
                $errors[] = __('cms_admin_ui.health.publish_errors.block_missing_media');
                break;
            }
        }

        if (filled($block['category_id'] ?? null) && ! CmsCategory::query()->whereKey((int) $block['category_id'])->exists()) {
            $errors[] = __('cms_admin_ui.health.publish_errors.block_missing_category');
        }

        if (filled($block['tag_id'] ?? null) && ! CmsTag::query()->whereKey((int) $block['tag_id'])->exists()) {
            $errors[] = __('cms_admin_ui.health.publish_errors.block_missing_tag');
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function blocksFromData(array $data): array
    {
        $blocks = collect((array) ($data['content_blocks'] ?? []))
            ->map(fn (mixed $block): mixed => $this->normalizeBlock($block));

        if (isset($data['sections']) && is_array($data['sections'])) {
            $blocks = $blocks->merge(collect($data['sections'])->flatten(1)
                ->flatMap(fn (mixed $section): array => is_array($section) ? ($section['placements'] ?? []) : [])
                ->map(fn (mixed $placement): mixed => is_array($placement) ? $this->normalizeBlock($placement['block'] ?? []) : [])
            );
        }

        return $blocks
            ->filter(fn (mixed $block): bool => is_array($block))
            ->values()
            ->all();
    }

    private function normalizeBlock(mixed $block): mixed
    {
        if (! is_array($block)) {
            return $block;
        }

        if (isset($block['content']) && is_array($block['content'])) {
            return array_merge(['type' => $block['type'] ?? null], $block['content']);
        }

        return $block;
    }
}
