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
        if (Schema::hasTable('acl_permissions')) {
            Schema::table('acl_permissions', function (Blueprint $table) {
                if (! Schema::hasColumn('acl_permissions', 'route_name')) {
                    $table->string('route_name')->nullable();
                }

                if (! Schema::hasColumn('acl_permissions', 'description')) {
                    $table->string('description')->nullable();
                }

                if (! Schema::hasColumn('acl_permissions', 'module')) {
                    $table->string('module')->nullable();
                }

                if (! Schema::hasColumn('acl_permissions', 'action')) {
                    $table->string('action')->nullable();
                }

                if (! Schema::hasColumn('acl_permissions', 'type')) {
                    $table->string('type')->nullable();
                }

                if (! Schema::hasColumn('acl_permissions', 'query_id')) {
                    $table->unsignedBigInteger('query_id')->nullable();
                }

                if (! Schema::hasColumn('acl_permissions', 'menu')) {
                    $table->boolean('menu')->default(false);
                }

                if (! Schema::hasColumn('acl_permissions', 'url')) {
                    $table->string('url')->nullable();
                }
            });

            return;
        }

        Schema::create('acl_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('route_name')->unique();
            $table->string('description');
            $table->string('module')->nullable();
            $table->string('action')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('query_id')->nullable();
            $table->boolean('menu')->default(false);
            $table->string('url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acl_permissions');
    }
};
