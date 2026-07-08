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
        if ($this->hasTable('acl_roles')) {
            Schema::table('acl_roles', function (Blueprint $table) {
                if (! $this->hasColumn('acl_roles', 'key')) {
                    $table->string('key')->nullable();
                }

                if (! $this->hasColumn('acl_roles', 'name')) {
                    $table->string('name')->nullable();
                }

                if (! $this->hasColumn('acl_roles', 'description')) {
                    $table->string('description')->nullable();
                }
            });

            return;
        }

        Schema::create('acl_roles', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acl_roles');
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
