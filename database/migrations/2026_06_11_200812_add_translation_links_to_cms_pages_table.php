<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->string('translation_key', 32)->nullable()->after('locale');
            $table->foreignId('translated_from_page_id')
                ->nullable()
                ->after('translation_key')
                ->constrained('cms_pages')
                ->nullOnDelete();
        });

        DB::table('cms_pages')
            ->whereNull('translation_key')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $page): void {
                DB::table('cms_pages')
                    ->where('id', $page->id)
                    ->update(['translation_key' => (string) Str::ulid()]);
            });

        Schema::table('cms_pages', function (Blueprint $table) {
            $table->unique(['translation_key', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropUnique(['translation_key', 'locale']);
            $table->dropConstrainedForeignId('translated_from_page_id');
            $table->dropColumn('translation_key');
        });
    }
};
