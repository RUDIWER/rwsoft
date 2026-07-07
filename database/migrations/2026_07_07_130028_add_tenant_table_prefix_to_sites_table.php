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
            $table->dropUnique('sites_tenant_database_unique');
            $table->index('tenant_database');
            $table->string('tenant_table_prefix', 48)->nullable()->unique()->after('tenant_database');
            $table->string('tenant_database_mode', 32)->default('separate')->after('tenant_table_prefix');
            $table->string('tenant_provisioning_mode', 32)->default('create_database')->after('tenant_database_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropUnique('sites_tenant_table_prefix_unique');
            $table->dropIndex('sites_tenant_database_index');
            $table->dropColumn(['tenant_provisioning_mode', 'tenant_database_mode']);
            $table->dropColumn('tenant_table_prefix');
            $table->unique('tenant_database');
        });
    }
};
