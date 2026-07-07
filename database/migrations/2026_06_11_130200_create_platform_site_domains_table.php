<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('host', 255)->unique();
            $table->boolean('is_primary')->default(false)->index();
            $table->boolean('force_https')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_domains');
    }
};
