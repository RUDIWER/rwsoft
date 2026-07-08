<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_hosting_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('provider', 64)->default('laravel_cloud')->index();
            $table->string('api_base_url', 255)->nullable();
            $table->text('api_token')->nullable();
            $table->string('status', 32)->default('not_tested')->index();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('platform_hosting_environments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hosting_connection_id')->constrained('platform_hosting_connections')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('provider_application_id', 120);
            $table->string('provider_environment_id', 120);
            $table->string('provider_region', 80)->nullable();
            $table->string('default_tenant_database_mode', 32)->default('shared_prefixed');
            $table->string('default_database_name', 160)->nullable();
            $table->string('default_storage_mode', 64)->default('environment');
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['hosting_connection_id', 'provider_environment_id'], 'hosting_env_connection_provider_environment_unique');
        });

        Schema::create('platform_site_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('hosting_environment_id')->constrained('platform_hosting_environments')->cascadeOnDelete();
            $table->string('remote_site_slug', 120);
            $table->string('remote_domain', 255)->nullable()->index();
            $table->string('remote_tenant_database_mode', 32)->default('shared_prefixed');
            $table->string('remote_tenant_database', 160)->nullable();
            $table->string('remote_tenant_table_prefix', 48)->nullable();
            $table->string('remote_site_id', 120)->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('last_push_at')->nullable();
            $table->timestamp('last_pull_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['hosting_environment_id', 'remote_site_slug'], 'site_publication_environment_slug_unique');
        });

        Schema::create('platform_site_publication_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_publication_id')->constrained('platform_site_publications')->cascadeOnDelete();
            $table->string('direction', 16)->index();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('steps')->nullable();
            $table->json('options')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_site_publication_runs');
        Schema::dropIfExists('platform_site_publications');
        Schema::dropIfExists('platform_hosting_environments');
        Schema::dropIfExists('platform_hosting_connections');
    }
};
