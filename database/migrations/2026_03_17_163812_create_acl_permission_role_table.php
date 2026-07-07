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
        if (Schema::hasTable('acl_permission_role')) {
            Schema::table('acl_permission_role', function (Blueprint $table) {
                if (! Schema::hasColumn('acl_permission_role', 'acl_role_id')) {
                    $table->unsignedBigInteger('acl_role_id')->nullable();
                }

                if (! Schema::hasColumn('acl_permission_role', 'acl_permission_id')) {
                    $table->unsignedBigInteger('acl_permission_id')->nullable();
                }

                if (! Schema::hasColumn('acl_permission_role', 'active')) {
                    $table->boolean('active')->default(true);
                }
            });

            return;
        }

        Schema::create('acl_permission_role', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('acl_role_id');
            $table->unsignedBigInteger('acl_permission_id');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['acl_role_id', 'acl_permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acl_permission_role');
    }
};
