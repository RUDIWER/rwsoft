<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasColumn('cms_forms', 'form_kind')) {
            Schema::connection($this->connection)->table('cms_forms', function (Blueprint $table): void {
                $table->string('form_kind', 32)->default('normal')->after('translated_from_form_id')->index();
            });
        }

        if (! Schema::connection($this->connection)->hasColumn('cms_forms', 'system_key')) {
            Schema::connection($this->connection)->table('cms_forms', function (Blueprint $table): void {
                $table->string('system_key', 80)->nullable()->after('form_kind')->index();
            });
        }

        if (! Schema::connection($this->connection)->hasIndex('cms_forms', 'cms_forms_system_key_locale_unique')) {
            Schema::connection($this->connection)->table('cms_forms', function (Blueprint $table): void {
                $table->unique(['system_key', 'locale'], 'cms_forms_system_key_locale_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection($this->connection)->hasIndex('cms_forms', 'cms_forms_system_key_locale_unique')) {
            Schema::connection($this->connection)->table('cms_forms', function (Blueprint $table): void {
                $table->dropUnique('cms_forms_system_key_locale_unique');
            });
        }

        $columns = array_values(array_filter(
            ['system_key', 'form_kind'],
            fn (string $column): bool => Schema::connection($this->connection)->hasColumn('cms_forms', $column),
        ));

        if ($columns !== []) {
            Schema::connection($this->connection)->table('cms_forms', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
