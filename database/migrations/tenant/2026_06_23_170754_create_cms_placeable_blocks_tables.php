<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_placeable_blocks')) {
            Schema::connection($this->connection)->create('cms_placeable_blocks', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 128)->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('category', 64)->default('content')->index();
                $table->string('source', 64)->default('user')->index();
                $table->string('status', 32)->default('draft')->index();
                $table->json('allowed_zones');
                $table->string('rendering_mode', 64)->default('safe_blade')->index();
                $table->string('renderer_key', 128)->nullable()->index();
                $table->longText('template_source')->nullable();
                $table->longText('css_source')->nullable();
                $table->json('schema')->nullable();
                $table->json('defaults')->nullable();
                $table->json('capabilities')->nullable();
                $table->json('behavior_config')->nullable();
                $table->json('context_config')->nullable();
                $table->string('admin_component_key', 128)->nullable()->index();
                $table->string('package_key', 128)->nullable()->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->boolean('is_locked')->default(false)->index();
                $table->string('requires_permission')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'rendering_mode'], 'cms_placeable_blocks_status_mode_index');
                $table->index(['category', 'status'], 'cms_placeable_blocks_category_status_index');
            });
        }

        if (! Schema::connection($this->connection)->hasTable('cms_placeable_block_revisions')) {
            Schema::connection($this->connection)->create('cms_placeable_block_revisions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_placeable_block_id')->constrained('cms_placeable_blocks')->cascadeOnDelete();
                $table->unsignedInteger('revision_number');
                $table->string('status', 32)->default('draft')->index();
                $table->string('title')->nullable();
                $table->string('category', 64)->default('content')->index();
                $table->string('source', 64)->default('user')->index();
                $table->json('allowed_zones');
                $table->string('rendering_mode', 64)->default('safe_blade')->index();
                $table->string('renderer_key', 128)->nullable()->index();
                $table->longText('template_source')->nullable();
                $table->longText('css_source')->nullable();
                $table->json('schema')->nullable();
                $table->json('defaults')->nullable();
                $table->json('capabilities')->nullable();
                $table->json('behavior_config')->nullable();
                $table->json('context_config')->nullable();
                $table->string('admin_component_key', 128)->nullable()->index();
                $table->string('package_key', 128)->nullable()->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->boolean('is_locked')->default(false)->index();
                $table->string('requires_permission')->nullable();
                $table->string('snapshot_hash', 64)->index();
                $table->unsignedBigInteger('author_id')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamps();

                $table->unique(['cms_placeable_block_id', 'revision_number'], 'cms_placeable_block_revisions_number_unique');
                $table->index(['cms_placeable_block_id', 'status'], 'cms_placeable_block_revisions_block_status_index');
            });
        }

        if (Schema::connection($this->connection)->hasTable('cms_blocks')) {
            Schema::connection($this->connection)->table('cms_blocks', function (Blueprint $table): void {
                if (! Schema::connection($this->connection)->hasColumn('cms_blocks', 'cms_placeable_block_id')) {
                    $table->foreignId('cms_placeable_block_id')->nullable()->after('import_key')->constrained('cms_placeable_blocks')->nullOnDelete();
                }

                if (! Schema::connection($this->connection)->hasColumn('cms_blocks', 'placeable_block_revision_id')) {
                    $table->foreignId('placeable_block_revision_id')->nullable()->after('cms_placeable_block_id')->constrained('cms_placeable_block_revisions')->nullOnDelete();
                }
            });
        }

        $this->seedConfiguredPlaceableBlocks();
        $this->linkExistingBlocksToPlaceableBlocks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('cms_blocks')) {
            Schema::connection($this->connection)->table('cms_blocks', function (Blueprint $table): void {
                if (Schema::connection($this->connection)->hasColumn('cms_blocks', 'placeable_block_revision_id')) {
                    $table->dropConstrainedForeignId('placeable_block_revision_id');
                }

                if (Schema::connection($this->connection)->hasColumn('cms_blocks', 'cms_placeable_block_id')) {
                    $table->dropConstrainedForeignId('cms_placeable_block_id');
                }
            });
        }

        Schema::connection($this->connection)->dropIfExists('cms_placeable_block_revisions');
        Schema::connection($this->connection)->dropIfExists('cms_placeable_blocks');
    }

    private function seedConfiguredPlaceableBlocks(): void
    {
        $definitions = config('cms_blocks.types', []);
        $now = now();

        foreach ($definitions as $rendererKey => $definition) {
            if (! is_string($rendererKey) || ! is_array($definition)) {
                continue;
            }

            $zones = array_values(array_filter((array) ($definition['zones'] ?? []), fn (mixed $zone): bool => is_string($zone) && $zone !== ''));

            if ($zones === []) {
                continue;
            }

            $renderingMode = (string) ($definition['rendering_mode'] ?? 'platform_blade');
            $templateSource = $renderingMode === 'safe_blade'
                ? (string) ($definition['safe_blade_template'] ?? '')
                : null;

            $payload = [
                'name' => $this->placeableBlockName($rendererKey, $definition),
                'description' => null,
                'category' => $this->category($definition),
                'source' => 'system',
                'status' => 'published',
                'allowed_zones' => json_encode($zones, JSON_THROW_ON_ERROR),
                'rendering_mode' => $renderingMode,
                'renderer_key' => $rendererKey,
                'template_source' => $templateSource,
                'css_source' => null,
                'schema' => json_encode($this->editorSchema($definition), JSON_THROW_ON_ERROR),
                'defaults' => json_encode(is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [], JSON_THROW_ON_ERROR),
                'capabilities' => json_encode($this->capabilities($definition, $renderingMode), JSON_THROW_ON_ERROR),
                'behavior_config' => json_encode([], JSON_THROW_ON_ERROR),
                'context_config' => json_encode([], JSON_THROW_ON_ERROR),
                'admin_component_key' => null,
                'package_key' => null,
                'sort_order' => 0,
                'is_locked' => $this->category($definition) === 'system',
                'requires_permission' => is_string($definition['requires_permission'] ?? null) ? $definition['requires_permission'] : null,
                'published_at' => $now,
                'deleted_at' => null,
                'updated_at' => $now,
            ];

            $blockId = (int) DB::connection($this->connection)
                ->table('cms_placeable_blocks')
                ->where('key', $rendererKey)
                ->value('id');

            if ($blockId > 0) {
                DB::connection($this->connection)
                    ->table('cms_placeable_blocks')
                    ->where('id', $blockId)
                    ->update($payload);
            } else {
                $blockId = (int) DB::connection($this->connection)
                    ->table('cms_placeable_blocks')
                    ->insertGetId(array_merge($payload, [
                        'key' => $rendererKey,
                        'created_at' => $now,
                    ]));
            }

            $this->publishRevision($blockId, $rendererKey, $payload, $now);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function category(array $definition): string
    {
        $category = (string) ($definition['category'] ?? 'content');

        return in_array($category, ['content', 'header', 'navigation', 'system', 'code'], true)
            ? $category
            : 'content';
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, bool>
     */
    private function capabilities(array $definition, string $renderingMode): array
    {
        $category = $this->category($definition);

        return [
            'can_edit_template' => $renderingMode === 'safe_blade' && $category !== 'system',
            'can_edit_css' => $category !== 'system' || $renderingMode !== 'platform_blade',
            'can_edit_fields' => $category !== 'system',
            'can_edit_allowed_zones' => $category !== 'system',
            'can_edit_renderer' => false,
            'can_edit_defaults' => $category !== 'system',
            'can_edit_category' => $category !== 'system',
            'can_edit_admin_component' => false,
            'can_edit_slots' => $category !== 'system',
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function placeableBlockName(string $rendererKey, array $definition): string
    {
        $labelKey = (string) ($definition['label_key'] ?? '');

        if ($labelKey !== '') {
            return __('cms_admin_ui.'.$labelKey);
        }

        return str($rendererKey)->replace('_', ' ')->title()->toString();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function editorSchema(array $definition): array
    {
        return [
            'category' => $definition['category'] ?? null,
            'fields' => array_values((array) ($definition['fields'] ?? [])),
            'editor_fields' => array_values((array) data_get($definition, 'editor.fields', [])),
            'editor_visible' => (bool) ($definition['editor_visible'] ?? true),
            'preview' => is_array($definition['preview'] ?? null) ? $definition['preview'] : [],
            'slots' => array_values((array) ($definition['slots'] ?? [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publishRevision(int $blockId, string $rendererKey, array $payload, mixed $now): void
    {
        if ($blockId <= 0) {
            return;
        }

        $snapshotHash = hash('sha256', json_encode([
            'key' => $rendererKey,
            'category' => $payload['category'],
            'source' => $payload['source'],
            'allowed_zones' => $payload['allowed_zones'],
            'rendering_mode' => $payload['rendering_mode'],
            'renderer_key' => $rendererKey,
            'template_source' => $payload['template_source'],
            'css_source' => $payload['css_source'],
            'schema' => $payload['schema'],
            'defaults' => $payload['defaults'],
            'capabilities' => $payload['capabilities'],
            'admin_component_key' => $payload['admin_component_key'],
            'package_key' => $payload['package_key'],
            'sort_order' => $payload['sort_order'],
            'is_locked' => $payload['is_locked'],
        ], JSON_THROW_ON_ERROR));

        $exists = DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $blockId)
            ->where('snapshot_hash', $snapshotHash)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->exists();

        if ($exists) {
            return;
        }

        $revisionNumber = ((int) DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->where('cms_placeable_block_id', $blockId)
            ->max('revision_number')) + 1;

        DB::connection($this->connection)
            ->table('cms_placeable_block_revisions')
            ->insert([
                'cms_placeable_block_id' => $blockId,
                'revision_number' => $revisionNumber,
                'status' => 'published',
                'title' => $payload['name'],
                'category' => $payload['category'],
                'source' => $payload['source'],
                'allowed_zones' => $payload['allowed_zones'],
                'rendering_mode' => $payload['rendering_mode'],
                'renderer_key' => $rendererKey,
                'template_source' => $payload['template_source'],
                'css_source' => $payload['css_source'],
                'schema' => $payload['schema'],
                'defaults' => $payload['defaults'],
                'capabilities' => $payload['capabilities'],
                'behavior_config' => $payload['behavior_config'],
                'context_config' => $payload['context_config'],
                'admin_component_key' => $payload['admin_component_key'],
                'package_key' => $payload['package_key'],
                'sort_order' => $payload['sort_order'],
                'is_locked' => $payload['is_locked'],
                'requires_permission' => $payload['requires_permission'],
                'snapshot_hash' => $snapshotHash,
                'metadata' => json_encode(['source' => 'configured_placeable_blocks'], JSON_THROW_ON_ERROR),
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
    }

    private function linkExistingBlocksToPlaceableBlocks(): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_blocks')) {
            return;
        }

        DB::connection($this->connection)
            ->table('cms_blocks')
            ->whereNull('cms_placeable_block_id')
            ->orderBy('id')
            ->select(['id', 'type'])
            ->chunkById(200, function ($blocks): void {
                foreach ($blocks as $block) {
                    $placeableBlock = DB::connection($this->connection)
                        ->table('cms_placeable_blocks')
                        ->where('key', (string) $block->type)
                        ->first(['id']);

                    if (! $placeableBlock) {
                        continue;
                    }

                    $revisionId = DB::connection($this->connection)
                        ->table('cms_placeable_block_revisions')
                        ->where('cms_placeable_block_id', (int) $placeableBlock->id)
                        ->where('status', 'published')
                        ->whereNotNull('published_at')
                        ->orderByDesc('revision_number')
                        ->value('id');

                    DB::connection($this->connection)
                        ->table('cms_blocks')
                        ->where('id', (int) $block->id)
                        ->update([
                            'cms_placeable_block_id' => (int) $placeableBlock->id,
                            'placeable_block_revision_id' => $revisionId ? (int) $revisionId : null,
                        ]);
                }
            }, 'id');
    }
};
