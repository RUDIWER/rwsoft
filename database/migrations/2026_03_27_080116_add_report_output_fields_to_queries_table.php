<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queries', function (Blueprint $table): void {
            if (! Schema::hasColumn('queries', 'report_data_source')) {
                $table->string('report_data_source', 20)->nullable()->after('output_mode');
            }

            if (! Schema::hasColumn('queries', 'report_output_format')) {
                $table->string('report_output_format', 30)->nullable()->after('report_data_source');
            }

            if (! Schema::hasColumn('queries', 'report_template_path')) {
                $table->string('report_template_path', 255)->nullable()->after('report_output_format');
            }

            if (! Schema::hasColumn('queries', 'report_template_filename')) {
                $table->string('report_template_filename', 255)->nullable()->after('report_template_path');
            }

            if (! Schema::hasColumn('queries', 'report_template_extension')) {
                $table->string('report_template_extension', 16)->nullable()->after('report_template_filename');
            }

            if (! Schema::hasColumn('queries', 'report_template_size_kb')) {
                $table->unsignedInteger('report_template_size_kb')->nullable()->after('report_template_extension');
            }
        });
    }

    public function down(): void
    {
        $columns = [
            'report_data_source',
            'report_output_format',
            'report_template_path',
            'report_template_filename',
            'report_template_extension',
            'report_template_size_kb',
        ];

        foreach ($columns as $column) {
            if (! Schema::hasColumn('queries', $column)) {
                continue;
            }

            Schema::table('queries', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }
};
