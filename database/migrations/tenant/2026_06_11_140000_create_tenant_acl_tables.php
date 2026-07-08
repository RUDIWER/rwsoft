<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasTable('acl_roles')) {
            Schema::create('acl_roles', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (! $this->hasTable('acl_permissions')) {
            Schema::create('acl_permissions', function (Blueprint $table): void {
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

        if (! $this->hasTable('acl_role_user')) {
            Schema::create('acl_role_user', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('acl_role_id');
                $table->timestamps();

                $table->unique(['user_id', 'acl_role_id'], 'acl_role_user_unique');
                $table->index('user_id');
                $table->index('acl_role_id');
            });
        }

        if (! $this->hasTable('acl_permission_role')) {
            Schema::create('acl_permission_role', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('acl_role_id');
                $table->unsignedBigInteger('acl_permission_id');
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['acl_role_id', 'acl_permission_id'], 'acl_perm_role_unique');
                $table->index('acl_role_id');
                $table->index('acl_permission_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_permission_role');
        Schema::dropIfExists('acl_role_user');
        Schema::dropIfExists('acl_permissions');
        Schema::dropIfExists('acl_roles');
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table) || $this->hasPrefixedTable($table);
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
};
