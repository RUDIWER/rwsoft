<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'database_view_access')) {
                $table->boolean('database_view_access')->default(false)->after('two_factor_confirmed_at');
            }

            if (! Schema::hasColumn('users', 'database_edit_access')) {
                $table->boolean('database_edit_access')->default(false)->after('database_view_access');
            }

            if (! Schema::hasColumn('users', 'database_add_access')) {
                $table->boolean('database_add_access')->default(false)->after('database_edit_access');
            }

            if (! Schema::hasColumn('users', 'database_delete_access')) {
                $table->boolean('database_delete_access')->default(false)->after('database_add_access');
            }

            if (! Schema::hasColumn('users', 'database_export_access')) {
                $table->boolean('database_export_access')->default(false)->after('database_delete_access');
            }

            if (! Schema::hasColumn('users', 'database_sql_query_access')) {
                $table->boolean('database_sql_query_access')->default(false)->after('database_export_access');
            }

            if (! Schema::hasColumn('users', 'database_sql_destructive_access')) {
                $table->boolean('database_sql_destructive_access')->default(false)->after('database_sql_query_access');
            }

            if (! Schema::hasColumn('users', 'database_full_backup_access')) {
                $table->boolean('database_full_backup_access')->default(false)->after('database_sql_destructive_access');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $columns = [
                'database_view_access',
                'database_edit_access',
                'database_add_access',
                'database_delete_access',
                'database_export_access',
                'database_sql_query_access',
                'database_sql_destructive_access',
                'database_full_backup_access',
            ];

            $existingColumns = array_values(array_filter($columns, static function (string $column): bool {
                return Schema::hasColumn('users', $column);
            }));

            if ($existingColumns !== []) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
