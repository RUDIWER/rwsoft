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
        Schema::create('cms_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('cms_categories')->nullOnDelete();
            $table->string('type', 32)->default('post')->index();
            $table->string('title');
            $table->string('slug');
            $table->string('locale', 12)->default(config('app.locale', 'en'));
            $table->string('translation_key', 64)->nullable()->index();
            $table->foreignId('translated_from_category_id')->nullable()->constrained('cms_categories')->nullOnDelete();
            $table->foreignId('landing_page_id')->nullable()->constrained('cms_pages')->nullOnDelete();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['type', 'locale', 'slug']);
            $table->unique(['type', 'translation_key', 'locale'], 'cms_categories_type_translation_locale_unique');
            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('cms_tags', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->string('locale', 12)->default(config('app.locale', 'en'));
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['locale', 'slug']);
        });

        Schema::create('cms_post_category', function (Blueprint $table) {
            $table->foreignId('cms_post_id')->constrained('cms_posts')->cascadeOnDelete();
            $table->foreignId('cms_category_id')->constrained('cms_categories')->cascadeOnDelete();

            $table->primary(['cms_post_id', 'cms_category_id']);
        });

        Schema::create('cms_post_tag', function (Blueprint $table) {
            $table->foreignId('cms_post_id')->constrained('cms_posts')->cascadeOnDelete();
            $table->foreignId('cms_tag_id')->constrained('cms_tags')->cascadeOnDelete();

            $table->primary(['cms_post_id', 'cms_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_post_tag');
        Schema::dropIfExists('cms_post_category');
        Schema::dropIfExists('cms_tags');
        Schema::dropIfExists('cms_categories');
    }
};
