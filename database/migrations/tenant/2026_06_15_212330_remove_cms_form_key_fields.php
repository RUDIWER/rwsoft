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
        $this->normalizeFormTranslationKeys();
        $this->normalizeFieldTranslationKeys();
        $this->migrateLegacyFormBlockKeys();
        $this->snapshotSubmissionValueLabels();

        if (Schema::hasColumn('cms_form_submission_values', 'field_key')) {
            Schema::table('cms_form_submission_values', function (Blueprint $table): void {
                if (Schema::hasIndex('cms_form_submission_values', 'cms_submission_values_submission_key_index')) {
                    $table->dropIndex('cms_submission_values_submission_key_index');
                }

                $table->dropColumn('field_key');
            });
        }

        if (Schema::hasColumn('cms_form_fields', 'key')) {
            if (Schema::hasIndex('cms_form_fields', 'cms_form_fields_cms_form_id_key_unique')) {
                Schema::table('cms_form_fields', function (Blueprint $table): void {
                    $table->dropUnique('cms_form_fields_cms_form_id_key_unique');
                });
            }

            Schema::table('cms_form_fields', function (Blueprint $table): void {
                $table->dropColumn('key');
            });
        }

        if (Schema::hasColumn('cms_forms', 'key')) {
            if (Schema::hasIndex('cms_forms', 'cms_forms_locale_key_unique')) {
                Schema::table('cms_forms', function (Blueprint $table): void {
                    $table->dropUnique('cms_forms_locale_key_unique');
                });
            }

            if (Schema::hasIndex('cms_forms', 'cms_forms_key_unique')) {
                Schema::table('cms_forms', function (Blueprint $table): void {
                    $table->dropUnique('cms_forms_key_unique');
                });
            }

            Schema::table('cms_forms', function (Blueprint $table): void {
                $table->dropColumn('key');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('cms_forms', 'key')) {
            Schema::table('cms_forms', function (Blueprint $table): void {
                $table->string('key')->nullable()->after('id');
            });

            DB::table('cms_forms')
                ->orderBy('id')
                ->each(function (object $form): void {
                    DB::table('cms_forms')
                        ->where('id', $form->id)
                        ->update(['key' => 'form_'.$form->id]);
                });
        }

        if (! Schema::hasColumn('cms_form_fields', 'key')) {
            Schema::table('cms_form_fields', function (Blueprint $table): void {
                $table->string('key')->nullable()->after('type');
            });

            DB::table('cms_form_fields')
                ->orderBy('id')
                ->each(function (object $field): void {
                    DB::table('cms_form_fields')
                        ->where('id', $field->id)
                        ->update(['key' => 'field_'.$field->id]);
                });
        }

        if (! Schema::hasColumn('cms_form_submission_values', 'field_key')) {
            Schema::table('cms_form_submission_values', function (Blueprint $table): void {
                $table->string('field_key')->nullable()->after('cms_form_field_id');
            });
        }
    }

    private function normalizeFormTranslationKeys(): void
    {
        if (! Schema::hasColumn('cms_forms', 'key')) {
            return;
        }

        DB::table('cms_forms')
            ->select(['key'])
            ->whereNotNull('key')
            ->groupBy('key')
            ->orderBy('key')
            ->each(function (object $row): void {
                $legacyKey = (string) $row->key;
                $translationKey = (string) DB::table('cms_forms')
                    ->where('key', $legacyKey)
                    ->whereNotNull('translation_key')
                    ->orderBy('id')
                    ->value('translation_key');

                if ($translationKey === '') {
                    $translationKey = (string) Str::ulid();
                }

                DB::table('cms_forms')
                    ->where('key', $legacyKey)
                    ->update(['translation_key' => $translationKey]);
            });
    }

    private function normalizeFieldTranslationKeys(): void
    {
        if (! Schema::hasColumn('cms_form_fields', 'key')) {
            return;
        }

        DB::table('cms_form_fields')
            ->join('cms_forms', 'cms_forms.id', '=', 'cms_form_fields.cms_form_id')
            ->select(['cms_forms.translation_key as form_translation_key', 'cms_form_fields.key'])
            ->whereNotNull('cms_form_fields.key')
            ->groupBy('cms_forms.translation_key', 'cms_form_fields.key')
            ->orderBy('cms_forms.translation_key')
            ->orderBy('cms_form_fields.key')
            ->each(function (object $row): void {
                $formTranslationKey = (string) $row->form_translation_key;
                $legacyFieldKey = (string) $row->key;
                $fieldTranslationKey = (string) DB::table('cms_form_fields')
                    ->join('cms_forms', 'cms_forms.id', '=', 'cms_form_fields.cms_form_id')
                    ->where('cms_forms.translation_key', $formTranslationKey)
                    ->where('cms_form_fields.key', $legacyFieldKey)
                    ->whereNotNull('cms_form_fields.translation_key')
                    ->orderBy('cms_form_fields.id')
                    ->value('cms_form_fields.translation_key');

                if ($fieldTranslationKey === '') {
                    $fieldTranslationKey = (string) Str::ulid();
                }

                DB::table('cms_form_fields')
                    ->join('cms_forms', 'cms_forms.id', '=', 'cms_form_fields.cms_form_id')
                    ->where('cms_forms.translation_key', $formTranslationKey)
                    ->where('cms_form_fields.key', $legacyFieldKey)
                    ->update(['cms_form_fields.translation_key' => $fieldTranslationKey]);
            });
    }

    private function migrateLegacyFormBlockKeys(): void
    {
        if (! Schema::hasColumn('cms_forms', 'key')) {
            return;
        }

        $formTranslationKeys = DB::table('cms_forms')
            ->whereNotNull('key')
            ->whereNotNull('translation_key')
            ->orderBy('id')
            ->get(['key', 'translation_key'])
            ->mapWithKeys(fn (object $form): array => [(string) $form->key => (string) $form->translation_key])
            ->all();

        $this->migrateJsonColumnBlocks('cms_pages', 'content_blocks', $formTranslationKeys);
        $this->migrateJsonColumnBlocks('cms_posts', 'content_blocks', $formTranslationKeys);
        $this->migrateCmsBlockContent($formTranslationKeys);
        $this->migrateJsonColumnBlocks('cms_block_overrides', 'content', $formTranslationKeys);
        $this->migrateJsonColumnBlocks('cms_revisions', 'snapshot', $formTranslationKeys);
    }

    /**
     * @param  array<string, string>  $formTranslationKeys
     */
    private function migrateCmsBlockContent(array $formTranslationKeys): void
    {
        if (! Schema::hasTable('cms_blocks') || ! Schema::hasColumn('cms_blocks', 'content')) {
            return;
        }

        DB::table('cms_blocks')
            ->select(['id', 'type', 'content'])
            ->whereNotNull('content')
            ->orderBy('id')
            ->each(function (object $row) use ($formTranslationKeys): void {
                $decoded = json_decode((string) $row->content, true);

                if (! is_array($decoded)) {
                    return;
                }

                $decoded['type'] ??= (string) $row->type;

                [$migrated, $changed] = $this->migrateFormBlockReferences($decoded, $formTranslationKeys);

                unset($migrated['type']);

                if (! $changed) {
                    return;
                }

                DB::table('cms_blocks')
                    ->where('id', $row->id)
                    ->update(['content' => json_encode($migrated)]);
            });
    }

    /**
     * @param  array<string, string>  $formTranslationKeys
     */
    private function migrateJsonColumnBlocks(string $table, string $column, array $formTranslationKeys): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->select(['id', $column])
            ->whereNotNull($column)
            ->orderBy('id')
            ->each(function (object $row) use ($table, $column, $formTranslationKeys): void {
                $decoded = json_decode((string) $row->{$column}, true);

                if (! is_array($decoded)) {
                    return;
                }

                [$migrated, $changed] = $this->migrateFormBlockReferences($decoded, $formTranslationKeys);

                if (! $changed) {
                    return;
                }

                DB::table($table)
                    ->where('id', $row->id)
                    ->update([$column => json_encode($migrated)]);
            });
    }

    /**
     * @param  array<string|int, mixed>  $value
     * @param  array<string, string>  $formTranslationKeys
     * @return array{0: array<string|int, mixed>, 1: bool}
     */
    private function migrateFormBlockReferences(array $value, array $formTranslationKeys): array
    {
        $changed = false;

        if (($value['type'] ?? null) === 'form' && array_key_exists('form_key', $value)) {
            $legacyKey = (string) ($value['form_key'] ?? '');
            $translationKey = $formTranslationKeys[$legacyKey] ?? null;

            unset($value['form_key']);

            if ($translationKey !== null && $translationKey !== '') {
                $value['form_translation_key'] = $translationKey;
            }

            $changed = true;
        }

        foreach ($value as $key => $child) {
            if (! is_array($child)) {
                continue;
            }

            [$value[$key], $childChanged] = $this->migrateFormBlockReferences($child, $formTranslationKeys);
            $changed = $changed || $childChanged;
        }

        return [$value, $changed];
    }

    private function snapshotSubmissionValueLabels(): void
    {
        if (! Schema::hasColumn('cms_form_submission_values', 'field_label_snapshot')) {
            Schema::table('cms_form_submission_values', function (Blueprint $table): void {
                $table->string('field_label_snapshot')->nullable()->after('field_translation_key');
            });
        }

        DB::table('cms_form_submission_values')
            ->leftJoin('cms_form_fields', 'cms_form_fields.id', '=', 'cms_form_submission_values.cms_form_field_id')
            ->whereNull('cms_form_submission_values.field_label_snapshot')
            ->update(['cms_form_submission_values.field_label_snapshot' => DB::raw('cms_form_fields.label')]);
    }
};
