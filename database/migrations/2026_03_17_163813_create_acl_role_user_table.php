<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->hasTable('acl_role_user')) {
            Schema::table('acl_role_user', function (Blueprint $table) {
                if (! $this->hasColumn('acl_role_user', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
                }

                if (! $this->hasColumn('acl_role_user', 'acl_role_id')) {
                    $table->foreignId('acl_role_id')->nullable()->constrained('acl_roles')->cascadeOnDelete();
                }
            });

            return;
        }

        Schema::create('acl_role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('acl_role_id')->constrained('acl_roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'acl_role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acl_role_user');
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table) || $this->hasPrefixedTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column) || $this->hasPrefixedColumn($table, $column);
    }

    private function hasPrefixedTable(string $table): bool
    {
        $prefix = DB::connection()->getTablePrefix();

        return $prefix !== '' && DB::selectOne(
            'select 1 from information_schema.tables where table_schema = ? and table_name = ? limit 1',
            [DB::connection()->getDatabaseName(), $prefix.$table],
        ) !== null;
    }

    private function hasPrefixedColumn(string $table, string $column): bool
    {
        $prefix = DB::connection()->getTablePrefix();

        return $prefix !== '' && DB::selectOne(
            'select 1 from information_schema.columns where table_schema = ? and table_name = ? and column_name = ? limit 1',
            [DB::connection()->getDatabaseName(), $prefix.$table, $column],
        ) !== null;
    }
};
