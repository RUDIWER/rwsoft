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
        Schema::create('cms_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('featured_media_asset_id')->nullable()->index();
            $table->string('title');
            $table->string('slug');
            $table->string('locale', 12)->default(config('app.locale', 'en'));
            $table->string('status', 32)->default('draft')->index();
            $table->text('excerpt')->nullable();
            $table->json('content_blocks')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_image_path')->nullable();
            $table->boolean('noindex')->default(false);
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_searchable')->default(true);
            $table->timestamp('published_at')->nullable()->index();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['locale', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_posts');
    }
};
