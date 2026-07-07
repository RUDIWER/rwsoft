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
        if (! Schema::connection($this->connection)->hasTable('cms_block_placement_style_revisions')) {
            Schema::connection($this->connection)->create('cms_block_placement_style_revisions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('cms_block_placement_id');
                $table->unsignedInteger('revision_number');
                $table->string('status', 32)->default('published')->index();
                $table->string('title')->nullable();
                $table->json('style_config')->nullable();
                $table->longText('css_source')->nullable();
                $table->string('snapshot_hash', 64)->nullable()->index();
                $table->unsignedBigInteger('author_id')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamps();

                $table->unique(['cms_block_placement_id', 'revision_number'], 'cms_block_style_revision_number_unique');
                $table->foreign('cms_block_placement_id', 'cms_block_style_revisions_placement_fk')
                    ->references('id')
                    ->on('cms_block_placements')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::connection($this->connection)->hasColumn('cms_block_placements', 'published_style_revision_id')) {
            Schema::connection($this->connection)->table('cms_block_placements', function (Blueprint $table): void {
                $table->foreign('published_style_revision_id', 'cms_block_placements_published_style_revision_fk')
                    ->references('id')
                    ->on('cms_block_placement_style_revisions')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection($this->connection)->hasColumn('cms_block_placements', 'published_style_revision_id')) {
            Schema::connection($this->connection)->table('cms_block_placements', function (Blueprint $table): void {
                $table->dropForeign('cms_block_placements_published_style_revision_fk');
            });
        }

        Schema::connection($this->connection)->dropIfExists('cms_block_placement_style_revisions');
    }
};
