<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_languages', function (Blueprint $table): void {
            if (! Schema::hasColumn('cms_languages', 'flag_media_asset_id')) {
                $table->unsignedBigInteger('flag_media_asset_id')->nullable()->after('native_name');
                $table->index('flag_media_asset_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cms_languages', function (Blueprint $table): void {
            if (Schema::hasColumn('cms_languages', 'flag_media_asset_id')) {
                $table->dropIndex(['flag_media_asset_id']);
                $table->dropColumn('flag_media_asset_id');
            }
        });
    }
};
