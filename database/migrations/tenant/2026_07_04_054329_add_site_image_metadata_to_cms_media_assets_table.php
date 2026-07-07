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
            if (! Schema::hasColumn('cms_media_assets', 'asset_kind')) {
                $table->string('asset_kind', 40)->default('library')->after('visibility')->index();
            }

            if (! Schema::hasColumn('cms_media_assets', 'source_media_asset_id')) {
                $table->unsignedBigInteger('source_media_asset_id')->nullable()->after('asset_kind')->index();
            }

            if (! Schema::hasColumn('cms_media_assets', 'context_type')) {
                $table->string('context_type', 40)->nullable()->after('source_media_asset_id')->index();
            }

            if (! Schema::hasColumn('cms_media_assets', 'context_id')) {
                $table->unsignedBigInteger('context_id')->nullable()->after('context_type')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_media_assets', function (Blueprint $table): void {
            if (Schema::hasColumn('cms_media_assets', 'context_id')) {
                $table->dropIndex(['context_id']);
                $table->dropColumn('context_id');
            }

            if (Schema::hasColumn('cms_media_assets', 'context_type')) {
                $table->dropIndex(['context_type']);
                $table->dropColumn('context_type');
            }

            if (Schema::hasColumn('cms_media_assets', 'source_media_asset_id')) {
                $table->dropIndex(['source_media_asset_id']);
                $table->dropColumn('source_media_asset_id');
            }

            if (Schema::hasColumn('cms_media_assets', 'asset_kind')) {
                $table->dropIndex(['asset_kind']);
                $table->dropColumn('asset_kind');
            }
        });
    }
};
