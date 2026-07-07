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
        Schema::dropIfExists('cms_menu_item_translations');
        Schema::dropIfExists('cms_menu_translations');

        if (Schema::hasTable('cms_menus') && Schema::hasColumn('cms_menus', 'locale')) {
            Schema::table('cms_menus', function (Blueprint $table): void {
                $table->dropUnique('cms_menus_locale_slug_unique');
                $table->dropUnique('cms_menus_locale_location_unique');
                $table->dropColumn('locale');
            });

            Schema::table('cms_menus', function (Blueprint $table): void {
                $table->unique('slug');
                $table->unique('location');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cms_menus') && ! Schema::hasColumn('cms_menus', 'locale')) {
            Schema::table('cms_menus', function (Blueprint $table): void {
                $table->dropUnique('cms_menus_slug_unique');
                $table->dropUnique('cms_menus_location_unique');
                $table->string('locale', 12)->default(config('app.locale', 'en'))->after('location');
                $table->unique(['locale', 'slug']);
                $table->unique(['locale', 'location']);
            });
        }

        Schema::create('cms_menu_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_menu_id')->constrained('cms_menus')->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();

            $table->unique(['cms_menu_id', 'locale']);
            $table->index('locale');
        });

        Schema::create('cms_menu_item_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_menu_item_id')->constrained('cms_menu_items')->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('label')->nullable();
            $table->string('url', 2048)->nullable();
            $table->timestamps();

            $table->unique(['cms_menu_item_id', 'locale'], 'cms_menu_item_translations_item_locale_unique');
            $table->index('locale');
        });
    }
};
