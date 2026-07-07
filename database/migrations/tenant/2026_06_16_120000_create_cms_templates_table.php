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
        Schema::create('cms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('import_key')->nullable()->index();
            $table->string('name');
            $table->string('locale', 12)->default(config('app.locale', 'en'))->index();
            $table->string('translation_key')->nullable()->index();
            $table->foreignId('translated_from_template_id')->nullable()->constrained('cms_templates')->nullOnDelete();
            $table->string('template_class', 32)->index();
            $table->string('template_key', 64)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('cache_strategy', 32)->default('inherit')->index();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['template_key', 'locale', 'is_default'], 'cms_templates_default_lookup_index');
            $table->index(['template_key', 'locale', 'is_active'], 'cms_templates_active_lookup_index');
        });

        Schema::table('cms_pages', function (Blueprint $table) {
            $table->foreignId('detail_template_id')->nullable()->after('layout_id')->constrained('cms_templates')->nullOnDelete();
        });

        Schema::table('cms_posts', function (Blueprint $table) {
            $table->foreignId('detail_template_id')->nullable()->after('featured_media_asset_id')->constrained('cms_templates')->nullOnDelete();
        });

        Schema::table('cms_categories', function (Blueprint $table) {
            $table->foreignId('archive_template_id')->nullable()->after('landing_page_id')->constrained('cms_templates')->nullOnDelete();
            $table->foreignId('detail_template_id')->nullable()->after('archive_template_id')->constrained('cms_templates')->nullOnDelete();
        });

        Schema::table('cms_tags', function (Blueprint $table) {
            $table->foreignId('archive_template_id')->nullable()->after('landing_page_id')->constrained('cms_templates')->nullOnDelete();
            $table->foreignId('detail_template_id')->nullable()->after('archive_template_id')->constrained('cms_templates')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('detail_template_id');
            $table->dropConstrainedForeignId('archive_template_id');
        });

        Schema::table('cms_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('detail_template_id');
            $table->dropConstrainedForeignId('archive_template_id');
        });

        Schema::table('cms_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('detail_template_id');
        });

        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('detail_template_id');
        });

        Schema::dropIfExists('cms_templates');
    }
};
