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
        if (! Schema::connection($this->connection)->hasTable('cms_block_placements')) {
            return;
        }

        Schema::connection($this->connection)->table('cms_block_placements', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('cms_block_placements', 'cms_section_id')) {
                $table->foreignId('cms_section_id')->nullable()->change();
            }

            if (! Schema::connection($this->connection)->hasColumn('cms_block_placements', 'parent_placement_id')) {
                $table->foreignId('parent_placement_id')
                    ->nullable()
                    ->after('cms_section_id')
                    ->constrained('cms_block_placements')
                    ->cascadeOnDelete();
            }

            if (! Schema::connection($this->connection)->hasColumn('cms_block_placements', 'slot_key')) {
                $table->string('slot_key', 80)->nullable()->after('parent_placement_id')->index();
            }
        });

        Schema::connection($this->connection)->table('cms_block_placements', function (Blueprint $table): void {
            $table->index(['parent_placement_id', 'slot_key', 'sort_order'], 'cms_block_placements_parent_slot_sort_index');
            $table->index(['parent_placement_id', 'is_active'], 'cms_block_placements_parent_active_index');
        });

        $this->syncFeatureCardSlots();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_block_placements')) {
            return;
        }

        Schema::connection($this->connection)->table('cms_block_placements', function (Blueprint $table): void {
            $table->dropIndex('cms_block_placements_parent_slot_sort_index');
            $table->dropIndex('cms_block_placements_parent_active_index');

            if (Schema::connection($this->connection)->hasColumn('cms_block_placements', 'slot_key')) {
                $table->dropColumn('slot_key');
            }

            if (Schema::connection($this->connection)->hasColumn('cms_block_placements', 'parent_placement_id')) {
                $table->dropConstrainedForeignId('parent_placement_id');
            }
        });
    }

    private function syncFeatureCardSlots(): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_placeable_blocks')) {
            return;
        }

        $definition = config('cms_blocks.types.feature_card');

        if (! is_array($definition)) {
            return;
        }

        $schema = DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', 'feature_card')
            ->value('schema');

        $schema = is_string($schema) && $schema !== '' ? json_decode($schema, true) : [];
        $schema = is_array($schema) ? $schema : [];
        $schema['slots'] = array_values((array) ($definition['slots'] ?? []));

        DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('key', 'feature_card')
            ->update([
                'template_source' => (string) ($definition['safe_blade_template'] ?? ''),
                'schema' => json_encode($schema, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

        if (Schema::connection($this->connection)->hasTable('cms_placeable_block_revisions')) {
            DB::connection($this->connection)
                ->table('cms_placeable_block_revisions')
                ->whereIn('cms_placeable_block_id', function ($query): void {
                    $query->select('id')
                        ->from('cms_placeable_blocks')
                        ->where('key', 'feature_card');
                })
                ->where('status', 'published')
                ->update([
                    'template_source' => (string) ($definition['safe_blade_template'] ?? ''),
                    'schema' => json_encode($schema, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
        }
    }
};
