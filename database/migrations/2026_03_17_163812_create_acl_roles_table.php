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
        if (Schema::hasTable('acl_roles')) {
            Schema::table('acl_roles', function (Blueprint $table) {
                if (! Schema::hasColumn('acl_roles', 'key')) {
                    $table->string('key')->nullable();
                }

                if (! Schema::hasColumn('acl_roles', 'name')) {
                    $table->string('name')->nullable();
                }

                if (! Schema::hasColumn('acl_roles', 'description')) {
                    $table->string('description')->nullable();
                }
            });

            return;
        }

        Schema::create('acl_roles', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acl_roles');
    }
};
