<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('query_builder_select_tables')) {
            return;
        }

        Schema::create('query_builder_select_tables', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('table_name', 160);
            $table->string('select_field', 160)->default('id');
            $table->json('label_fields');
            $table->json('search_fields')->nullable();
            $table->json('default_filters')->nullable();
            $table->json('default_sort')->nullable();
            $table->unsignedInteger('sort_order')->default(10)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_builder_select_tables');
    }
};
