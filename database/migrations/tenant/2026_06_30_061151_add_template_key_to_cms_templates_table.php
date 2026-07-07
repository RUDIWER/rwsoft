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
        Schema::table('cms_templates', function (Blueprint $table) {
            $table->string('template_key', 64)->nullable()->after('template_class')->index();
        });

        DB::table('cms_templates')
            ->whereNull('template_key')
            ->update(['template_key' => DB::raw("CONCAT(template_class, '.', purpose)")]);

        Schema::table('cms_templates', function (Blueprint $table) {
            $table->dropIndex('cms_templates_default_lookup_index');
            $table->dropIndex('cms_templates_active_lookup_index');
            $table->dropIndex(['purpose']);

            $table->dropColumn('purpose');

            $table->index(['template_key', 'locale', 'is_default'], 'cms_templates_default_lookup_index');
            $table->index(['template_key', 'locale', 'is_active'], 'cms_templates_active_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_templates', function (Blueprint $table) {
            $table->string('purpose', 32)->nullable()->after('template_key')->index();
        });

        DB::table('cms_templates')
            ->whereNull('purpose')
            ->update(['purpose' => DB::raw("SUBSTRING_INDEX(template_key, '.', -1)")]);

        Schema::table('cms_templates', function (Blueprint $table) {
            $table->dropIndex('cms_templates_default_lookup_index');
            $table->dropIndex('cms_templates_active_lookup_index');
            $table->dropIndex(['template_key']);

            $table->dropColumn('template_key');

            $table->index(['template_class', 'purpose', 'locale', 'is_default'], 'cms_templates_default_lookup_index');
            $table->index(['template_class', 'purpose', 'locale', 'is_active'], 'cms_templates_active_lookup_index');
        });
    }
};
