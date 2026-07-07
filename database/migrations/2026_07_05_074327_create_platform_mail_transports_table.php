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
        Schema::create('platform_mail_transports', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('provider', 32)->default('smtp')->index();
            $table->boolean('is_active')->default(false)->index();
            $table->string('status', 32)->default('not_configured')->index();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('reply_to_email')->nullable();
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('encryption', 16)->nullable();
            $table->string('username')->nullable();
            $table->text('encrypted_secret')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 32)->nullable();
            $table->text('last_test_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_mail_transports');
    }
};
