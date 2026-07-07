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
        Schema::table('cms_media_assets', function (Blueprint $table): void {
            $table->string('hash', 64)->nullable()->after('height')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_media_assets', function (Blueprint $table): void {
            $table->dropUnique(['hash']);
            $table->dropColumn('hash');
        });
    }
};
