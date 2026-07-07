<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rw_table_exports')) {
            return;
        }

        Schema::create('rw_table_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('table_identifier')->index();
            $table->string('description');
            $table->json('config');
            $table->timestamps();

            $table->unique(['user_id', 'table_identifier', 'description'], 'rw_table_exports_unique_user_table_desc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rw_table_exports');
    }
};
