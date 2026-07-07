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
            if (! Schema::hasColumn('site_user_memberships', 'admin_locale')) {
                $table->string('admin_locale', 12)->nullable()->after('last_accessed_at')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_user_memberships', function (Blueprint $table): void {
            if (Schema::hasColumn('site_user_memberships', 'admin_locale')) {
                $table->dropIndex(['admin_locale']);
                $table->dropColumn('admin_locale');
            }
        });
    }
};
