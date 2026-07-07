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
        Schema::create('cms_menus', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('location')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique('location');
        });

        Schema::create('cms_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_menu_id')->constrained('cms_menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cms_menu_items')->cascadeOnDelete();
            $table->foreignId('cms_page_id')->nullable()->constrained('cms_pages')->nullOnDelete();
            $table->foreignId('cms_post_id')->nullable()->constrained('cms_posts')->nullOnDelete();
            $table->string('type', 32)->default('custom')->index();
            $table->string('label');
            $table->string('url')->nullable();
            $table->string('target', 32)->nullable();
            $table->string('rel')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['cms_menu_id', 'parent_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_menu_items');
        Schema::dropIfExists('cms_menus');
    }
};
