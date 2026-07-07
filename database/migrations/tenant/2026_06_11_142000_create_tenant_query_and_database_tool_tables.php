<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(10)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('report_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(10)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('chart_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(10)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('queries', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('description', 255);
            $table->text('memo')->nullable();
            $table->string('query_mode', 20)->default('sql')->index();
            $table->string('output_mode', 20)->default('table')->index();
            $table->string('report_data_source', 20)->nullable();
            $table->string('report_output_format', 30)->nullable();
            $table->string('report_template_path', 255)->nullable();
            $table->string('report_template_filename', 255)->nullable();
            $table->string('report_template_extension', 16)->nullable();
            $table->unsignedInteger('report_template_size_kb')->nullable();
            $table->string('table_name', 160)->nullable();
            $table->boolean('all_fields')->default(false);
            $table->boolean('distinct_select')->default(false);
            $table->longText('query')->nullable();
            $table->longText('test_query')->nullable();
            $table->json('selected_fields')->nullable();
            $table->json('join_rows')->nullable();
            $table->json('where_rows')->nullable();
            $table->boolean('group_by')->default(false);
            $table->json('group_rows')->nullable();
            $table->json('aggregate_rows')->nullable();
            $table->json('having_rows')->nullable();
            $table->json('binding_rows')->nullable();
            $table->json('chart_config')->nullable();
            $table->foreignId('query_group_id')->nullable()->constrained('query_groups')->nullOnDelete();
            $table->foreignId('report_group_id')->nullable()->constrained('report_groups')->nullOnDelete();
            $table->foreignId('chart_group_id')->nullable()->constrained('chart_groups')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->index(['query_mode', 'output_mode'], 'queries_mode_output_index');
        });

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

        Schema::create('rw_table_charts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('table_identifier')->index();
            $table->string('description');
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'table_identifier', 'description'], 'rw_table_charts_unique_user_table_desc');
        });

        Schema::create('rw_table_exports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('table_identifier')->index();
            $table->string('description');
            $table->json('config');
            $table->timestamps();

            $table->unique(['user_id', 'table_identifier', 'description'], 'rw_table_exports_unique_user_table_desc');
        });

        Schema::create('rw_db_column_metadata', function (Blueprint $table): void {
            $table->id();
            $table->string('table_name', 128);
            $table->string('column_name', 64);
            $table->boolean('render_as_file_upload')->default(false);
            $table->json('upload_config')->nullable();
            $table->timestamps();

            $table->unique(['table_name', 'column_name'], 'rw_db_col_meta_table_column_unique');
            $table->index('table_name');
        });

        Schema::create('database_editor_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action', 50)->index();
            $table->string('table_name', 128)->index();
            $table->string('record_key', 128)->nullable()->index();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('database_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
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
        Schema::dropIfExists('database_editor_logs');
        Schema::dropIfExists('rw_db_column_metadata');
        Schema::dropIfExists('rw_table_exports');
        Schema::dropIfExists('rw_table_charts');
        Schema::dropIfExists('query_builder_select_tables');
        Schema::dropIfExists('queries');
        Schema::dropIfExists('chart_groups');
        Schema::dropIfExists('report_groups');
        Schema::dropIfExists('query_groups');
    }
};
