<?php

namespace App\Support\PublicSite;

use App\Actions\PublicSite\ResolveAllowedCmsDownloadsAction;
use App\Models\Cms\CmsDownloadAsset;
use App\Models\Cms\CmsDownloadFolder;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsPlaceableBlockRevision;
use App\Models\Cms\CmsPost;
use App\Support\Cms\CmsBlockFieldContract;
use App\Support\Cms\CmsBlockRegistry;
use App\Support\Cms\CmsTemplateFieldRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class CmsBlockPayloadBuilder
{
    /**
     * @var array<int, array<string, mixed>|null>
     */
    private array $dynamicMediaPayloadCache = [];

    private int $runtimeIdSequence = 0;

    public function __construct(
        private readonly PublicMediaUrl $mediaUrl,
        private readonly PublicSafeUrl $safeUrl,
        private readonly CmsContentListBlockResolver $contentListResolver,
        private readonly CmsBreadcrumbBuilder $breadcrumbBuilder,
        private readonly CmsBlockFieldContract $blockFieldContract,
        private readonly CmsBlockRegistry $blockRegistry,
        private readonly CmsTemplateFieldRegistry $templateFieldRegistry,
        private readonly CmsNavigationBuilder $navigationBuilder,
        private readonly ?ResolveAllowedCmsDownloadsAction $allowedDownloads = null,
        private readonly ?Request $request = null,
    ) {}

    /**
     * @param  array<int, mixed>  $blocks
     * @param  array<string, mixed>  $templateContext
     * @param  array<string, mixed>  $contentSlots
     * @param  array<string, mixed>  $templateBlockData
     * @return array<int, array<string, mixed>>
     */
    public function handle(array $blocks, ?CmsPage $page = null, ?CmsPost $post = null, array $templateContext = [], array $contentSlots = [], ?string $contentLocale = null, array $templateBlockData = []): array
    {
        $placeableBlockIds = collect($blocks)
            ->filter(fn ($block): bool => is_array($block))
            ->map(fn (array $block): int => (int) ($block['cms_placeable_block_id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $revisionIds = collect($blocks)
            ->filter(fn ($block): bool => is_array($block))
            ->map(fn (array $block): int => (int) ($block['placeable_block_revision_id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $placeableBlocks = $placeableBlockIds->isEmpty()
            ? collect()
            : CmsPlaceableBlock::query()
                ->with('latestPublishedRevision')
                ->whereIn('id', $placeableBlockIds)
                ->where('status', 'published')
                ->get()
                ->keyBy('id');
        $revisions = $revisionIds->isEmpty()
            ? collect()
            : CmsPlaceableBlockRevision::query()
                ->whereIn('id', $revisionIds)
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->get()
                ->keyBy('id');
        $mediaIds = collect($blocks)
            ->filter(fn ($block): bool => is_array($block) && $this->blockHasField($block, 'media_asset_id'))
            ->pluck('media_asset_id')
            ->merge(
                collect($blocks)
                    ->filter(fn ($block): bool => is_array($block) && $this->blockHasField($block, 'media_asset_ids'))
                    ->flatMap(fn (array $block): array => is_array($block['media_asset_ids'] ?? null) ? $block['media_asset_ids'] : [])
            )
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $mediaAssets = $mediaIds->isEmpty()
            ? collect()
            : CmsMediaAsset::query()
                ->with('translations')
                ->whereIn('id', $mediaIds)
                ->get()
                ->keyBy('id');

        return collect($blocks)
            ->filter(fn ($block): bool => is_array($block))
            ->map(function (array $block) use ($mediaAssets, $page, $post, $templateContext, $contentSlots, $contentLocale, $templateBlockData, $placeableBlocks, $revisions): array {
                $placeableBlock = $this->placeableBlockPayload($block, $placeableBlocks, $revisions);
                $rendererKey = $this->rendererKey($placeableBlock);
                $block = $this->withTemplateBlockData($rendererKey, $block, $placeableBlock, $templateBlockData);
                $block = $this->normalizedBlock($rendererKey, $block, $placeableBlock);

                if ($rendererKey === 'site_user_dashboard') {
                    return $this->withPlaceableBlock($this->siteUserDashboardPayload($block, $placeableBlock, $page, $post, $contentLocale), $placeableBlock);
                }

                if (($this->blockRegistry->definition($rendererKey)['category'] ?? null) === 'system') {
                    return $this->withPlaceableBlock($this->genericPayload($rendererKey, $block, $placeableBlock), $placeableBlock);
                }

                if (($this->blockRegistry->definition($rendererKey)['category'] ?? null) === 'code') {
                    return $this->withPlaceableBlock([
                        'renderer_key' => $rendererKey,
                        'code' => (string) $block['code'],
                    ], $placeableBlock);
                }

                if ($rendererKey === 'breadcrumb') {
                    return $this->withPlaceableBlock([
                        'renderer_key' => 'breadcrumb',
                        'items' => $this->breadcrumbBuilder->handle(
                            $page,
                            $post,
                            (bool) $block['show_current'],
                        ),
                        'show_on_home' => (bool) $block['show_on_home'],
                        'compact' => (bool) $block['compact'],
                        'home_icon' => $this->mdiIconClass($block['home_icon'] ?? null, 'mdi-home'),
                        'separator' => $this->breadcrumbSeparator($block['separator'] ?? null),
                    ], $placeableBlock);
                }

                if ($rendererKey === 'dynamic_field') {
                    return $this->withPlaceableBlock($this->dynamicFieldPayload($block, $templateContext, $contentLocale), $placeableBlock);
                }

                if ($rendererKey === 'content_slot') {
                    return $this->withPlaceableBlock($this->contentSlotPayload($block, $contentSlots), $placeableBlock);
                }

                if (in_array($rendererKey, ['list_rows', 'list_grid'], true)) {
                    return $this->withPlaceableBlock($this->contentListResolver->handle($block, $page), $placeableBlock);
                }

                if (in_array($rendererKey, ['download_list', 'download_browser'], true)) {
                    return $this->withPlaceableBlock($this->downloadPayload($rendererKey, $block, $placeableBlock, $page, $post, $contentLocale), $placeableBlock);
                }

                if ($rendererKey === 'image') {
                    $asset = $this->mediaAsset($mediaAssets, (int) $block['media_asset_id']);
                    $locale = $this->contentLocale($page, $post, $contentLocale);

                    return $this->withPlaceableBlock([
                        'renderer_key' => 'image',
                        'caption' => $block['caption'],
                        'media' => $asset instanceof CmsMediaAsset ? $this->mediaUrl->payload($asset, $locale) : null,
                    ], $placeableBlock);
                }

                if ($rendererKey === 'button') {
                    $target = $this->openInNewTab($block['open_in_new_tab'] ?? false) ? '_blank' : '_self';

                    return $this->withPlaceableBlock([
                        'renderer_key' => 'button',
                        'label' => $block['label'],
                        'url' => $this->safeUrl->handle($block['url']),
                        'open_in_new_tab' => $target === '_blank',
                        'target' => $target,
                        'rel' => $this->linkRel(null, $target) ?? '',
                    ], $placeableBlock);
                }

                if ($rendererKey === 'site_logo') {
                    $locale = $this->contentLocale($page, $post, $contentLocale);

                    return $this->withPlaceableBlock($this->siteLogoPayload($block, $mediaAssets, $locale), $placeableBlock);
                }

                if (in_array($rendererKey, ['site_brand', 'site_link', 'site_button', 'site_promo'], true)) {
                    return $this->withPlaceableBlock($this->headerLinkPayload($rendererKey, $block), $placeableBlock);
                }

                if ($rendererKey === 'site_menu') {
                    $locale = $this->contentLocale($page, $post, $contentLocale);

                    return $this->withPlaceableBlock($this->siteMenuPayload($block, $placeableBlock, $locale), $placeableBlock);
                }

                if ($rendererKey === 'address_block') {
                    $locale = $this->contentLocale($page, $post, $contentLocale);

                    return $this->withPlaceableBlock($this->addressBlockPayload($block, $mediaAssets, $locale), $placeableBlock);
                }

                if (in_array($rendererKey, ['site_baseline', 'site_login', 'site_language_switcher'], true)) {
                    return $this->withPlaceableBlock($this->genericPayload($rendererKey, $block, $placeableBlock), $placeableBlock);
                }

                if ($rendererKey === 'video') {
                    return $this->withPlaceableBlock([
                        'renderer_key' => 'video',
                        'title' => $block['title'],
                        'embed_url' => $this->videoEmbedUrl((string) $block['video_url']),
                    ], $placeableBlock);
                }

                if ($rendererKey === 'logo_strip') {
                    $locale = $this->contentLocale($page, $post, $contentLocale);
                    $media = collect(is_array($block['media_asset_ids'] ?? null) ? $block['media_asset_ids'] : [])
                        ->map(fn (mixed $id): ?array => $this->mediaUrl->payload($this->mediaAsset($mediaAssets, (int) $id), $locale))
                        ->filter()
                        ->values()
                        ->all();

                    return $this->withPlaceableBlock([
                        'renderer_key' => 'logo_strip',
                        'title' => $block['title'],
                        'media' => $media,
                    ], $placeableBlock);
                }

                if ($rendererKey === 'form') {
                    $formTranslationKey = (string) ($block['form_translation_key'] ?? $block['form_key'] ?? '');
                    $locale = $this->contentLocale($page, $post, $contentLocale);
                    $formExists = $formTranslationKey !== '' && CmsForm::query()
                        ->where('translation_key', $formTranslationKey)
                        ->where('locale', $locale)
                        ->where('is_active', true)
                        ->exists();

                    return $this->withPlaceableBlock([
                        'renderer_key' => 'form',
                        'form_translation_key' => $formExists ? $formTranslationKey : null,
                        'locale' => $formExists ? $locale : null,
                    ], $placeableBlock);
                }

                return $this->withPlaceableBlock($this->genericPayload($rendererKey, $block, $placeableBlock), $placeableBlock);
            })
            ->values()
            ->all();
    }

    private function contentLocale(?CmsPage $page, ?CmsPost $post, ?string $contentLocale): string
    {
        return (string) ($page?->locale ?? $post?->locale ?? $contentLocale ?? '');
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $templateContext
     * @return array<string, mixed>
     */
    private function dynamicFieldPayload(array $block, array $templateContext, ?string $contentLocale): array
    {
        $fieldKey = (string) ($block['field_key'] ?? '');
        $templateKey = (string) Arr::get($templateContext, '__template.template_key', '');
        $contract = Arr::get($templateContext, '__template.data_contract', []);
        $contractSystemFields = is_array($contract['system_fields'] ?? null) ? $contract['system_fields'] : [];
        $enabledSystemFields = collect($contractSystemFields)
            ->filter(fn (array $field): bool => (bool) ($field['enabled'] ?? false))
            ->pluck('key');
        $templateFields = collect(is_array($contract['template_fields'] ?? null) ? $contract['template_fields'] : [])
            ->filter(fn (mixed $field): bool => is_array($field))
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->map(fn (string $key): string => str_starts_with($key, 'template.') ? $key : 'template.'.$key);
        $allowedFields = $enabledSystemFields
            ->when(
                $contractSystemFields === [],
                fn ($fields) => $fields->merge(collect($this->templateFieldRegistry->fieldsFor($templateKey))->pluck('key')),
            )
            ->merge($templateFields)
            ->unique()
            ->all();

        if ($fieldKey === '' || ! in_array($fieldKey, $allowedFields, true)) {
            return [
                'renderer_key' => 'dynamic_field',
                'field_key' => null,
                'title' => $block['title'] ?? null,
                'heading_level' => $this->headingLevel($block['heading_level'] ?? null),
                'value' => null,
                'value_type' => 'empty',
            ];
        }

        $value = Arr::get($templateContext, $fieldKey);
        $valueType = $this->valueType($value);

        if ($this->dynamicFieldType($fieldKey, $templateKey, $contract) === 'media') {
            $value = $this->mediaPayload((int) $value, $contentLocale);
            $valueType = $this->valueType($value);
        }

        return [
            'renderer_key' => 'dynamic_field',
            'field_key' => $fieldKey,
            'title' => $block['title'] ?? null,
            'heading_level' => $this->headingLevel($block['heading_level'] ?? null),
            'value' => $value,
            'value_type' => $valueType,
        ];
    }

    private function dynamicFieldType(string $fieldKey, string $templateKey, mixed $contract): ?string
    {
        if (str_starts_with($fieldKey, 'template.')) {
            $templateFieldKey = substr($fieldKey, strlen('template.'));

            foreach ((array) data_get($contract, 'template_fields', []) as $field) {
                if (is_array($field) && ($field['key'] ?? null) === $templateFieldKey) {
                    return is_string($field['type'] ?? null) ? (string) $field['type'] : null;
                }
            }
        }

        $systemField = collect($this->templateFieldRegistry->fieldsFor($templateKey))
            ->first(fn (array $field): bool => ($field['key'] ?? null) === $fieldKey);

        return is_array($systemField) && is_string($systemField['type'] ?? null)
            ? (string) $systemField['type']
            : null;
    }

    private function mediaPayload(int $mediaAssetId, ?string $locale): ?array
    {
        if ($mediaAssetId <= 0) {
            return null;
        }

        if (array_key_exists($mediaAssetId, $this->dynamicMediaPayloadCache)) {
            return $this->dynamicMediaPayloadCache[$mediaAssetId];
        }

        $asset = CmsMediaAsset::query()
            ->with('translations')
            ->whereNull('deleted_at')
            ->find($mediaAssetId);

        $this->dynamicMediaPayloadCache[$mediaAssetId] = $asset instanceof CmsMediaAsset
            ? $this->mediaUrl->payload($asset, $locale)
            : null;

        return $this->dynamicMediaPayloadCache[$mediaAssetId];
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $contentSlots
     * @return array<string, mixed>
     */
    private function contentSlotPayload(array $block, array $contentSlots): array
    {
        $slotKey = (string) ($block['slot_key'] ?? 'content');
        $slot = $contentSlots[$slotKey] ?? [];

        if (is_array($slot) && (array_key_exists('blocks', $slot) || array_key_exists('sections', $slot))) {
            return [
                'renderer_key' => 'content_slot',
                'slot_key' => $slotKey,
                'title' => $block['title'] ?? null,
                'blocks' => is_array($slot['blocks'] ?? null) ? $slot['blocks'] : [],
                'sections' => is_array($slot['sections'] ?? null) ? $slot['sections'] : [],
            ];
        }

        return [
            'renderer_key' => 'content_slot',
            'slot_key' => $slotKey,
            'title' => $block['title'] ?? null,
            'blocks' => is_array($slot) ? $slot : [],
            'sections' => [],
        ];
    }

    private function headingLevel(mixed $headingLevel): string
    {
        return in_array($headingLevel, ['none', 'h1', 'h2', 'h3'], true)
            ? (string) $headingLevel
            : 'none';
    }

    private function valueType(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'empty';
        }

        if (is_array($value) && array_key_exists('url', $value)) {
            return 'media';
        }

        if (is_array($value) && array_is_list($value)) {
            return 'list';
        }

        if (is_array($value)) {
            return 'object';
        }

        return 'scalar';
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function rendererKey(array $placeableBlock): string
    {
        $rendererKey = $placeableBlock['renderer_key'] ?? $placeableBlock['type'] ?? null;

        if (! is_string($rendererKey) || $rendererKey === '') {
            throw new \InvalidArgumentException('CMS block renderer key is missing or unsupported.');
        }

        if (in_array($rendererKey, $this->blockRegistry->typeKeys(), true)) {
            return $rendererKey;
        }

        if (($placeableBlock['rendering_mode'] ?? null) === 'safe_blade' && trim((string) ($placeableBlock['template_source'] ?? '')) !== '') {
            return $rendererKey;
        }

        throw new \InvalidArgumentException('CMS block renderer key is missing or unsupported.');
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function normalizedBlock(string $type, array $block, array $placeableBlock): array
    {
        $normalized = array_replace(
            ['renderer_key' => $type],
            $this->blockRegistry->defaultsFor($type),
            is_array($placeableBlock['defaults'] ?? null) ? $placeableBlock['defaults'] : [],
            $this->nonNullValues($block),
        );

        $normalized['renderer_key'] = $type;
        $normalized['placeable_block'] = $placeableBlock;

        return $normalized;
    }

    private function mdiIconClass(mixed $value, string $fallback): ?string
    {
        $icon = is_scalar($value) ? trim((string) $value) : '';

        if ($icon === '') {
            return null;
        }

        if (preg_match('/^mdi-[a-z0-9-]+$/', $icon) !== 1) {
            return $fallback;
        }

        return $icon;
    }

    private function breadcrumbSeparator(mixed $value): string
    {
        $separator = is_scalar($value) ? trim((string) $value) : '';

        return in_array($separator, ['›', '>', '/', '•'], true) ? $separator : '›';
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $placeableBlock
     * @param  array<string, mixed>  $templateBlockData
     * @return array<string, mixed>
     */
    private function withTemplateBlockData(string $type, array $block, array $placeableBlock, array $templateBlockData): array
    {
        $contentKey = is_scalar($block['content_key'] ?? null) ? (string) $block['content_key'] : '';

        if ($contentKey === '' || ! is_array($templateBlockData[$contentKey] ?? null)) {
            return $block;
        }

        $fields = $this->blockFieldContract->fieldsForBlock(
            $type,
            is_array($placeableBlock['schema'] ?? null) ? $placeableBlock['schema'] : [],
            is_array($placeableBlock['defaults'] ?? null) ? $placeableBlock['defaults'] : [],
        );

        if (is_array($block['page_editable_fields'] ?? null)) {
            $allowedFields = collect($block['page_editable_fields'])
                ->filter(fn (mixed $field): bool => is_string($field) && $field !== '')
                ->values()
                ->all();
            $fields = collect($fields)
                ->filter(fn (array $field): bool => in_array((string) $field['key'], $allowedFields, true))
                ->values()
                ->all();
        }

        $blockData = collect($templateBlockData[$contentKey])
            ->except('_meta')
            ->all();
        $cleanData = $this->blockFieldContract->cleanData($blockData, $fields);

        return $cleanData === [] ? $block : array_replace_recursive($block, $cleanData);
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  Collection<int, CmsPlaceableBlock>  $placeableBlocks
     * @param  Collection<int, CmsPlaceableBlockRevision>  $revisions
     * @return array<string, mixed>
     */
    private function placeableBlockPayload(array $block, Collection $placeableBlocks, Collection $revisions): array
    {
        if ((int) ($block['cms_placeable_block_id'] ?? 0) <= 0) {
            return ['renderer_key' => $this->rendererKey($block)];
        }

        $placeableBlock = $placeableBlocks->get((int) ($block['cms_placeable_block_id'] ?? 0));

        if (! $placeableBlock instanceof CmsPlaceableBlock || $placeableBlock->latestPublishedRevision === null) {
            throw new \InvalidArgumentException('CMS placeable block is missing or unpublished.');
        }

        $revision = null;
        $revisionId = (int) ($block['placeable_block_revision_id'] ?? 0);

        if ($revisionId > 0) {
            $candidate = $revisions->get($revisionId);
            $revision = $candidate instanceof CmsPlaceableBlockRevision && (int) $candidate->cms_placeable_block_id === (int) $placeableBlock->id
                ? $candidate
                : null;
        }

        $revision ??= $placeableBlock->latestPublishedRevision;

        return [
            'id' => (int) $placeableBlock->id,
            'key' => (string) $placeableBlock->key,
            'name' => (string) $placeableBlock->name,
            'category' => (string) ($revision->category ?: $placeableBlock->category ?: 'content'),
            'source' => (string) ($revision->source ?: $placeableBlock->source ?: 'user'),
            'revision_id' => (int) $revision->id,
            'revision_number' => (int) $revision->revision_number,
            'allowed_zones' => $revision->allowed_zones ?? [],
            'rendering_mode' => (string) $revision->rendering_mode,
            'renderer_key' => (string) $revision->renderer_key,
            'template_source' => (string) ($revision->template_source ?? ''),
            'css_source' => (string) ($revision->css_source ?? ''),
            'schema' => $revision->schema ?? [],
            'defaults' => $revision->defaults ?? [],
            'capabilities' => $revision->capabilities ?? [],
            'behavior_config' => $revision->behavior_config ?? [],
            'context_config' => $revision->context_config ?? [],
            'admin_component_key' => $revision->admin_component_key,
            'package_key' => $revision->package_key,
            'is_locked' => (bool) $revision->is_locked,
            'requires_permission' => $revision->requires_permission,
            'published_at' => $revision->published_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function genericPayload(string $type, array $block, array $placeableBlock = []): array
    {
        $payload = [
            'renderer_key' => $type,
            'runtime_id' => $this->runtimeId($type),
        ];
        $fieldDefinitions = $this->blockFieldContract->fieldsForBlock(
            $type,
            is_array($placeableBlock['schema'] ?? null) ? $placeableBlock['schema'] : [],
            is_array($placeableBlock['defaults'] ?? null) ? $placeableBlock['defaults'] : [],
        );
        $cleanData = $this->blockFieldContract->cleanData($block, $fieldDefinitions);
        $contractFieldKeys = collect($fieldDefinitions)
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();

        foreach ($this->fieldsForPayload($type, $placeableBlock) as $field) {
            $value = in_array($field, $contractFieldKeys, true)
                ? ($cleanData[$field] ?? null)
                : ($block[$field] ?? null);

            $payload[$field] = $this->blockRegistry->repeaterFieldNamesFor($type, $field) !== []
                ? $this->repeaterItemsPayload($type, $field, $value ?? [])
                : $value;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $placeableBlock
     * @return array<string, mixed>
     */
    private function siteUserDashboardPayload(array $block, array $placeableBlock, ?CmsPage $page, ?CmsPost $post, ?string $contentLocale): array
    {
        return [
            ...$this->genericPayload('site_user_dashboard', $block, $placeableBlock),
            'downloads' => $this->downloadItems([
                'source_mode' => 'allowed_for_current_user',
            ], $this->contentLocale($page, $post, $contentLocale)),
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $placeableBlock
     * @return array<string, mixed>
     */
    private function downloadPayload(string $type, array $block, array $placeableBlock, ?CmsPage $page, ?CmsPost $post, ?string $contentLocale): array
    {
        $payload = $this->genericPayload($type, $block, $placeableBlock);
        $locale = $this->contentLocale($page, $post, $contentLocale);
        $sourceMode = in_array($payload['source_mode'] ?? null, ['manual', 'folders', 'allowed_for_current_user'], true)
            ? (string) $payload['source_mode']
            : 'folders';
        $options = [
            'source_mode' => $type === 'download_browser' ? 'folders' : $sourceMode,
            'download_asset_ids' => $this->integerList($payload['download_asset_ids'] ?? []),
            'folder_ids' => $this->integerList($payload['folder_ids'] ?? []),
            'include_subfolders' => $this->booleanValue($payload['include_subfolders'] ?? true),
        ];

        return [
            ...$payload,
            'downloads' => $this->downloadItems($options, $locale),
            'locked_folders' => $this->lockedFolderItems($options),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    private function downloadItems(array $options, string $locale): array
    {
        return $this->downloadsResolver()
            ->assets($this->currentRequest(), $options)
            ->map(fn (CmsDownloadAsset $asset): array => [
                'id' => $asset->id,
                'title' => $this->downloadTitle($asset, $locale),
                'description' => $this->downloadDescription($asset, $locale),
                'filename' => $asset->filename,
                'original_filename' => $asset->original_filename,
                'extension' => $asset->extension,
                'size_bytes' => (int) $asset->size_bytes,
                'size_kb' => round(((int) $asset->size_bytes) / 1024, 1),
                'download_url' => route('cms.downloads.download', ['download' => $asset->id, 'filename' => $asset->filename], false),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    private function lockedFolderItems(array $options): array
    {
        $folderIds = $this->integerList($options['folder_ids'] ?? []);
        $query = CmsDownloadFolder::query()
            ->where(function ($query): void {
                $query->where('access_mode', 'password')->orWhereNotNull('password_hash');
            })
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($folderIds !== []) {
            $query->whereIn('id', $folderIds);
        }

        return $query
            ->get(['id', 'name', 'password_hash', 'password_expires_minutes'])
            ->filter(fn (CmsDownloadFolder $folder): bool => ! $this->downloadsResolver()->folderIsUnlocked($folder, $this->currentRequest()))
            ->map(fn (CmsDownloadFolder $folder): array => [
                'id' => $folder->id,
                'name' => $folder->name,
                'unlock_url' => route('cms.downloads.folders.unlock', ['folder' => $folder->id], false),
            ])
            ->values()
            ->all();
    }

    private function downloadTitle(CmsDownloadAsset $asset, string $locale): string
    {
        $translation = $locale !== '' ? $asset->translationForLocale($locale) : null;

        return $translation?->title
            ?: $asset->title
            ?: $asset->original_filename
            ?: $asset->filename
            ?: (string) $asset->path;
    }

    private function downloadDescription(CmsDownloadAsset $asset, string $locale): ?string
    {
        $translation = $locale !== '' ? $asset->translationForLocale($locale) : null;

        return $translation?->description ?: $asset->description;
    }

    /**
     * @return array<int, int>
     */
    private function integerList(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function downloadsResolver(): ResolveAllowedCmsDownloadsAction
    {
        return $this->allowedDownloads ?? app(ResolveAllowedCmsDownloadsAction::class);
    }

    private function currentRequest(): Request
    {
        return $this->request ?? request();
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $placeableBlock
     * @return array<string, mixed>
     */
    private function siteMenuPayload(array $block, array $placeableBlock, string $locale): array
    {
        $menuId = (int) ($block['cms_menu_id'] ?? 0);
        $placementZone = (string) ($block['placement_zone'] ?? '');

        return array_merge(
            $this->genericPayload('site_menu', $block, $placeableBlock),
            [
                'cms_menu_id' => $menuId > 0 ? $menuId : null,
                'menu' => $this->navigationBuilder->menuForId(
                    $menuId,
                    $locale,
                    in_array($placementZone, ['content', 'header', 'footer'], true) ? $placementZone : null,
                ),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  Collection<int, CmsMediaAsset>  $mediaAssets
     * @return array<string, mixed>
     */
    private function addressBlockPayload(array $block, Collection $mediaAssets, string $locale): array
    {
        $value = fn (string $field): string => $this->stringValue($block[$field] ?? null);
        $phones = $this->contactLinks([
            ['label' => $value('phone_1_label'), 'value' => $value('phone_1'), 'href' => $this->phoneHref($value('phone_1'))],
            ['label' => $value('phone_2_label'), 'value' => $value('phone_2'), 'href' => $this->phoneHref($value('phone_2'))],
            ['label' => $value('phone_3_label'), 'value' => $value('phone_3'), 'href' => $this->phoneHref($value('phone_3'))],
        ]);
        $emails = $this->contactLinks([
            ['label' => $value('email_1_label'), 'value' => $value('email_1'), 'href' => $this->emailHref($value('email_1'))],
            ['label' => $value('email_2_label'), 'value' => $value('email_2'), 'href' => $this->emailHref($value('email_2'))],
        ]);
        $customFields = $this->contactLinks([
            ['label' => $this->stringValue($block['custom_field_1_label'] ?? null), 'value' => $this->stringValue($block['custom_field_1_value'] ?? null), 'href' => null],
            ['label' => $this->stringValue($block['custom_field_2_label'] ?? null), 'value' => $this->stringValue($block['custom_field_2_value'] ?? null), 'href' => null],
            ['label' => $this->stringValue($block['custom_field_3_label'] ?? null), 'value' => $this->stringValue($block['custom_field_3_value'] ?? null), 'href' => null],
        ]);
        $mediaAssetId = (int) ($block['media_asset_id'] ?? 0);

        return [
            'renderer_key' => 'address_block',
            'runtime_id' => $this->runtimeId('address-block'),
            'title' => $this->stringValue($block['title'] ?? null),
            'media' => $this->mediaUrl->payload($this->mediaAsset($mediaAssets, $mediaAssetId), $locale),
            'image_position' => in_array($block['image_position'] ?? null, ['top', 'left', 'right', 'bottom'], true)
                ? (string) $block['image_position']
                : 'top',
            'show_company_name' => $this->booleanValue($block['show_company_name'] ?? false),
            'company_name' => $value('company_name'),
            'show_address' => $this->booleanValue($block['show_address'] ?? false),
            'street' => $value('street'),
            'postal_code' => $value('postal_code'),
            'city' => $value('city'),
            'country' => $value('country'),
            'country_code' => $value('country_code'),
            'show_phones' => $this->booleanValue($block['show_phones'] ?? false),
            'phones' => $phones,
            'show_emails' => $this->booleanValue($block['show_emails'] ?? false),
            'emails' => $emails,
            'show_vat_number' => $this->booleanValue($block['show_vat_number'] ?? false),
            'vat_number' => $value('vat_number'),
            'show_custom_fields' => $this->booleanValue($block['show_custom_fields'] ?? false),
            'custom_fields' => $customFields,
        ];
    }

    /**
     * @param  array<int, array{label: string, value: string, href: string|null}>  $items
     * @return array<int, array{label: string, value: string, href: string|null}>
     */
    private function contactLinks(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item): bool => $item['value'] !== '')
            ->values()
            ->all();
    }

    private function stringValue(mixed $value): string
    {
        return trim((string) $value);
    }

    private function booleanValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function phoneHref(string $phone): ?string
    {
        $normalized = preg_replace('/[^0-9+]/', '', $phone) ?: '';

        return $normalized !== '' && preg_match('/^\+?[0-9]{5,20}$/', $normalized) === 1
            ? 'tel:'.$normalized
            : null;
    }

    private function emailHref(string $email): ?string
    {
        $normalized = trim($email);

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false
            ? 'mailto:'.$normalized
            : null;
    }

    /**
     * @param  array<string, mixed>  $placeableBlock
     * @return array<int, string>
     */
    private function fieldsForPayload(string $type, array $placeableBlock): array
    {
        $fields = $this->blockRegistry->fieldsFor($type);

        if ($fields !== []) {
            return $fields;
        }

        $schemaFields = $placeableBlock['schema']['fields'] ?? [];

        return collect(is_array($schemaFields) ? $schemaFields : [])
            ->filter(fn (mixed $field): bool => is_string($field) && preg_match('/^[a-z0-9_]+$/', $field) === 1)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function headerLinkPayload(string $type, array $block): array
    {
        $payload = $this->genericPayload($type, $block);

        foreach (['url', 'link_url'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->safeUrl->handle($payload[$field] ?? null) ?? '';
            }
        }

        foreach (['alt_text', 'label', 'title', 'text'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = (string) ($payload[$field] ?? '');
            }
        }

        $openInNewTab = $this->openInNewTab($payload['open_in_new_tab'] ?? false);

        if (array_key_exists('target', $payload)) {
            $payload['target'] = $openInNewTab ? '_blank' : $this->linkTarget($payload['target'] ?? null);
        } elseif ($openInNewTab) {
            $payload['target'] = '_blank';
        } elseif (array_key_exists('open_in_new_tab', $payload)) {
            $payload['target'] = '_self';
        }

        if (array_key_exists('rel', $payload)) {
            $payload['rel'] = $this->linkRel($payload['rel'] ?? null, $payload['target'] ?? null) ?? '';
        } elseif (array_key_exists('target', $payload)) {
            $payload['rel'] = $this->linkRel(null, $payload['target'] ?? null) ?? '';
        }

        if (array_key_exists('variant', $payload)) {
            $payload['variant'] = in_array($payload['variant'] ?? null, ['primary', 'secondary'], true)
                ? $payload['variant']
                : 'primary';
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  Collection<int, CmsMediaAsset>  $mediaAssets
     * @return array<string, mixed>
     */
    private function siteLogoPayload(array $block, Collection $mediaAssets, string $locale): array
    {
        $payload = $this->headerLinkPayload('site_logo', $block);
        $media = $this->mediaUrl->payload($this->mediaAsset($mediaAssets, (int) ($block['media_asset_id'] ?? 0)), $locale);

        $payload['media'] = $media;

        if (($payload['alt_text'] ?? '') === '' && is_array($media)) {
            $payload['alt_text'] = (string) ($media['alt_text'] ?? '');
        }

        return $payload;
    }

    private function linkTarget(mixed $target): string
    {
        return in_array($target, ['_self', '_blank'], true) ? (string) $target : '_self';
    }

    /**
     * @param  Collection<int, CmsMediaAsset>  $mediaAssets
     */
    private function mediaAsset(Collection $mediaAssets, int $mediaAssetId): ?CmsMediaAsset
    {
        if ($mediaAssetId <= 0) {
            return null;
        }

        $asset = $mediaAssets->get($mediaAssetId);
        if ($asset instanceof CmsMediaAsset) {
            return $asset;
        }

        $asset = CmsMediaAsset::query()
            ->with('translations')
            ->whereNull('deleted_at')
            ->find($mediaAssetId);

        if ($asset instanceof CmsMediaAsset) {
            $mediaAssets->put($mediaAssetId, $asset);
        }

        return $asset instanceof CmsMediaAsset ? $asset : null;
    }

    private function openInNewTab(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    private function linkRel(mixed $rel, mixed $target): ?string
    {
        $value = trim((string) $rel);
        $value = preg_replace('/[^A-Za-z0-9_\-\s]/', '', $value) ?: '';

        if ($value !== '') {
            return collect(preg_split('/\s+/', $value) ?: [])
                ->filter()
                ->unique()
                ->implode(' ');
        }

        return $target === '_blank' ? 'noopener noreferrer' : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function repeaterItemsPayload(string $type, string $field, mixed $items): array
    {
        return collect($this->blockRegistry->normalizeRepeaterItems($type, $field, $items))
            ->values()
            ->map(fn (array $item, int $index): array => [
                'runtime_id' => $this->runtimeId($type.'-'.$field.'-item'),
                'is_first' => $index === 0,
                ...$item,
            ])
            ->values()
            ->all();
    }

    private function runtimeId(string $type): string
    {
        $this->runtimeIdSequence++;

        return 'cms-'.preg_replace('/[^a-z0-9_-]/', '-', $type).'-'.$this->runtimeIdSequence;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $placeableBlock
     * @return array<string, mixed>
     */
    private function withPlaceableBlock(array $payload, ?array $placeableBlock): array
    {
        if ($placeableBlock === null) {
            return $payload;
        }

        $payload['placeable_block'] = $placeableBlock;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function nonNullValues(array $values): array
    {
        return array_filter($values, fn (mixed $value): bool => $value !== null);
    }

    private function videoEmbedUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $videoId = (string) ($query['v'] ?? '');

            if ($videoId === '' && str_starts_with($path, 'embed/')) {
                $videoId = substr($path, strlen('embed/'));
            }

            if ($videoId === '' && str_starts_with($path, 'shorts/')) {
                $videoId = substr($path, strlen('shorts/'));
            }

            return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId) === 1
                ? 'https://www.youtube-nocookie.com/embed/'.$videoId
                : null;
        }

        if ($host === 'youtu.be') {
            return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $path) === 1
                ? 'https://www.youtube-nocookie.com/embed/'.$path
                : null;
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true)) {
            $videoId = collect(explode('/', $path))->first(fn (string $segment): bool => preg_match('/^\d{6,12}$/', $segment) === 1);

            return is_string($videoId) ? 'https://player.vimeo.com/video/'.$videoId : null;
        }

        if ($host === 'player.vimeo.com' && str_starts_with($path, 'video/')) {
            $videoId = substr($path, strlen('video/'));

            return preg_match('/^\d{6,12}$/', $videoId) === 1
                ? 'https://player.vimeo.com/video/'.$videoId
                : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function blockHasField(array $block, string $field): bool
    {
        return in_array($field, $this->blockRegistry->fieldsFor($this->rendererKey($block)), true);
    }
}
