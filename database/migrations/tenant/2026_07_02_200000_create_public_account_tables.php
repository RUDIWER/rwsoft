<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('site_users')) {
            Schema::connection($this->connection)->create('site_users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('status', 32)->default('active')->index();
                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip_hash', 64)->nullable();
                $table->text('two_factor_secret')->nullable();
                $table->text('two_factor_recovery_codes')->nullable();
                $table->timestamp('two_factor_confirmed_at')->nullable();
                $table->rememberToken();
                $table->timestamps();

                $table->unique('email');
                $table->index(['status', 'created_at']);
            });
        }

        if (! Schema::connection($this->connection)->hasTable('site_user_profiles')) {
            Schema::connection($this->connection)->create('site_user_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_user_id')->constrained('site_users')->cascadeOnDelete();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('locale', 12)->nullable();
                $table->boolean('marketing_opt_in')->default(false);
                $table->timestamps();

                $table->unique('site_user_id');
            });
        }

        if (! Schema::connection($this->connection)->hasTable('site_user_password_reset_tokens')) {
            Schema::connection($this->connection)->create('site_user_password_reset_tokens', function (Blueprint $table): void {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('site_user_password_reset_tokens');
        Schema::connection($this->connection)->dropIfExists('site_user_profiles');
        Schema::connection($this->connection)->dropIfExists('site_users');
    }
};
