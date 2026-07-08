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
        if ($this->hasTable('acl_permissions')) {
            Schema::table('acl_permissions', function (Blueprint $table) {
                if (! $this->hasColumn('acl_permissions', 'route_name')) {
                    $table->string('route_name')->nullable();
                }

                if (! $this->hasColumn('acl_permissions', 'description')) {
                    $table->string('description')->nullable();
                }

                if (! $this->hasColumn('acl_permissions', 'module')) {
                    $table->string('module')->nullable();
                }

                if (! $this->hasColumn('acl_permissions', 'action')) {
                    $table->string('action')->nullable();
                }

                if (! $this->hasColumn('acl_permissions', 'type')) {
                    $table->string('type')->nullable();
                }

                if (! $this->hasColumn('acl_permissions', 'query_id')) {
                    $table->unsignedBigInteger('query_id')->nullable();
                }

                if (! $this->hasColumn('acl_permissions', 'menu')) {
                    $table->boolean('menu')->default(false);
                }

                if (! $this->hasColumn('acl_permissions', 'url')) {
                    $table->string('url')->nullable();
                }
            });

            return;
        }

        Schema::create('acl_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('route_name')->unique();
            $table->string('description');
            $table->string('module')->nullable();
            $table->string('action')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('query_id')->nullable();
            $table->boolean('menu')->default(false);
            $table->string('url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acl_permissions');
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
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        return $prefix !== '' && $connection->selectOne(
            'select 1 from information_schema.tables where table_schema = ? and table_name = ? limit 1',
            [$connection->getDatabaseName(), $prefix.$table],
        ) !== null;
    }

    private function hasPrefixedColumn(string $table, string $column): bool
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        return $prefix !== '' && $connection->selectOne(
            'select 1 from information_schema.columns where table_schema = ? and table_name = ? and column_name = ? limit 1',
            [$connection->getDatabaseName(), $prefix.$table, $column],
        ) !== null;
    }
};
