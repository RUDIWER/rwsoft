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
        Schema::table('sites', function (Blueprint $table): void {
            $table->text('tenant_database_url')->nullable()->after('tenant_provisioning_mode');
            $table->string('tenant_database_host', 255)->nullable()->after('tenant_database_url');
            $table->unsignedInteger('tenant_database_port')->nullable()->after('tenant_database_host');
            $table->string('tenant_database_username', 160)->nullable()->after('tenant_database_port');
            $table->text('tenant_database_password')->nullable()->after('tenant_database_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn([
                'tenant_database_password',
                'tenant_database_username',
                'tenant_database_port',
                'tenant_database_host',
                'tenant_database_url',
            ]);
        });
    }
};
