<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('database_logs')) {
            return;
        }

        Schema::create('database_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('project_name', 120);
            $table->string('filename')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->json('selected_tables');
            $table->integer('file_size_kb')->nullable();
            $table->json('log_details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_logs');
    }
};
