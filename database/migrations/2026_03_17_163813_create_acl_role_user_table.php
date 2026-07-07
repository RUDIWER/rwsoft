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
        if (Schema::hasTable('acl_role_user')) {
            Schema::table('acl_role_user', function (Blueprint $table) {
                if (! Schema::hasColumn('acl_role_user', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
                }

                if (! Schema::hasColumn('acl_role_user', 'acl_role_id')) {
                    $table->foreignId('acl_role_id')->nullable()->constrained('acl_roles')->cascadeOnDelete();
                }
            });

            return;
        }

        Schema::create('acl_role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('acl_role_id')->constrained('acl_roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'acl_role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acl_role_user');
    }
};
