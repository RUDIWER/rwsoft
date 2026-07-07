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
        Schema::table('platform_mail_transports', function (Blueprint $table): void {
            $table->json('provider_config')->nullable()->after('encrypted_secret');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_mail_transports', function (Blueprint $table): void {
            $table->dropColumn('provider_config');
        });
    }
};
