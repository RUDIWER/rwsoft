<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_public_texts')) {
            Schema::create('cms_public_texts', function (Blueprint $table): void {
                $table->id();
                $table->string('group')->index();
                $table->string('key');
                $table->string('label');
                $table->text('description')->nullable();
                $table->text('default_value')->nullable();
                $table->string('type', 32)->default('text');
                $table->boolean('is_system')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();

                $table->unique(['group', 'key']);
            });
        }

        if (! Schema::hasTable('cms_public_text_translations')) {
            Schema::create('cms_public_text_translations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_public_text_id')->constrained('cms_public_texts')->cascadeOnDelete();
                $table->string('locale', 12)->index();
                $table->text('value')->nullable();
                $table->timestamps();

                $table->unique(['cms_public_text_id', 'locale'], 'cms_public_text_translations_text_locale_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_public_text_translations');
        Schema::dropIfExists('cms_public_texts');
    }
};
