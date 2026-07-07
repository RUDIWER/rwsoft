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
        Schema::table('site_user_memberships', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_user_memberships', 'allowed_content_locales')) {
                $table->json('allowed_content_locales')->nullable()->after('admin_locale');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_user_memberships', function (Blueprint $table): void {
            if (Schema::hasColumn('site_user_memberships', 'allowed_content_locales')) {
                $table->dropColumn('allowed_content_locales');
            }
        });
    }
};
