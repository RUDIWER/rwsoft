<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_themes', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('author')->nullable();
            $table->string('version', 40)->default('1.0.0');
            $table->string('status', 24)->default('draft')->index();
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('active_version_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('cms_theme_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_theme_id')->constrained('cms_themes')->cascadeOnDelete();
            $table->string('version_hash', 64)->index();
            $table->string('developer_css_path');
            $table->string('generated_css_path')->nullable();
            $table->string('minified_css_path');
            $table->json('settings')->nullable();
            $table->json('source_manifest')->nullable();
            $table->json('external_assets')->nullable();
            $table->unsignedInteger('file_size_kb')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['cms_theme_id', 'version_hash']);
        });

        Schema::table('cms_themes', function (Blueprint $table): void {
            $table->foreign('active_version_id')
                ->references('id')
                ->on('cms_theme_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cms_themes', function (Blueprint $table): void {
            $table->dropForeign(['active_version_id']);
        });

        Schema::dropIfExists('cms_theme_versions');
        Schema::dropIfExists('cms_themes');
    }
};
