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
        Schema::table('cms_tags', function (Blueprint $table) {
            $table->string('translation_key', 64)->nullable()->after('locale')->index();
            $table->foreignId('translated_from_tag_id')
                ->nullable()
                ->after('translation_key')
                ->constrained('cms_tags')
                ->nullOnDelete();
        });

        DB::table('cms_tags')
            ->whereNull('translation_key')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(100, function ($tags): void {
                foreach ($tags as $tag) {
                    DB::table('cms_tags')
                        ->where('id', $tag->id)
                        ->update(['translation_key' => (string) Str::ulid()]);
                }
            });

        Schema::table('cms_tags', function (Blueprint $table) {
            $table->unique(['translation_key', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_tags', function (Blueprint $table) {
            $table->dropUnique(['translation_key', 'locale']);
            $table->dropConstrainedForeignId('translated_from_tag_id');
            $table->dropColumn('translation_key');
        });
    }
};
