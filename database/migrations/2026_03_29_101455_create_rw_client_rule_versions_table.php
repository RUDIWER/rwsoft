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
        if (Schema::hasTable('rw_client_rule_versions')) {
            return;
        }

        Schema::create('rw_client_rule_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('state', 20)->default('draft')->index();
            $table->longText('code');
            $table->string('checksum', 64)->nullable()->index();
            $table->string('build_status', 20)->default('pending')->index();
            $table->longText('build_log')->nullable();
            $table->timestamp('build_started_at')->nullable();
            $table->timestamp('build_finished_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['state', 'version'], 'rw_client_rule_versions_state_version_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rw_client_rule_versions');
    }
};
