<?php

namespace App\Support\Cms;

use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsPlaceableBlockRevision;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsTemplate;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class CmsTemplateBlockDataContractBuilder
{
    public function __construct(private readonly CmsBlockFieldContract $fieldContract) {}

    /**
     * @return array{blocks: array<int, array<string, mixed>>}
     */
    public function handle(CmsTemplate $template): array
    {
        if (! $template->relationLoaded('sections')) {
            $template->loadMissing([
                'sections.placements.block.placeableBlockRevision',
                'sections.placements.block.placeableBlock.latestPublishedRevision',
                'sections.placements.childPlacements.block.placeableBlockRevision',
                'sections.placements.childPlacements.block.placeableBlock.latestPublishedRevision',
            ]);
        }

        $seenContentKeys = [];

        $blocks = $this->templateSections($template->sections)
            ->flatMap(function (CmsSection $section) use (&$seenContentKeys): array {
                return $this->placementContracts($section, $seenContentKeys);
            })
            ->values()
            ->all();

        return ['blocks' => $blocks];
    }

    /**
     * @param  array<string, mixed>  $templateData
     * @return array<string, mixed>
     */
    public function cleanTemplateData(CmsTemplate $template, array $templateData): array
    {
        $contract = $this->handle($template);
        $cleanBlocks = [];

        foreach ($contract['blocks'] as $block) {
            $contentKey = (string) $block['content_key'];
            $data = data_get($templateData, 'blocks.'.$contentKey, []);

            if (! is_array($data)) {
                continue;
            }

            $hasFilledContent = $this->hasFilledBlockContent($data, $block['fields']);
            $cleanData = $this->fieldContract->cleanData($data, $block['fields']);
            $metaData = is_array($data['_meta'] ?? null) ? $data['_meta'] : [];
            $metaFields = $block['meta_fields'] ?? [];
            $cleanMeta = $this->shouldCleanMetaFields($metaData, $metaFields, $hasFilledContent)
                ? $this->fieldContract->cleanData($metaData, $metaFields)
                : [];

            if (! $this->shouldKeepFieldData($hasFilledContent, $metaData, $metaFields)) {
                $cleanData = [];
            }

            if ($cleanMeta !== []) {
                $cleanData['_meta'] = $cleanMeta;
            }

            if ($cleanData !== []) {
                $cleanBlocks[$contentKey] = $cleanData;
            }
        }

        return $cleanBlocks === [] ? [] : ['blocks' => $cleanBlocks];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(CmsTemplate $template, string $prefix = 'template_data', ?array $templateData = null): array
    {
        $contract = $this->handle($template);
        $rules = [
            $prefix => ['nullable', 'array'],
            $prefix.'.blocks' => ['nullable', 'array'],
        ];

        foreach ($contract['blocks'] as $block) {
            $contentKey = (string) $block['content_key'];
            $metaFields = $block['meta_fields'] ?? [];

            if ($this->shouldValidateBlockFields($contentKey, $block['fields'], $metaFields, $templateData)) {
                $rules = array_merge(
                    $rules,
                    $this->fieldContract->validationRules($block['fields'], $prefix.'.blocks.'.$contentKey),
                );
            }

            $rules = array_merge(
                $rules,
                $this->fieldContract->validationRules($metaFields, $prefix.'.blocks.'.$contentKey.'._meta'),
            );
        }

        return $rules;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<int, array<string, mixed>>  $metaFields
     * @param  array<string, mixed>|null  $templateData
     */
    private function shouldValidateBlockFields(string $contentKey, array $fields, array $metaFields, ?array $templateData): bool
    {
        if (! $this->hasIsActiveMetaField($metaFields)) {
            return true;
        }

        $blockData = data_get($templateData ?? [], 'blocks.'.$contentKey);

        if (! is_array($blockData)) {
            return false;
        }

        $isActive = data_get($blockData, '_meta.is_active');

        if ($this->isFalseLike($isActive)) {
            return false;
        }

        if ($this->isTrueLike($isActive)) {
            return true;
        }

        return $this->hasFilledBlockContent($blockData, $fields);
    }

    /**
     * @param  array<string, mixed>  $metaData
     * @param  array<int, array<string, mixed>>  $metaFields
     */
    private function shouldCleanMetaFields(array $metaData, array $metaFields, bool $hasFilledContent): bool
    {
        if (! $this->hasIsActiveMetaField($metaFields)) {
            return $metaFields !== [];
        }

        return array_key_exists('is_active', $metaData) || $hasFilledContent;
    }

    /**
     * @param  array<string, mixed>  $metaData
     * @param  array<int, array<string, mixed>>  $metaFields
     */
    private function shouldKeepFieldData(bool $hasFilledContent, array $metaData, array $metaFields): bool
    {
        if ($hasFilledContent) {
            return true;
        }

        if (! $this->hasIsActiveMetaField($metaFields)) {
            return false;
        }

        return array_key_exists('is_active', $metaData)
            && $this->isTrueLike($metaData['is_active']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $metaFields
     */
    private function hasIsActiveMetaField(array $metaFields): bool
    {
        return collect($metaFields)
            ->contains(fn (array $field): bool => ($field['key'] ?? null) === 'is_active');
    }

    /**
     * @param  array<string, mixed>  $blockData
     * @param  array<int, array<string, mixed>>  $fields
     */
    private function hasFilledBlockContent(array $blockData, array $fields): bool
    {
        foreach ($fields as $field) {
            $fieldKey = (string) ($field['key'] ?? '');

            if ($fieldKey === '') {
                continue;
            }

            $value = data_get($blockData, $fieldKey);

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (in_array((string) ($field['type'] ?? ''), ['checkbox', 'boolean'], true)) {
                if ($this->isTrueLike($value)) {
                    return true;
                }

                continue;
            }

            if ($value !== false) {
                return true;
            }
        }

        return false;
    }

    private function isFalseLike(mixed $value): bool
    {
        return $value === false || $value === 0 || $value === '0' || $value === 'false';
    }

    private function isTrueLike(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    /**
     * @param  Collection<int, CmsSection>|EloquentCollection<int, CmsSection>|null  $sections
     * @return Collection<int, CmsSection>
     */
    private function templateSections(Collection|EloquentCollection|null $sections): Collection
    {
        return collect($sections ?? [])
            ->filter(fn (CmsSection $section): bool => (bool) $section->is_active && $section->zone === 'content')
            ->sortBy('sort_order')
            ->values();
    }

    /**
     * @param  array<string, bool>  $seenContentKeys
     * @return array<int, array<string, mixed>>
     */
    private function placementContracts(CmsSection $section, array &$seenContentKeys): array
    {
        return collect($section->placements ?? [])
            ->filter(fn (CmsBlockPlacement $placement): bool => (bool) $placement->is_active)
            ->sortBy('sort_order')
            ->flatMap(function (CmsBlockPlacement $placement) use (&$seenContentKeys): array {
                return $this->placementContractTree($placement, $seenContentKeys);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, bool>  $seenContentKeys
     * @return array<int, array<string, mixed>>
     */
    private function placementContractTree(CmsBlockPlacement $placement, array &$seenContentKeys): array
    {
        $contracts = [];
        $contract = $this->placementContract($placement, $seenContentKeys);

        if ($contract !== null) {
            $contracts[] = $contract;
        }

        $children = collect($placement->relationLoaded('childPlacements') ? $placement->childPlacements : [])
            ->filter(fn (CmsBlockPlacement $childPlacement): bool => (bool) $childPlacement->is_active)
            ->sortBy('sort_order')
            ->values();

        foreach ($children as $childPlacement) {
            $childContract = $this->placementContract($childPlacement, $seenContentKeys);

            if ($childContract !== null) {
                $contracts[] = $childContract;
            }
        }

        return $contracts;
    }

    /**
     * @param  array<string, bool>  $seenContentKeys
     * @return array<string, mixed>|null
     */
    private function placementContract(CmsBlockPlacement $placement, array &$seenContentKeys): ?array
    {
        $contentKey = $this->contentKey($placement);

        if ($contentKey === '') {
            return null;
        }

        $revision = $this->revisionForPlacement($placement);

        if (! $revision instanceof CmsPlaceableBlockRevision) {
            return null;
        }

        $rendererKey = (string) ($revision->renderer_key ?: $placement->block?->placeableBlock?->renderer_key ?: '');

        if ($rendererKey === '') {
            return null;
        }

        $allFields = $this->fieldContract->fieldsForBlock($rendererKey, $revision->schema ?? [], $revision->defaults ?? []);
        $fields = $this->pageEditableFields($placement, $allFields);
        $metaFields = $this->pageEditableMetaFields($placement);

        if ($fields === [] && $metaFields === []) {
            return null;
        }

        $contentKey = $this->uniqueContentKey($contentKey, $seenContentKeys);

        return [
            'content_key' => $contentKey,
            'editor_label' => $this->editorLabel($placement, $contentKey),
            'placement_id' => $placement->id ? (int) $placement->id : null,
            'block_id' => $placement->cms_block_id ? (int) $placement->cms_block_id : null,
            'placeable_block_id' => $placement->block?->cms_placeable_block_id ? (int) $placement->block->cms_placeable_block_id : null,
            'placeable_block_revision_id' => (int) $revision->id,
            'renderer_key' => $rendererKey,
            'fields' => $fields,
            'meta_fields' => $metaFields,
        ];
    }

    private function revisionForPlacement(CmsBlockPlacement $placement): ?CmsPlaceableBlockRevision
    {
        if ($placement->block?->placeableBlockRevision instanceof CmsPlaceableBlockRevision) {
            return $placement->block->placeableBlockRevision;
        }

        if ($placement->block?->placeableBlock?->latestPublishedRevision instanceof CmsPlaceableBlockRevision) {
            return $placement->block->placeableBlock->latestPublishedRevision;
        }

        return null;
    }

    private function contentKey(CmsBlockPlacement $placement): string
    {
        $settings = array_replace_recursive(
            $placement->block?->settings ?? [],
            $placement->settings ?? [],
        );

        if (! (bool) ($settings['page_editable'] ?? filled($settings['content_key'] ?? null))) {
            return '';
        }

        $contentKey = is_scalar($settings['content_key'] ?? null) ? (string) $settings['content_key'] : '';
        $contentKey = preg_replace('/[^a-z0-9_]+/', '_', mb_strtolower(trim($contentKey))) ?: '';

        return trim($contentKey, '_');
    }

    /**
     * @param  array<string, bool>  $seenContentKeys
     */
    private function uniqueContentKey(string $contentKey, array &$seenContentKeys): string
    {
        if (! array_key_exists($contentKey, $seenContentKeys)) {
            $seenContentKeys[$contentKey] = true;

            return $contentKey;
        }

        $suffix = 2;
        $candidate = $contentKey.'_'.$suffix;

        while (array_key_exists($candidate, $seenContentKeys)) {
            $suffix++;
            $candidate = $contentKey.'_'.$suffix;
        }

        $seenContentKeys[$candidate] = true;

        return $candidate;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function pageEditableFields(CmsBlockPlacement $placement, array $fields): array
    {
        $settings = array_replace_recursive(
            $placement->block?->settings ?? [],
            $placement->settings ?? [],
        );

        if (! array_key_exists('page_editable_fields', $settings)) {
            return $fields;
        }

        $allowedFields = collect(is_array($settings['page_editable_fields'] ?? null) ? $settings['page_editable_fields'] : [])
            ->filter(fn (mixed $field): bool => is_string($field) && $field !== '')
            ->values()
            ->all();

        return collect($fields)
            ->filter(fn (array $field): bool => in_array((string) $field['key'], $allowedFields, true))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pageEditableMetaFields(CmsBlockPlacement $placement): array
    {
        $settings = array_replace_recursive(
            $placement->block?->settings ?? [],
            $placement->settings ?? [],
        );
        $metaFields = collect(is_array($settings['page_editable_meta'] ?? null) ? $settings['page_editable_meta'] : [])
            ->filter(fn (mixed $field): bool => $field === 'is_active')
            ->values()
            ->all();

        if (! in_array('is_active', $metaFields, true)) {
            return [];
        }

        return [[
            'key' => 'is_active',
            'type' => 'checkbox',
            'required' => false,
            'sort_order' => -10,
            'default' => true,
            'label_key' => 'common.columns.active',
            'placeholder_key' => null,
            'translations' => [],
            'options' => [],
            'fields' => [],
        ]];
    }

    private function editorLabel(CmsBlockPlacement $placement, string $contentKey): string
    {
        $settings = array_replace_recursive(
            $placement->block?->settings ?? [],
            $placement->settings ?? [],
        );
        $label = is_scalar($settings['editor_label'] ?? null) ? trim((string) $settings['editor_label']) : '';

        return $label !== '' ? $label : $contentKey;
    }
}
