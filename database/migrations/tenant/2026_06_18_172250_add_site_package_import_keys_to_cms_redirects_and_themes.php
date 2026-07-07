<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_redirects') && ! Schema::hasColumn('cms_redirects', 'import_key')) {
            Schema::table('cms_redirects', function (Blueprint $table): void {
                $table->string('import_key')->nullable()->after('id')->index();
            });
        }

        if (Schema::hasTable('cms_themes') && ! Schema::hasColumn('cms_themes', 'import_key')) {
            Schema::table('cms_themes', function (Blueprint $table): void {
                $table->string('import_key')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cms_themes') && Schema::hasColumn('cms_themes', 'import_key')) {
            Schema::table('cms_themes', function (Blueprint $table): void {
                $table->dropIndex(['import_key']);
                $table->dropColumn('import_key');
            });
        }

        if (Schema::hasTable('cms_redirects') && Schema::hasColumn('cms_redirects', 'import_key')) {
            Schema::table('cms_redirects', function (Blueprint $table): void {
                $table->dropIndex(['import_key']);
                $table->dropColumn('import_key');
            });
        }
    }
};
