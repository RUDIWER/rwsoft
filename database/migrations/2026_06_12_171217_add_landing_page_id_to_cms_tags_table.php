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
        Schema::table('cms_tags', function (Blueprint $table) {
            $table->foreignId('landing_page_id')
                ->nullable()
                ->after('locale')
                ->constrained('cms_pages')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('landing_page_id');
        });
    }
};
