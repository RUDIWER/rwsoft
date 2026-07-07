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
        if (! Schema::hasTable('cms_visitors')) {
            Schema::create('cms_visitors', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->nullable()->index();
                $table->ipAddress('ip')->nullable();
                $table->string('ip_hash', 128)->nullable()->index();
                $table->unsignedTinyInteger('geo_checked')->default(0)->index();
                $table->string('country_code', 10)->nullable()->index();
                $table->string('country_name', 120)->nullable();
                $table->string('region_code', 20)->nullable();
                $table->string('region_name', 120)->nullable();
                $table->string('city_name', 120)->nullable();
                $table->string('zip_code', 30)->nullable();
                $table->decimal('latitude', 10, 6)->nullable();
                $table->decimal('longitude', 10, 6)->nullable();
                $table->string('timezone', 80)->nullable();
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cms_visits')) {
            Schema::create('cms_visits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_visitor_id')->nullable()->constrained('cms_visitors')->nullOnDelete();
                $table->uuid('uuid')->nullable()->index();
                $table->ipAddress('ip')->nullable();
                $table->string('ip_hash', 128)->nullable()->index();
                $table->string('method', 12)->default('GET');
                $table->string('url', 2048)->nullable();
                $table->string('path', 512)->nullable();
                $table->string('locale', 12)->nullable()->index();
                $table->string('ref')->nullable();
                $table->string('referer', 2048)->nullable();
                $table->string('utm_source')->nullable();
                $table->string('utm_medium')->nullable();
                $table->string('utm_campaign')->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->string('platform', 255)->nullable();
                $table->string('country_code_header', 10)->nullable();
                $table->boolean('is_crawler')->default(false);
                $table->json('data')->nullable();
                $table->timestamps();

                $table->index(['path', 'created_at'], 'cms_visits_path_created_at_idx');
                $table->index(['cms_visitor_id', 'created_at'], 'cms_visits_visitor_created_at_idx');
                $table->index(['locale', 'created_at'], 'cms_visits_locale_created_at_idx');
                $table->index(['created_at'], 'cms_visits_created_at_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_visits');
        Schema::dropIfExists('cms_visitors');
    }
};
