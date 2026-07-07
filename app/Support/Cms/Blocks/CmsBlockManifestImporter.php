<?php

namespace App\Support\Cms\Blocks;

use App\Models\Cms\CmsPlaceableBlock;
use Illuminate\Support\Facades\DB;

class CmsBlockManifestImporter
{
    public function __construct(
        private readonly CmsBlockManifestValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<int, CmsPlaceableBlock>
     */
    public function import(array $manifest, ?int $userId = null, bool $publish = false): array
    {
        $errors = $this->validator->errors($manifest);

        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        return DB::transaction(function () use ($manifest, $userId, $publish): array {
            $imported = [];

            foreach ($manifest['blocks'] as $blockPayload) {
                $imported[] = $this->importBlock((array) $blockPayload, (string) $manifest['package_key'], $userId, $publish);
            }

            return $imported;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function importBlock(array $payload, string $packageKey, ?int $userId, bool $publish): CmsPlaceableBlock
    {
        $block = CmsPlaceableBlock::query()->firstOrNew(['key' => (string) $payload['key']]);
        $status = $publish ? 'published' : (string) ($payload['status'] ?? 'draft');

        $block->fill([
            'name' => (string) $payload['name'],
            'description' => $payload['description'] ?? null,
            'category' => (string) $payload['category'],
            'source' => 'package',
            'status' => $status,
            'allowed_zones' => array_values(array_unique((array) $payload['allowed_zones'])),
            'rendering_mode' => (string) $payload['rendering_mode'],
            'renderer_key' => (string) $payload['renderer_key'],
            'template_source' => $payload['template_source'] ?? null,
            'css_source' => $payload['css_source'] ?? null,
            'schema' => $this->schema($payload),
            'defaults' => is_array($payload['defaults'] ?? null) ? $payload['defaults'] : [],
            'capabilities' => is_array($payload['capabilities'] ?? null) ? $payload['capabilities'] : [],
            'behavior_config' => is_array($payload['behavior_config'] ?? null) ? $payload['behavior_config'] : [],
            'context_config' => is_array($payload['context_config'] ?? null) ? $payload['context_config'] : [],
            'admin_component_key' => $payload['admin_component_key'] ?? null,
            'package_key' => $packageKey,
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            'is_locked' => (bool) ($payload['is_locked'] ?? false),
            'requires_permission' => $payload['requires_permission'] ?? null,
            'updated_by' => $userId,
            'created_by' => $block->exists ? $block->created_by : $userId,
            'published_at' => $publish ? now() : $block->published_at,
        ])->save();

        if ($publish) {
            $this->publishRevision($block, $userId);
        }

        return $block;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function schema(array $payload): array
    {
        return [
            'category' => (string) $payload['category'],
            'fields' => array_values((array) ($payload['schema']['fields'] ?? $payload['fields'] ?? [])),
            'editor_fields' => array_values((array) ($payload['schema']['editor_fields'] ?? $payload['editor_fields'] ?? [])),
            'preview' => is_array($payload['schema']['preview'] ?? null)
                ? $payload['schema']['preview']
                : (is_array($payload['preview'] ?? null) ? $payload['preview'] : []),
        ];
    }

    private function publishRevision(CmsPlaceableBlock $block, ?int $userId): void
    {
        $revisionNumber = ((int) $block->revisions()->max('revision_number')) + 1;

        $block->revisions()->create([
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
            'snapshot_hash' => hash('sha256', json_encode($block->only(['key', 'category', 'source', 'allowed_zones', 'rendering_mode', 'renderer_key', 'template_source', 'css_source', 'schema', 'defaults']), JSON_THROW_ON_ERROR)),
            'author_id' => $userId,
            'metadata' => ['source' => 'manifest_import'],
            'published_at' => now(),
        ]);
    }
}
