<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('occurred_at')->index();
            $table->uuid('request_id')->nullable()->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 160)->nullable();
            $table->string('actor_email', 190)->nullable();
            $table->string('application_slug', 120)->nullable()->index();
            $table->string('application_name', 160)->nullable();
            $table->string('module', 64)->index();
            $table->string('action', 120)->index();
            $table->string('subject_type', 80)->nullable()->index();
            $table->string('subject_key', 191)->nullable()->index();
            $table->boolean('success')->default(true)->index();
            $table->string('severity', 20)->default('info')->index();
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('route_name', 160)->nullable();
            $table->string('http_method', 16)->nullable();
            $table->text('url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
