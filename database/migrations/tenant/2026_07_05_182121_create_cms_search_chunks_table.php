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
        Schema::create('cms_search_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_search_document_id')
                ->constrained('cms_search_documents')
                ->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->string('heading')->nullable();
            $table->string('anchor')->nullable();
            $table->longText('content_markdown');
            $table->longText('content_text');
            $table->unsignedInteger('token_count')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['cms_search_document_id', 'chunk_index'], 'cms_search_chunks_document_chunk_unique');
            $table->index(['cms_search_document_id', 'chunk_index'], 'cms_search_chunks_document_index');
        });

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('cms_search_chunks', function (Blueprint $table) {
                $table->fullText(['heading', 'content_text'], 'cms_search_chunks_fulltext');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_search_chunks');
    }
};
