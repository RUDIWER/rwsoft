<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_posts', function (Blueprint $table): void {
            $table->string('translation_key', 32)->nullable()->after('locale');
            $table->foreignId('translated_from_post_id')
                ->nullable()
                ->after('translation_key')
                ->constrained('cms_posts')
                ->nullOnDelete();
        });

        DB::table('cms_posts')
            ->whereNull('translation_key')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $post): void {
                DB::table('cms_posts')
                    ->where('id', $post->id)
                    ->update(['translation_key' => (string) Str::ulid()]);
            });

        Schema::table('cms_posts', function (Blueprint $table): void {
            $table->unique(['translation_key', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::table('cms_posts', function (Blueprint $table): void {
            $table->dropUnique(['translation_key', 'locale']);
            $table->dropConstrainedForeignId('translated_from_post_id');
            $table->dropColumn('translation_key');
        });
    }
};
