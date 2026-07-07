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
                $table->foreignId('cms_download_asset_id')->constrained('cms_download_assets')->cascadeOnDelete();
                $table->string('locale', 12);
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['cms_download_asset_id', 'locale'], 'cms_download_asset_translations_asset_locale_unique');
            });
        }

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
                $table->foreignId('cms_download_group_id')->constrained('cms_download_groups')->cascadeOnDelete();
                $table->foreignId('site_user_id')->constrained('site_users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['cms_download_group_id', 'site_user_id'], 'cms_download_group_site_user_unique');
            });
        }

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
                $table->foreignId('cms_download_asset_id')->nullable()->constrained('cms_download_assets')->nullOnDelete();
                $table->unsignedBigInteger('site_user_id')->nullable()->index();
                $table->string('event', 32)->index();
                $table->string('ip_hash', 64)->nullable();
                $table->string('user_agent_hash', 64)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
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
};
