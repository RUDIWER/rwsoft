<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chart_groups')) {
            return;
        }

        Schema::create('chart_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(10)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_groups');
    }
};
