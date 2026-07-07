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
        if (! Schema::hasTable('cms_pages') || ! Schema::hasColumn('cms_pages', 'layout_id')) {
            return;
        }

        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('layout_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('cms_pages') || Schema::hasColumn('cms_pages', 'layout_id')) {
            return;
        }

        Schema::table('cms_pages', function (Blueprint $table) {
            $table->foreignId('layout_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('cms_layouts')
                ->nullOnDelete();
        });
    }
};
