<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_modules')) {
            Schema::connection($this->connection)->create('cms_modules', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 128)->unique();
                $table->string('name');
                $table->string('status', 32)->default('active')->index();
                $table->json('settings')->nullable();
                $table->timestamp('installed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('cms_modules');
    }
};
