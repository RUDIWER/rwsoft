<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('site_user_profile_field_definitions')) {
            Schema::connection($this->connection)->create('site_user_profile_field_definitions', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 80)->unique();
                $table->string('label');
                $table->string('type', 32)->default('text');
                $table->json('options')->nullable();
                $table->json('validation_rules')->nullable();
                $table->boolean('is_required')->default(false);
                $table->boolean('is_active')->default(true);
                $table->boolean('show_on_register')->default(false);
                $table->boolean('show_on_profile')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->index('is_active', 'supfd_active_idx');
                $table->index('show_on_register', 'supfd_register_idx');
                $table->index('show_on_profile', 'supfd_profile_idx');
                $table->index('sort_order', 'supfd_sort_idx');
            });
        }

        $this->ensureDefinitionIndex('show_on_register', 'supfd_register_idx');
        $this->ensureDefinitionIndex('show_on_profile', 'supfd_profile_idx');
        $this->ensureDefinitionIndex('sort_order', 'supfd_sort_idx');

        if (! Schema::connection($this->connection)->hasTable('site_user_profile_field_values')) {
            Schema::connection($this->connection)->create('site_user_profile_field_values', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_user_id');
                $table->unsignedBigInteger('site_user_profile_field_definition_id');
                $table->string('profile_field_key', 80)->index();
                $table->text('value')->nullable();
                $table->timestamps();

                $table->unique(['site_user_id', 'site_user_profile_field_definition_id'], 'site_user_profile_field_values_user_definition_unique');
                $table->foreign('site_user_id', 'supfv_user_fk')
                    ->references('id')
                    ->on('site_users')
                    ->cascadeOnDelete();
                $table->foreign('site_user_profile_field_definition_id', 'supfv_definition_fk')
                    ->references('id')
                    ->on('site_user_profile_field_definitions')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('site_user_profile_field_values');
        Schema::connection($this->connection)->dropIfExists('site_user_profile_field_definitions');
    }

    private function ensureDefinitionIndex(string $column, string $index): void
    {
        if (! Schema::connection($this->connection)->hasTable('site_user_profile_field_definitions')
            || ! Schema::connection($this->connection)->hasColumn('site_user_profile_field_definitions', $column)
            || $this->hasIndex('site_user_profile_field_definitions', $index)) {
            return;
        }

        Schema::connection($this->connection)->table('site_user_profile_field_definitions', function (Blueprint $table) use ($column, $index): void {
            $table->index($column, $index);
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return Schema::connection($this->connection)->hasIndex($table, $index) || $this->hasPrefixedIndex($table, $index);
    }

    private function hasPrefixedIndex(string $table, string $index): bool
    {
        $connection = Schema::connection($this->connection)->getConnection();
        $prefix = $connection->getTablePrefix();

        return $prefix !== '' && $connection->selectOne(
            'select 1 from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$connection->getDatabaseName(), $prefix.$table, $prefix.$index],
        ) !== null;
    }
};
