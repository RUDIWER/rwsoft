<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queries', function (Blueprint $table): void {
            if (! Schema::hasColumn('queries', 'chart_group_id')) {
                $table->unsignedBigInteger('chart_group_id')->nullable()->after('report_group_id')->index();
            }

            if (! Schema::hasColumn('queries', 'chart_config')) {
                $table->json('chart_config')->nullable()->after('binding_rows');
            }
        });

        if (Schema::hasColumn('queries', 'chart_group_id') && Schema::hasTable('chart_groups')) {
            try {
                Schema::table('queries', function (Blueprint $table): void {
                    $table->foreign('chart_group_id')->references('id')->on('chart_groups')->nullOnDelete();
                });
            } catch (Throwable) {
                // no-op when the foreign key already exists
            }
        }
    }

    public function down(): void
    {
        try {
            Schema::table('queries', function (Blueprint $table): void {
                $table->dropForeign(['chart_group_id']);
            });
        } catch (Throwable) {
            // no-op when foreign key does not exist
        }

        Schema::table('queries', function (Blueprint $table): void {
            if (Schema::hasColumn('queries', 'chart_group_id')) {
                $table->dropColumn('chart_group_id');
            }

            if (Schema::hasColumn('queries', 'chart_config')) {
                $table->dropColumn('chart_config');
            }
        });
    }
};
