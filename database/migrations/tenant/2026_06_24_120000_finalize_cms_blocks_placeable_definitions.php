<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('cms_placeable_blocks')) {
            Schema::connection($this->connection)->table('cms_placeable_blocks', function (Blueprint $table): void {
                if (! Schema::connection($this->connection)->hasColumn('cms_placeable_blocks', 'category')) {
                    $table->string('category', 64)->default('content')->after('description')->index();
                }

                if (! Schema::connection($this->connection)->hasColumn('cms_placeable_blocks', 'source')) {
                    $table->string('source', 64)->default('user')->after('category')->index();
                }

                if (! Schema::connection($this->connection)->hasColumn('cms_placeable_blocks', 'capabilities')) {
                    $table->json('capabilities')->nullable()->after('defaults');
                }

                if (! Schema::connection($this->connection)->hasColumn('cms_placeable_blocks', 'admin_component_key')) {
                    $table->string('admin_component_key', 128)->nullable()->after('context_config')->index();
                }

                if (! Schema::connection($this->connection)->hasColumn('cms_placeable_blocks', 'package_key')) {
                    $table->string('package_key', 128)->nullable()->after('admin_component_key')->index();
                }

                if (! Schema::connection($this->connection)->hasColumn('cms_placeable_blocks', 'sort_order')) {
                    $table->unsignedInteger('sort_order')->default(0)->after('package_key')->index();
                }

                if (! Schema::connection($this->connection)->hasColumn('cms_placeable_blocks', 'is_locked')) {
                    $table->boolean('is_locked')->default(false)->after('sort_order')->index();
                }
            });
        }

        if (Schema::connection($this->connection)->hasTable('cms_placeable_block_revisions')) {
            Schema::connection($this->connection)->table('cms_placeable_block_revisions', function (Blueprint $table): void {
                if (! Schema::connection($this->connection)->hasColumn('cms_placeable_block_revisions', 'category')) {
                    $table->string('category', 64)->default('content')->after('title')->index();
                }

                if (! Schema::connection($this->connection)->hasColumn('cms_placeable_block_revisions', 'source')) {
                    $table->string('source', 64)->default('user')->after('category')->index();
                }

                if (! Schema::connection($this->connection)->hasColumn('cms_placeable_block_revisions', 'capabilities')) {
                    $table->json('capabilities')->nullable()->after('defaults');
                }

                if (! Schema::connection($this->connection)->hasColumn('cms_placeable_block_revisions', 'admin_component_key')) {
                    $table->string('admin_component_key', 128)->nullable()->after('context_config')->index();
                }

                if (! Schema::connection($this->connection)->hasColumn('cms_placeable_block_revisions', 'package_key')) {
                    $table->string('package_key', 128)->nullable()->after('admin_component_key')->index();
                }

                if (! Schema::connection($this->connection)->hasColumn('cms_placeable_block_revisions', 'sort_order')) {
                    $table->unsignedInteger('sort_order')->default(0)->after('package_key')->index();
                }

                if (! Schema::connection($this->connection)->hasColumn('cms_placeable_block_revisions', 'is_locked')) {
                    $table->boolean('is_locked')->default(false)->after('sort_order')->index();
                }
            });
        }

        if (Schema::connection($this->connection)->hasTable('cms_blocks')) {
            $this->dropForeignKeysForColumn('cms_blocks', 'variant_revision_id');
            $this->dropForeignKeysForColumn('cms_blocks', 'cms_block_variant_id');

            Schema::connection($this->connection)->table('cms_blocks', function (Blueprint $table): void {
                if (Schema::connection($this->connection)->hasColumn('cms_blocks', 'variant_revision_id')) {
                    $table->dropColumn('variant_revision_id');
                }

                if (Schema::connection($this->connection)->hasColumn('cms_blocks', 'cms_block_variant_id')) {
                    $table->dropColumn('cms_block_variant_id');
                }

                if (Schema::connection($this->connection)->hasColumn('cms_blocks', 'variant_key')) {
                    $table->dropColumn('variant_key');
                }
            });
        }

        Schema::connection($this->connection)->dropIfExists('cms_block_variant_revisions');
        Schema::connection($this->connection)->dropIfExists('cms_block_variants');
    }

    public function down(): void
    {
        // This migration intentionally finalizes the pre-release block model.
    }

    private function dropForeignKeysForColumn(string $tableName, string $columnName): void
    {
        if (! Schema::connection($this->connection)->hasColumn($tableName, $columnName)) {
            return;
        }

        $foreignKeys = DB::connection($this->connection)->select(
            'select CONSTRAINT_NAME as constraint_name
             from information_schema.KEY_COLUMN_USAGE
             where TABLE_SCHEMA = database()
               and TABLE_NAME = ?
               and COLUMN_NAME = ?
               and REFERENCED_TABLE_NAME is not null',
            [$tableName, $columnName]
        );

        foreach ($foreignKeys as $foreignKey) {
            $constraintName = str_replace('`', '``', (string) $foreignKey->constraint_name);
            DB::connection($this->connection)->statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraintName}`");
        }
    }
};
