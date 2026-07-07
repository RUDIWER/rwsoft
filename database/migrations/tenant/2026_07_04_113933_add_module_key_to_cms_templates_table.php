<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cms_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('cms_templates', 'module_key')) {
                $table->string('module_key', 64)->nullable()->after('template_key')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('cms_templates', 'module_key')) {
                $table->dropColumn('module_key');
            }
        });
    }
};
