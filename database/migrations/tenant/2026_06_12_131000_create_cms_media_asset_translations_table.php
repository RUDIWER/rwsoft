<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_media_asset_translations')) {
            return;
        }

        Schema::create('cms_media_asset_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_media_asset_id')->constrained('cms_media_assets')->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->timestamps();

            $table->unique(['cms_media_asset_id', 'locale'], 'cms_media_asset_translations_asset_locale_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_media_asset_translations');
    }
};
