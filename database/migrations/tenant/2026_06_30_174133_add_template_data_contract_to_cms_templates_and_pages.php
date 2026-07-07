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
        if (Schema::hasTable('cms_templates') && ! Schema::hasColumn('cms_templates', 'data_contract')) {
            Schema::table('cms_templates', function (Blueprint $table): void {
                $table->json('data_contract')->nullable()->after('settings');
            });
        }

        if (Schema::hasTable('cms_pages') && ! Schema::hasColumn('cms_pages', 'template_data')) {
            Schema::table('cms_pages', function (Blueprint $table): void {
                $table->json('template_data')->nullable()->after('content_blocks');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cms_pages') && Schema::hasColumn('cms_pages', 'template_data')) {
            Schema::table('cms_pages', function (Blueprint $table): void {
                $table->dropColumn('template_data');
            });
        }

        if (Schema::hasTable('cms_templates') && Schema::hasColumn('cms_templates', 'data_contract')) {
            Schema::table('cms_templates', function (Blueprint $table): void {
                $table->dropColumn('data_contract');
            });
        }
    }
};
