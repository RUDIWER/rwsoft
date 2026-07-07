<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasFormTranslationKey = Schema::hasColumn('cms_forms', 'translation_key');
        $hasTranslatedFromFormId = Schema::hasColumn('cms_forms', 'translated_from_form_id');

        if (Schema::hasIndex('cms_forms', 'cms_forms_key_unique')) {
            Schema::table('cms_forms', function (Blueprint $table): void {
                $table->dropUnique(['key']);
            });
        }

        if (! $hasFormTranslationKey || ! $hasTranslatedFromFormId) {
            Schema::table('cms_forms', function (Blueprint $table) use ($hasFormTranslationKey, $hasTranslatedFromFormId): void {
                if (! $hasFormTranslationKey) {
                    $table->string('translation_key', 32)->nullable()->after('locale')->index();
                }

                if (! $hasTranslatedFromFormId) {
                    $table->foreignId('translated_from_form_id')
                        ->nullable()
                        ->after('translation_key')
                        ->constrained('cms_forms')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasColumn('cms_forms', 'key') && ! Schema::hasIndex('cms_forms', 'cms_forms_locale_key_unique')) {
            Schema::table('cms_forms', function (Blueprint $table): void {
                $table->unique(['locale', 'key']);
            });
        }

        if (! Schema::hasIndex('cms_forms', 'cms_forms_translation_key_locale_unique')) {
            Schema::table('cms_forms', function (Blueprint $table): void {
                $table->unique(['translation_key', 'locale']);
            });
        }

        $hasFieldTranslationKey = Schema::hasColumn('cms_form_fields', 'translation_key');
        $hasTranslatedFromFormFieldId = Schema::hasColumn('cms_form_fields', 'translated_from_form_field_id');
        $fieldTranslationAfterColumn = Schema::hasColumn('cms_form_fields', 'key') ? 'key' : 'type';

        if (! $hasFieldTranslationKey || ! $hasTranslatedFromFormFieldId) {
            Schema::table('cms_form_fields', function (Blueprint $table) use ($fieldTranslationAfterColumn, $hasFieldTranslationKey, $hasTranslatedFromFormFieldId): void {
                if (! $hasFieldTranslationKey) {
                    $table->string('translation_key', 32)->nullable()->after($fieldTranslationAfterColumn)->index();
                }

                if (! $hasTranslatedFromFormFieldId) {
                    $table->foreignId('translated_from_form_field_id')
                        ->nullable()
                        ->after('translation_key')
                        ->constrained('cms_form_fields')
                        ->nullOnDelete();
                }
            });
        }

        $hasSubmissionLocale = Schema::hasColumn('cms_form_submissions', 'locale');
        $hasFormTranslationKey = Schema::hasColumn('cms_form_submissions', 'form_translation_key');

        if (! $hasSubmissionLocale || ! $hasFormTranslationKey) {
            Schema::table('cms_form_submissions', function (Blueprint $table) use ($hasSubmissionLocale, $hasFormTranslationKey): void {
                if (! $hasSubmissionLocale) {
                    $table->string('locale', 12)->nullable()->after('cms_page_id')->index();
                }

                if (! $hasFormTranslationKey) {
                    $table->string('form_translation_key', 32)->nullable()->after('locale')->index();
                }
            });
        }

        if (! Schema::hasColumn('cms_form_submission_values', 'field_translation_key')) {
            $fieldTranslationAfterColumn = Schema::hasColumn('cms_form_submission_values', 'field_key') ? 'field_key' : 'cms_form_field_id';

            Schema::table('cms_form_submission_values', function (Blueprint $table) use ($fieldTranslationAfterColumn): void {
                $table->string('field_translation_key', 32)->nullable()->after($fieldTranslationAfterColumn)->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cms_form_submission_values', 'field_translation_key')) {
            Schema::table('cms_form_submission_values', function (Blueprint $table): void {
                $table->dropIndex(['field_translation_key']);
                $table->dropColumn('field_translation_key');
            });
        }

        Schema::table('cms_form_submissions', function (Blueprint $table): void {
            if (Schema::hasColumn('cms_form_submissions', 'locale')) {
                $table->dropIndex(['locale']);
                $table->dropColumn('locale');
            }

            if (Schema::hasColumn('cms_form_submissions', 'form_translation_key')) {
                $table->dropIndex(['form_translation_key']);
                $table->dropColumn('form_translation_key');
            }
        });

        Schema::table('cms_form_fields', function (Blueprint $table): void {
            if (Schema::hasColumn('cms_form_fields', 'translated_from_form_field_id')) {
                $table->dropConstrainedForeignId('translated_from_form_field_id');
            }

            if (Schema::hasColumn('cms_form_fields', 'translation_key')) {
                $table->dropIndex(['translation_key']);
                $table->dropColumn('translation_key');
            }
        });

        if (Schema::hasIndex('cms_forms', 'cms_forms_translation_key_locale_unique')) {
            Schema::table('cms_forms', function (Blueprint $table): void {
                $table->dropUnique(['translation_key', 'locale']);
            });
        }

        if (Schema::hasColumn('cms_forms', 'key') && Schema::hasIndex('cms_forms', 'cms_forms_locale_key_unique')) {
            Schema::table('cms_forms', function (Blueprint $table): void {
                $table->dropUnique(['locale', 'key']);
            });
        }

        Schema::table('cms_forms', function (Blueprint $table): void {
            if (Schema::hasColumn('cms_forms', 'translated_from_form_id')) {
                $table->dropConstrainedForeignId('translated_from_form_id');
            }

            if (Schema::hasColumn('cms_forms', 'translation_key')) {
                $table->dropIndex(['translation_key']);
                $table->dropColumn('translation_key');
            }
        });

        if (Schema::hasColumn('cms_forms', 'key') && ! Schema::hasIndex('cms_forms', 'cms_forms_key_unique')) {
            Schema::table('cms_forms', function (Blueprint $table): void {
                $table->unique('key');
            });
        }
    }
};
