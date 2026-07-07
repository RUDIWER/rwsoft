<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('site_user_sessions')) {
            return;
        }

        Schema::connection($this->connection)->create('site_user_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_user_id')->constrained('site_users')->cascadeOnDelete();
            $table->string('session_token_hash', 64)->unique();
            $table->string('ip_hash', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['site_user_id', 'revoked_at'], 'sus_user_revoked_idx');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('site_user_sessions');
    }
};
