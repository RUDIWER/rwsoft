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
        Schema::create('rw_db_column_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 128);
            $table->string('column_name', 64);
            $table->boolean('render_as_file_upload')->default(false);
            $table->json('upload_config')->nullable();
            $table->timestamps();

            $table->unique(['table_name', 'column_name'], 'rw_db_col_meta_table_column_unique');
            $table->index('table_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rw_db_column_metadata');
    }
};
