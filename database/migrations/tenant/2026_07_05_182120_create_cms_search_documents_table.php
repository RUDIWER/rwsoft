<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cms_search_documents', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 64);
            $table->string('source_key', 255);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('locale', 16)->index();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('summary')->nullable();
            $table->string('canonical_path')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('markdown_path')->nullable();
            $table->string('markdown_url')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_searchable')->default(true);
            $table->boolean('noindex')->default(false);
            $table->string('markdown_hash', 64)->nullable();
            $table->string('plain_text_hash', 64)->nullable();
            $table->longText('markdown');
            $table->longText('plain_text');
            $table->json('metadata')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_key', 'locale'], 'cms_search_documents_source_unique');
            $table->index(['source_type', 'source_id'], 'cms_search_documents_source_id_index');
            $table->index(['locale', 'source_type', 'is_active', 'is_searchable', 'noindex'], 'cms_search_documents_public_index');
        });

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('cms_search_documents', function (Blueprint $table) {
                $table->fullText(['title', 'summary', 'plain_text'], 'cms_search_documents_fulltext');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_search_documents');
    }
};
