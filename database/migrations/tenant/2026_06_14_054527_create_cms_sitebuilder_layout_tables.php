<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cms_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('import_key')->nullable()->index();
            $table->string('name');
            $table->string('locale', 12)->default(config('app.locale', 'en'))->index();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('cache_strategy', 32)->default('inherit')->index();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['locale', 'is_default']);
        });

        Schema::create('cms_sections', function (Blueprint $table) {
            $table->id();
            $table->string('import_key')->nullable()->index();
            $table->morphs('owner');
            $table->string('zone', 32)->index();
            $table->string('name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('visible_mobile')->default(true);
            $table->boolean('visible_tablet')->default(true);
            $table->boolean('visible_desktop')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'zone', 'sort_order']);
        });

        Schema::create('cms_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('import_key')->nullable()->index();
            $table->string('type', 64)->index();
            $table->string('name')->nullable();
            $table->json('content')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_shared')->default(false)->index();
            $table->boolean('is_dynamic')->default(false)->index();
            $table->string('cache_strategy', 32)->default('inherit')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cms_block_placements', function (Blueprint $table) {
            $table->id();
            $table->string('import_key')->nullable()->index();
            $table->foreignId('cms_section_id')->constrained('cms_sections')->cascadeOnDelete();
            $table->foreignId('cms_block_id')->constrained('cms_blocks')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('visible_mobile')->default(true);
            $table->boolean('visible_tablet')->default(true);
            $table->boolean('visible_desktop')->default(true);
            $table->unsignedTinyInteger('mobile_span')->default(12);
            $table->unsignedTinyInteger('tablet_span')->default(12);
            $table->unsignedTinyInteger('desktop_span')->default(12);
            $table->string('height_mode', 32)->default('auto');
            $table->string('height_value')->nullable();
            $table->string('cache_strategy', 32)->default('inherit')->index();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['cms_section_id', 'sort_order']);
            $table->index(['cms_block_id', 'is_active']);
        });

        Schema::create('cms_block_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->foreignId('cms_block_placement_id')->constrained('cms_block_placements')->cascadeOnDelete();
            $table->json('content')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['cms_page_id', 'cms_block_placement_id'], 'cms_block_overrides_page_placement_unique');
        });

        Schema::create('cms_block_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->foreignId('cms_block_placement_id')->constrained('cms_block_placements')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['cms_page_id', 'cms_block_placement_id'], 'cms_block_exclusions_page_placement_unique');
        });

        Schema::create('cms_shared_block_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_block_placement_id')->constrained('cms_block_placements')->cascadeOnDelete();
            $table->string('scope_type', 64)->index();
            $table->string('scope_value')->nullable()->index();
            $table->string('locale', 12)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'scope_value', 'locale'], 'cms_shared_block_scopes_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_shared_block_scopes');
        Schema::dropIfExists('cms_block_exclusions');
        Schema::dropIfExists('cms_block_overrides');
        Schema::dropIfExists('cms_block_placements');
        Schema::dropIfExists('cms_blocks');
        Schema::dropIfExists('cms_sections');
        Schema::dropIfExists('cms_layouts');
    }
};
