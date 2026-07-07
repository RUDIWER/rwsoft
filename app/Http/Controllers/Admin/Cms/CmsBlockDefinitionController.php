<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\StoreCmsBlockDefinitionRequest;
use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsPlaceableBlockRevision;
use App\Models\Cms\CmsSection;
use App\Support\Cms\CmsBlockRegistry;
use App\Support\Cms\CmsCssSourceValidator;
use App\Support\Cms\SafeBladeRenderer;
use App\Support\PublicSite\CmsLanguageSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CmsBlockDefinitionController extends Controller
{
    public function __construct(
        private readonly CmsBlockRegistry $blockRegistry,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Cms/Blocks/Index', [
            'blocks' => CmsPlaceableBlock::query()
                ->with(['latestPublishedRevision'])
                ->withCount('blocks')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (CmsPlaceableBlock $block): array => $this->blockPayload($block))
                ->values(),
        ]);
    }

    public function create(): Response
    {
        return $this->renderEdit(null);
    }

    public function edit(int $block): Response
    {
        return $this->renderEdit(CmsPlaceableBlock::query()->findOrFail($block));
    }

    public function store(StoreCmsBlockDefinitionRequest $request, ?int $block = null): RedirectResponse
    {
        $blockModel = $block ? CmsPlaceableBlock::query()->findOrFail($block) : new CmsPlaceableBlock;
        $validated = $request->validated();
        $isPublishing = (bool) ($validated['publish'] ?? false) || $validated['status'] === 'published';

        $blockModel->fill($this->blockData($validated, $blockModel, $isPublishing, $request->user()?->id));

        DB::transaction(function () use ($blockModel, $request, $isPublishing): void {
            $blockModel->save();

            if ($isPublishing) {
                $this->createPublishedRevision($blockModel, $request->user()?->id, 'block_manager');
            }
        });

        return redirect()
            ->route('admin.cms.blocks.edit', ['block' => $blockModel->id])
            ->with('status', $isPublishing
                ? __('cms_admin_ui.flash.block_published')
                : __('cms_admin_ui.flash.block_saved'));
    }

    public function publish(int $block): RedirectResponse
    {
        $blockModel = CmsPlaceableBlock::query()->findOrFail($block);

        if ($blockModel->rendering_mode === 'safe_blade' && blank($blockModel->template_source)) {
            return back()->with('error', __('cms_admin_ui.validation.block_template_required'));
        }

        if (! app(CmsCssSourceValidator::class)->isSafe((string) $blockModel->css_source)) {
            return back()
                ->withErrors(['css_source' => __('cms_admin_ui.validation.layout_css_forbidden_syntax')])
                ->with('error', __('cms_admin_ui.validation.layout_css_forbidden_syntax'));
        }

        if ($blockModel->rendering_mode === 'safe_blade') {
            try {
                app(SafeBladeRenderer::class)->render((string) $blockModel->template_source, []);
            } catch (\InvalidArgumentException) {
                return back()
                    ->withErrors(['template_source' => __('cms_admin_ui.validation.layout_variant_template_invalid')])
                    ->with('error', __('cms_admin_ui.validation.layout_variant_template_invalid'));
            }
        }

        DB::transaction(function () use ($blockModel): void {
            $blockModel->forceFill([
                'status' => 'published',
                'published_at' => now(),
                'updated_by' => auth()->id(),
            ])->save();

            $this->createPublishedRevision($blockModel, auth()->id(), 'block_manager_publish');
        });

        return redirect()
            ->route('admin.cms.blocks.edit', ['block' => $blockModel->id])
            ->with('status', __('cms_admin_ui.flash.block_published'));
    }

    public function restoreRevision(int $block, int $revision): RedirectResponse
    {
        $blockModel = CmsPlaceableBlock::query()->findOrFail($block);
        $revisionModel = $blockModel->revisions()->whereKey($revision)->firstOrFail();

        if ($revisionModel->rendering_mode === 'safe_blade') {
            try {
                app(SafeBladeRenderer::class)->render((string) $revisionModel->template_source, []);
            } catch (\InvalidArgumentException) {
                return back()
                    ->withErrors(['template_source' => __('cms_admin_ui.validation.layout_variant_template_invalid')])
                    ->with('error', __('cms_admin_ui.validation.layout_variant_template_invalid'));
            }
        }

        $blockModel->forceFill([
            'name' => $revisionModel->title ?: $blockModel->name,
            'category' => $revisionModel->category,
            'source' => $revisionModel->source,
            'status' => 'draft',
            'allowed_zones' => $revisionModel->allowed_zones ?? [],
            'rendering_mode' => $revisionModel->rendering_mode,
            'renderer_key' => $revisionModel->renderer_key,
            'template_source' => $revisionModel->template_source,
            'css_source' => $revisionModel->css_source,
            'schema' => $revisionModel->schema ?? [],
            'defaults' => $revisionModel->defaults ?? [],
            'capabilities' => $revisionModel->capabilities ?? [],
            'behavior_config' => $revisionModel->behavior_config ?? [],
            'context_config' => $revisionModel->context_config ?? [],
            'admin_component_key' => $revisionModel->admin_component_key,
            'package_key' => $revisionModel->package_key,
            'sort_order' => $revisionModel->sort_order,
            'is_locked' => $revisionModel->is_locked,
            'requires_permission' => $revisionModel->requires_permission,
            'updated_by' => auth()->id(),
            'published_at' => null,
        ])->save();

        return redirect()
            ->route('admin.cms.blocks.edit', ['block' => $blockModel->id])
            ->with('status', __('cms_admin_ui.flash.block_revision_restored'));
    }

    private function renderEdit(?CmsPlaceableBlock $block): Response
    {
        $block?->load([
            'latestPublishedRevision',
            'revisions',
            'blocks.placements.section.owner',
        ]);

        return Inertia::render('Admin/Cms/Blocks/Edit', [
            'blockItem' => $block ? $this->blockPayload($block) : null,
            'revisions' => $block
                ? $block->revisions->map(fn (CmsPlaceableBlockRevision $revision): array => $this->revisionPayload($revision))->values()
                : [],
            'blockUsages' => $block ? $this->blockUsagePayload($block) : [],
            'formOptions' => $this->formOptions(),
            'activeLanguages' => app(CmsLanguageSettings::class)->languages(true),
            'availableLocales' => app(CmsLanguageSettings::class)->activeLocales(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function blockData(array $validated, CmsPlaceableBlock $block, bool $isPublishing, ?int $userId): array
    {
        return [
            'key' => $validated['key'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'source' => $validated['source'],
            'status' => $isPublishing ? 'published' : $validated['status'],
            'allowed_zones' => array_values(array_unique($validated['allowed_zones'] ?? [])),
            'rendering_mode' => $validated['rendering_mode'],
            'renderer_key' => $validated['renderer_key'],
            'template_source' => $validated['template_source'] ?? null,
            'css_source' => $validated['css_source'] ?? null,
            'schema' => $this->schema($validated),
            'defaults' => $validated['defaults'] ?? [],
            'capabilities' => $this->capabilities($validated),
            'behavior_config' => [],
            'context_config' => [],
            'admin_component_key' => $validated['admin_component_key'] ?? null,
            'package_key' => $validated['package_key'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_locked' => (bool) ($validated['is_locked'] ?? false),
            'requires_permission' => $validated['requires_permission'] ?? null,
            'updated_by' => $userId,
            'created_by' => $block->exists ? $block->created_by : $userId,
            'published_at' => $isPublishing ? now() : $block->published_at,
        ];
    }

    private function createPublishedRevision(CmsPlaceableBlock $block, ?int $authorId, string $source): CmsPlaceableBlockRevision
    {
        $revisionNumber = ((int) $block->revisions()->max('revision_number')) + 1;

        return $block->revisions()->create([
            'revision_number' => $revisionNumber,
            'status' => 'published',
            'title' => $block->name,
            'category' => $block->category,
            'source' => $block->source,
            'allowed_zones' => $block->allowed_zones ?? [],
            'rendering_mode' => $block->rendering_mode,
            'renderer_key' => $block->renderer_key,
            'template_source' => $block->template_source,
            'css_source' => $block->css_source,
            'schema' => $block->schema ?? [],
            'defaults' => $block->defaults ?? [],
            'capabilities' => $block->capabilities ?? [],
            'behavior_config' => $block->behavior_config ?? [],
            'context_config' => $block->context_config ?? [],
            'admin_component_key' => $block->admin_component_key,
            'package_key' => $block->package_key,
            'sort_order' => $block->sort_order,
            'is_locked' => $block->is_locked,
            'requires_permission' => $block->requires_permission,
            'snapshot_hash' => $this->snapshotHash($block),
            'author_id' => $authorId,
            'metadata' => ['source' => $source],
            'published_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function blockPayload(CmsPlaceableBlock $block): array
    {
        return [
            'id' => (int) $block->id,
            'key' => (string) $block->key,
            'name' => (string) $block->name,
            'description' => $block->description,
            'category' => (string) ($block->category ?: 'content'),
            'source' => (string) ($block->source ?: 'user'),
            'status' => (string) $block->status,
            'allowed_zones' => $block->allowed_zones ?? [],
            'rendering_mode' => (string) $block->rendering_mode,
            'renderer_key' => (string) $block->renderer_key,
            'template_source' => (string) ($block->template_source ?? ''),
            'css_source' => (string) ($block->css_source ?? ''),
            'schema' => $block->schema ?? [],
            'defaults' => $block->defaults ?? [],
            'capabilities' => $block->capabilities ?? [],
            'admin_component_key' => $block->admin_component_key,
            'package_key' => $block->package_key,
            'sort_order' => (int) $block->sort_order,
            'is_locked' => (bool) $block->is_locked,
            'requires_permission' => $block->requires_permission,
            'blocks_count' => (int) ($block->blocks_count ?? $block->blocks()->count()),
            'latest_published_revision' => $block->latestPublishedRevision
                ? $this->revisionPayload($block->latestPublishedRevision)
                : null,
            'published_at' => $block->published_at?->toDateTimeString(),
            'created_at' => $block->created_at?->toDateTimeString(),
            'updated_at' => $block->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revisionPayload(CmsPlaceableBlockRevision $revision): array
    {
        return [
            'id' => (int) $revision->id,
            'revision_number' => (int) $revision->revision_number,
            'status' => (string) $revision->status,
            'title' => $revision->title,
            'published_at' => $revision->published_at?->toDateTimeString(),
            'created_at' => $revision->created_at?->toDateTimeString(),
            'snapshot_hash' => (string) $revision->snapshot_hash,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blockUsagePayload(CmsPlaceableBlock $block): array
    {
        return $block->blocks
            ->flatMap(function (CmsBlock $cmsBlock): array {
                if ($cmsBlock->placements->isEmpty()) {
                    return [$this->blockUsageRow($cmsBlock, null)];
                }

                return $cmsBlock->placements
                    ->map(fn (CmsBlockPlacement $placement): array => $this->blockUsageRow($cmsBlock, $placement))
                    ->all();
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function blockUsageRow(CmsBlock $block, ?CmsBlockPlacement $placement): array
    {
        $section = $placement?->section;
        $owner = $section?->owner;

        return [
            'block_id' => (int) $block->id,
            'block_name' => (string) ($block->name ?: $block->type),
            'placeable_block_revision_id' => $block->placeable_block_revision_id,
            'placement_id' => $placement?->id,
            'placement_active' => $placement ? (bool) $placement->is_active : null,
            'section_id' => $section?->id,
            'section_name' => $section?->name,
            'section_zone' => $section?->zone,
            'owner_type' => $this->ownerTypeLabel($section),
            'owner_id' => $owner?->id,
            'owner_name' => $this->ownerName($owner),
            'owner_edit_url' => $owner instanceof CmsLayout
                ? route('admin.cms.layouts.edit', ['id' => $owner->id])
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function schema(array $validated): array
    {
        $schema = is_array($validated['schema'] ?? null) ? $validated['schema'] : [];

        return [
            'category' => $validated['category'],
            'fields' => array_values($schema['fields'] ?? []),
            'editor_fields' => array_values($schema['editor_fields'] ?? []),
            'preview' => is_array($schema['preview'] ?? null) ? $schema['preview'] : [],
            'slots' => $this->slots($schema['slots'] ?? []),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function slots(mixed $slots): array
    {
        return collect(is_array($slots) ? $slots : [])
            ->filter(fn (mixed $slot): bool => is_array($slot))
            ->map(function (array $slot): array {
                $maxItems = array_key_exists('max_items', $slot) && $slot['max_items'] !== null
                    ? max(1, min(50, (int) $slot['max_items']))
                    : null;

                return array_filter([
                    'key' => (string) $slot['key'],
                    'label' => (string) $slot['label'],
                    'help' => $slot['help'] ?? null,
                    'allowed_block_keys' => array_values(array_unique(array_map('strval', $slot['allowed_block_keys'] ?? []))),
                    'min_items' => max(0, min(50, (int) ($slot['min_items'] ?? 0))),
                    'max_items' => $maxItems,
                    'layout' => in_array($slot['layout'] ?? null, ['stack', 'inline', 'grid'], true) ? $slot['layout'] : 'stack',
                    'columns' => max(1, min(12, (int) ($slot['columns'] ?? 12))),
                    'responsive' => in_array($slot['responsive'] ?? null, ['same', 'wrap_mobile', 'stack_mobile'], true) ? $slot['responsive'] : 'same',
                ], fn (mixed $value): bool => $value !== null && $value !== '');
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, bool>
     */
    private function capabilities(array $validated): array
    {
        $capabilities = is_array($validated['capabilities'] ?? null) ? $validated['capabilities'] : [];

        return array_merge($this->defaultCapabilities($validated), array_map('boolval', $capabilities));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, bool>
     */
    private function defaultCapabilities(array $validated): array
    {
        $isSystem = ($validated['category'] ?? null) === 'system';
        $isSafeBlade = ($validated['rendering_mode'] ?? null) === 'safe_blade';

        return [
            'can_edit_template' => $isSafeBlade && ! $isSystem,
            'can_edit_css' => ! $isSystem || $isSafeBlade,
            'can_edit_fields' => ! $isSystem,
            'can_edit_allowed_zones' => ! $isSystem,
            'can_edit_renderer' => false,
            'can_edit_defaults' => ! $isSystem,
            'can_edit_category' => ! $isSystem,
            'can_edit_admin_component' => false,
            'can_edit_slots' => ! $isSystem,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => ['content', 'header', 'navigation', 'system', 'code', 'mail'],
            'sources' => ['user', 'system', 'package'],
            'zones' => array_values(array_unique(array_merge($this->blockRegistry->contentZones(), $this->blockRegistry->layoutZones()))),
            'rendering_modes' => $this->blockRegistry->renderingModes(),
            'renderer_keys' => $this->blockRegistry->typeKeys(),
            'editor_field_types' => $this->blockRegistry->editorFieldTypes(),
            'slot_layouts' => ['stack', 'inline', 'grid'],
            'slot_responsive_modes' => ['same', 'wrap_mobile', 'stack_mobile'],
            'slot_block_options' => CmsPlaceableBlock::query()
                ->where('status', 'published')
                ->where(function ($query): void {
                    $query->whereNull('requires_permission')
                        ->orWhere('requires_permission', '');
                })
                ->where('category', '!=', 'code')
                ->orderBy('name')
                ->get(['id', 'key', 'name', 'category', 'description', 'renderer_key'])
                ->map(fn (CmsPlaceableBlock $block): array => [
                    'id' => (int) $block->id,
                    'key' => (string) $block->key,
                    'name' => (string) $block->name,
                    'category' => (string) ($block->category ?: 'content'),
                    'description' => $block->description,
                    'renderer_key' => (string) $block->renderer_key,
                    'has_slots' => ! empty($block->schema['slots'] ?? []),
                ])
                ->values()
                ->all(),
        ];
    }

    private function snapshotHash(CmsPlaceableBlock $block): string
    {
        return hash('sha256', json_encode([
            'key' => $block->key,
            'category' => $block->category,
            'source' => $block->source,
            'allowed_zones' => $block->allowed_zones,
            'rendering_mode' => $block->rendering_mode,
            'renderer_key' => $block->renderer_key,
            'template_source' => $block->template_source,
            'css_source' => $block->css_source,
            'schema' => $block->schema,
            'defaults' => $block->defaults,
            'capabilities' => $block->capabilities,
        ], JSON_THROW_ON_ERROR));
    }

    private function ownerTypeLabel(?CmsSection $section): ?string
    {
        return match ($section?->owner_type) {
            CmsLayout::class => __('cms_admin_ui.layouts.title'),
            default => $section?->owner_type,
        };
    }

    private function ownerName(mixed $owner): ?string
    {
        if ($owner instanceof CmsLayout) {
            return (string) $owner->name;
        }

        return null;
    }
}
