<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('queries')) {
            return;
        }

        Schema::table('queries', function (Blueprint $table): void {
            if (! Schema::hasColumn('queries', 'all_fields')) {
                $table->boolean('all_fields')->default(false)->after('table_name');
            }

            if (! Schema::hasColumn('queries', 'distinct_select')) {
                $table->boolean('distinct_select')->default(false)->after('all_fields');
            }

            if (! Schema::hasColumn('queries', 'group_by')) {
                $table->boolean('group_by')->default(false)->after('where_rows');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('queries')) {
            return;
        }

        Schema::table('queries', function (Blueprint $table): void {
            $columns = [];

            if (Schema::hasColumn('queries', 'group_by')) {
                $columns[] = 'group_by';
            }

            if (Schema::hasColumn('queries', 'distinct_select')) {
                $columns[] = 'distinct_select';
            }

            if (Schema::hasColumn('queries', 'all_fields')) {
                $columns[] = 'all_fields';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
