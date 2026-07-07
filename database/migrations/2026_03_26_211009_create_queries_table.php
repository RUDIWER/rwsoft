<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('queries')) {
            return;
        }

        Schema::create('queries', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('description', 255);
            $table->text('memo')->nullable();
            $table->string('query_mode', 20)->default('sql')->index();
            $table->string('output_mode', 20)->default('table')->index();

            $table->string('table_name', 160)->nullable();
            $table->longText('query')->nullable();
            $table->longText('test_query')->nullable();

            $table->json('selected_fields')->nullable();
            $table->json('join_rows')->nullable();
            $table->json('where_rows')->nullable();
            $table->json('group_rows')->nullable();
            $table->json('aggregate_rows')->nullable();
            $table->json('having_rows')->nullable();
            $table->json('binding_rows')->nullable();

            $table->unsignedBigInteger('query_group_id')->nullable()->index();
            $table->unsignedBigInteger('report_group_id')->nullable()->index();

            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['query_mode', 'output_mode'], 'queries_mode_output_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queries');
    }
};
