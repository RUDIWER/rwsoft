<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     *
     * @var string
     */
    protected $connection = 'tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->table('cms_revisions', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('cms_revisions', 'scope')) {
                $table->string('scope', 32)->default('full')->after('revision_number')->index();
            }

            if (! Schema::connection($this->connection)->hasColumn('cms_revisions', 'snapshot_hash')) {
                $table->string('snapshot_hash', 64)->nullable()->after('snapshot')->index();
            }

            if (! Schema::connection($this->connection)->hasColumn('cms_revisions', 'restored_from_revision_id')) {
                $table->foreignId('restored_from_revision_id')->nullable()->after('snapshot_hash')->constrained('cms_revisions')->nullOnDelete();
            }

            if (! Schema::connection($this->connection)->hasColumn('cms_revisions', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('restored_from_revision_id')->index();
            }

            if (! Schema::connection($this->connection)->hasColumn('cms_revisions', 'metadata')) {
                $table->json('metadata')->nullable()->after('is_pinned');
            }
        });

        Schema::connection($this->connection)->table('cms_sections', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('cms_sections', 'revision_key')) {
                $table->string('revision_key', 32)->nullable()->after('import_key')->index();
            }
        });

        Schema::connection($this->connection)->table('cms_blocks', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('cms_blocks', 'revision_key')) {
                $table->string('revision_key', 32)->nullable()->after('import_key')->index();
            }
        });

        Schema::connection($this->connection)->table('cms_block_placements', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('cms_block_placements', 'revision_key')) {
                $table->string('revision_key', 32)->nullable()->after('import_key')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('cms_block_placements', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('cms_block_placements', 'revision_key')) {
                $table->dropColumn('revision_key');
            }
        });

        Schema::connection($this->connection)->table('cms_blocks', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('cms_blocks', 'revision_key')) {
                $table->dropColumn('revision_key');
            }
        });

        Schema::connection($this->connection)->table('cms_sections', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('cms_sections', 'revision_key')) {
                $table->dropColumn('revision_key');
            }
        });

        Schema::connection($this->connection)->table('cms_revisions', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('cms_revisions', 'metadata')) {
                $table->dropColumn('metadata');
            }

            if (Schema::connection($this->connection)->hasColumn('cms_revisions', 'is_pinned')) {
                $table->dropColumn('is_pinned');
            }

            if (Schema::connection($this->connection)->hasColumn('cms_revisions', 'restored_from_revision_id')) {
                $table->dropConstrainedForeignId('restored_from_revision_id');
            }

            if (Schema::connection($this->connection)->hasColumn('cms_revisions', 'snapshot_hash')) {
                $table->dropColumn('snapshot_hash');
            }

            if (Schema::connection($this->connection)->hasColumn('cms_revisions', 'scope')) {
                $table->dropColumn('scope');
            }
        });
    }
};
