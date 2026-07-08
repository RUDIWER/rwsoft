<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_download_folders')) {
            Schema::connection($this->connection)->create('cms_download_folders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('cms_download_folders')->nullOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->string('access_mode', 32)->default('inherit')->index();
                $table->string('password_hash')->nullable();
                $table->unsignedInteger('password_expires_minutes')->nullable();
                $table->json('settings')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['parent_id', 'slug'], 'cms_download_folders_parent_slug_unique');
                $table->index(['parent_id', 'sort_order'], 'cms_download_folders_parent_sort_index');
            });
        }

        if (! Schema::connection($this->connection)->hasTable('cms_download_assets')) {
            Schema::connection($this->connection)->create('cms_download_assets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('folder_id')->nullable()->constrained('cms_download_folders')->nullOnDelete();
                $table->unsignedBigInteger('uploaded_by')->nullable()->index();
                $table->string('disk')->default((string) config('cms_downloads.disk', 'private'));
                $table->string('visibility', 24)->default('protected')->index();
                $table->string('access_mode', 32)->default('inherit')->index();
                $table->string('path')->unique();
                $table->string('filename');
                $table->string('original_filename')->nullable();
                $table->string('mime_type', 160)->nullable();
                $table->string('extension', 32)->nullable()->index();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('hash', 64)->nullable()->index();
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamp('expires_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['folder_id', 'sort_order'], 'cms_download_assets_folder_sort_index');
            });
        }

        if (! Schema::connection($this->connection)->hasTable('cms_download_asset_translations')) {
            Schema::connection($this->connection)->create('cms_download_asset_translations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_download_asset_id');
                $table->string('locale', 12);
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['cms_download_asset_id', 'locale'], 'cms_download_asset_translations_asset_locale_unique');
                $table->foreign('cms_download_asset_id', 'cms_download_asset_translations_asset_fk')
                    ->references('id')
                    ->on('cms_download_assets')
                    ->cascadeOnDelete();
            });
        }

        $this->ensureForeignKey('cms_download_asset_translations', 'cms_download_asset_id', 'cms_download_assets', 'cms_download_asset_translations_asset_fk', cascadeOnDelete: true);

        if (! Schema::connection($this->connection)->hasTable('cms_download_groups')) {
            Schema::connection($this->connection)->create('cms_download_groups', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::connection($this->connection)->hasTable('cms_download_group_site_user')) {
            Schema::connection($this->connection)->create('cms_download_group_site_user', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_download_group_id');
                $table->foreignId('site_user_id');
                $table->timestamps();

                $table->unique(['cms_download_group_id', 'site_user_id'], 'cms_download_group_site_user_unique');
                $table->foreign('cms_download_group_id', 'cdgsu_group_fk')
                    ->references('id')
                    ->on('cms_download_groups')
                    ->cascadeOnDelete();
                $table->foreign('site_user_id', 'cdgsu_site_user_fk')
                    ->references('id')
                    ->on('site_users')
                    ->cascadeOnDelete();
            });
        }

        $this->ensureForeignKey('cms_download_group_site_user', 'cms_download_group_id', 'cms_download_groups', 'cdgsu_group_fk', cascadeOnDelete: true);
        $this->ensureForeignKey('cms_download_group_site_user', 'site_user_id', 'site_users', 'cdgsu_site_user_fk', cascadeOnDelete: true);

        if (! Schema::connection($this->connection)->hasTable('cms_download_access_rules')) {
            Schema::connection($this->connection)->create('cms_download_access_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('subject_type', 32);
                $table->unsignedBigInteger('subject_id');
                $table->string('rule_type', 32);
                $table->unsignedBigInteger('site_user_id')->nullable()->index();
                $table->unsignedBigInteger('cms_download_group_id')->nullable()->index();
                $table->string('profile_field_key', 80)->nullable()->index();
                $table->string('operator', 32)->nullable();
                $table->json('value')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['subject_type', 'subject_id'], 'cms_download_access_rules_subject_index');
            });
        }

        if (! Schema::connection($this->connection)->hasTable('cms_download_events')) {
            Schema::connection($this->connection)->create('cms_download_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_download_asset_id')->nullable();
                $table->unsignedBigInteger('site_user_id')->nullable()->index();
                $table->string('event', 32)->index();
                $table->string('ip_hash', 64)->nullable();
                $table->string('user_agent_hash', 64)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('cms_download_asset_id', 'cms_download_events_asset_fk')
                    ->references('id')
                    ->on('cms_download_assets')
                    ->nullOnDelete();
            });
        }

        $this->ensureForeignKey('cms_download_events', 'cms_download_asset_id', 'cms_download_assets', 'cms_download_events_asset_fk', nullOnDelete: true);
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('cms_download_events');
        Schema::connection($this->connection)->dropIfExists('cms_download_access_rules');
        Schema::connection($this->connection)->dropIfExists('cms_download_group_site_user');
        Schema::connection($this->connection)->dropIfExists('cms_download_groups');
        Schema::connection($this->connection)->dropIfExists('cms_download_asset_translations');
        Schema::connection($this->connection)->dropIfExists('cms_download_assets');
        Schema::connection($this->connection)->dropIfExists('cms_download_folders');
    }

    private function ensureForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $constraint,
        bool $cascadeOnDelete = false,
        bool $nullOnDelete = false,
    ): void {
        if (! Schema::connection($this->connection)->hasTable($table)
            || ! Schema::connection($this->connection)->hasTable($referencedTable)
            || ! Schema::connection($this->connection)->hasColumn($table, $column)
            || $this->hasForeignKey($table, $column)) {
            return;
        }

        Schema::connection($this->connection)->table($table, function (Blueprint $blueprint) use ($cascadeOnDelete, $column, $constraint, $nullOnDelete, $referencedTable): void {
            $foreign = $blueprint->foreign($column, $constraint)
                ->references('id')
                ->on($referencedTable);

            if ($cascadeOnDelete) {
                $foreign->cascadeOnDelete();
            }

            if ($nullOnDelete) {
                $foreign->nullOnDelete();
            }
        });
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        $connection = Schema::connection($this->connection)->getConnection();
        $prefix = $connection->getTablePrefix();

        return $connection->selectOne(
            'select 1 from information_schema.key_column_usage where table_schema = ? and table_name = ? and column_name = ? and referenced_table_name is not null limit 1',
            [$connection->getDatabaseName(), $prefix.$table, $column],
        ) !== null;
    }
};
