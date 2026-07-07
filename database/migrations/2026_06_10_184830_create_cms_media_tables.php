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
        Schema::create('cms_media_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('cms_media_folders')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
        });

        Schema::create('cms_media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('cms_media_folders')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->default('public');
            $table->string('visibility', 24)->default('public')->index();
            $table->string('path')->unique();
            $table->string('filename');
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 160)->nullable();
            $table->string('extension', 32)->nullable()->index();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->json('focal_point')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['folder_id', 'sort_order']);
        });

        Schema::create('cms_media_asset_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_media_asset_id')->constrained('cms_media_assets')->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->timestamps();

            $table->unique(['cms_media_asset_id', 'locale'], 'cms_media_asset_translations_asset_locale_unique');
        });

        Schema::table('cms_posts', function (Blueprint $table) {
            $table->foreign('featured_media_asset_id')
                ->references('id')
                ->on('cms_media_assets')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_posts', function (Blueprint $table) {
            $table->dropForeign(['featured_media_asset_id']);
        });

        Schema::dropIfExists('cms_media_asset_translations');
        Schema::dropIfExists('cms_media_assets');
        Schema::dropIfExists('cms_media_folders');
    }
};
