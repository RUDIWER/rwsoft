<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('queries') || ! Schema::hasColumn('queries', 'query_key')) {
            return;
        }

        Schema::table('queries', function (Blueprint $table): void {
            $table->dropColumn('query_key');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('queries') || Schema::hasColumn('queries', 'query_key')) {
            return;
        }

        Schema::table('queries', function (Blueprint $table): void {
            $table->string('query_key', 160)->nullable()->unique()->after('slug');
        });
    }
};
