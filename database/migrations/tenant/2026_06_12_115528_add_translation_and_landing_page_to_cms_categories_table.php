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
        $hasTranslationKey = Schema::hasColumn('cms_categories', 'translation_key');
        $hasTranslatedFromCategoryId = Schema::hasColumn('cms_categories', 'translated_from_category_id');
        $hasLandingPageId = Schema::hasColumn('cms_categories', 'landing_page_id');

        if (! $hasTranslationKey || ! $hasTranslatedFromCategoryId || ! $hasLandingPageId) {
            Schema::table('cms_categories', function (Blueprint $table) use (
                $hasTranslationKey,
                $hasTranslatedFromCategoryId,
                $hasLandingPageId,
            ): void {
                if (! $hasTranslationKey) {
                    $table->string('translation_key', 64)->nullable()->after('locale')->index();
                }

                if (! $hasTranslatedFromCategoryId) {
                    $table->foreignId('translated_from_category_id')
                        ->nullable()
                        ->after('translation_key')
                        ->constrained('cms_categories')
                        ->nullOnDelete();
                }

                if (! $hasLandingPageId) {
                    $table->foreignId('landing_page_id')
                        ->nullable()
                        ->after('translated_from_category_id')
                        ->constrained('cms_pages')
                        ->nullOnDelete();
                }
            });
        }

        DB::table('cms_categories')
            ->whereNull('translation_key')
            ->orderBy('id')
            ->select(['id'])
            ->cursor()
            ->each(function (object $category): void {
                DB::table('cms_categories')
                    ->where('id', $category->id)
                    ->update(['translation_key' => (string) Str::ulid()]);
            });

        if (! Schema::hasIndex('cms_categories', 'cms_categories_type_translation_locale_unique')) {
            Schema::table('cms_categories', function (Blueprint $table): void {
                $table->unique(['type', 'translation_key', 'locale'], 'cms_categories_type_translation_locale_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('cms_categories', 'cms_categories_type_translation_locale_unique')) {
            Schema::table('cms_categories', function (Blueprint $table): void {
                $table->dropUnique('cms_categories_type_translation_locale_unique');
            });
        }

        Schema::table('cms_categories', function (Blueprint $table): void {
            if (Schema::hasColumn('cms_categories', 'landing_page_id')) {
                $table->dropForeign(['landing_page_id']);
                $table->dropColumn('landing_page_id');
            }

            if (Schema::hasColumn('cms_categories', 'translated_from_category_id')) {
                $table->dropForeign(['translated_from_category_id']);
                $table->dropColumn('translated_from_category_id');
            }

            if (Schema::hasColumn('cms_categories', 'translation_key')) {
                $table->dropColumn('translation_key');
            }
        });
    }
};
