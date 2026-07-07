<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cms_block_placements')) {
            return;
        }

        $usedKeysByTemplate = [];
        $rows = DB::table('cms_block_placements as placement')
            ->join('cms_sections as section', 'section.id', '=', 'placement.cms_section_id')
            ->join('cms_blocks as block', 'block.id', '=', 'placement.cms_block_id')
            ->leftJoin('cms_placeable_blocks as placeable', 'placeable.id', '=', 'block.cms_placeable_block_id')
            ->leftJoin('cms_placeable_block_revisions as revision', 'revision.id', '=', 'block.placeable_block_revision_id')
            ->where('section.owner_type', 'App\\Models\\Cms\\CmsTemplate')
            ->where('section.zone', 'content')
            ->where('section.is_active', true)
            ->where('placement.is_active', true)
            ->orderBy('section.owner_id')
            ->orderBy('section.sort_order')
            ->orderBy('placement.sort_order')
            ->get([
                'placement.id',
                'placement.settings',
                'section.owner_id as template_id',
                'placeable.name as placeable_name',
                'placeable.renderer_key as placeable_renderer_key',
                'placeable.category as placeable_category',
                'revision.renderer_key as revision_renderer_key',
                'revision.category as revision_category',
                'revision.schema as revision_schema',
            ]);

        foreach ($rows as $row) {
            $settings = $this->jsonArray($row->settings);
            $templateId = (int) $row->template_id;
            $existingKey = $this->contentKey($settings['content_key'] ?? null);

            if ($existingKey !== null) {
                $usedKeysByTemplate[$templateId][$existingKey] = true;

                continue;
            }

            $rendererKey = trim((string) ($row->revision_renderer_key ?: $row->placeable_renderer_key));
            $schema = $this->jsonArray($row->revision_schema);

            if (! $this->isPageOverrideEligible($rendererKey, (string) ($row->revision_category ?: $row->placeable_category), $schema)) {
                continue;
            }

            $contentKey = $this->uniqueContentKey($rendererKey, $usedKeysByTemplate[$templateId] ?? []);
            $settings['content_key'] = $contentKey;
            $settings['editor_label'] ??= trim((string) ($row->placeable_name ?: Str::headline($rendererKey)));
            $usedKeysByTemplate[$templateId][$contentKey] = true;

            DB::table('cms_block_placements')
                ->where('id', (int) $row->id)
                ->update(['settings' => json_encode($settings, JSON_THROW_ON_ERROR)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left empty: generated content keys may be used by saved page data.
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function isPageOverrideEligible(string $rendererKey, string $category, array $schema): bool
    {
        if ($rendererKey === '') {
            return false;
        }

        $category = $category !== '' ? $category : (string) config("cms_blocks.blocks.{$rendererKey}.category", 'content');

        if ($category !== 'content') {
            return false;
        }

        if (in_array($rendererKey, ['breadcrumb', 'button', 'content_slot', 'dynamic_field', 'form', 'image', 'list_grid', 'list_rows', 'quote', 'text'], true)) {
            return false;
        }

        $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : config("cms_blocks.blocks.{$rendererKey}.fields", []);

        return collect($fields)
            ->contains(fn (mixed $field): bool => is_string($field) && preg_match('/^[a-z0-9_]+$/', $field) === 1);
    }

    /**
     * @param  array<string, bool>  $usedKeys
     */
    private function uniqueContentKey(string $rendererKey, array $usedKeys): string
    {
        $baseKey = $this->contentKey($rendererKey) ?? 'block';

        if (! array_key_exists($baseKey, $usedKeys)) {
            return $baseKey;
        }

        $suffix = 2;
        $candidate = $baseKey.'_'.$suffix;

        while (array_key_exists($candidate, $usedKeys)) {
            $suffix++;
            $candidate = $baseKey.'_'.$suffix;
        }

        return $candidate;
    }

    private function contentKey(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $key = preg_replace('/[^a-z0-9_]+/', '_', mb_strtolower(trim((string) $value))) ?: '';
        $key = trim($key, '_');

        return preg_match('/^[a-z][a-z0-9_]{0,79}$/', $key) === 1 ? $key : null;
    }
};
