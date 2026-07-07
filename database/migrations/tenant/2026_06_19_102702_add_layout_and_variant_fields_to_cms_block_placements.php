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
        Schema::connection($this->connection)->table('cms_block_placements', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('cms_block_placements', 'layout_config')) {
                $table->json('layout_config')->nullable()->after('desktop_span');
            }

            if (! Schema::connection($this->connection)->hasColumn('cms_block_placements', 'style_config')) {
                $table->json('style_config')->nullable()->after('layout_config');
            }

            if (! Schema::connection($this->connection)->hasColumn('cms_block_placements', 'published_style_revision_id')) {
                $table->foreignId('published_style_revision_id')->nullable()->after('style_config')->constrained('cms_block_placement_style_revisions')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('cms_block_placements', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('cms_block_placements', 'published_style_revision_id')) {
                $table->dropConstrainedForeignId('published_style_revision_id');
            }

            if (Schema::connection($this->connection)->hasColumn('cms_block_placements', 'style_config')) {
                $table->dropColumn('style_config');
            }

            if (Schema::connection($this->connection)->hasColumn('cms_block_placements', 'layout_config')) {
                $table->dropColumn('layout_config');
            }
        });

    }
};
