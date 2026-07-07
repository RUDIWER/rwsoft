<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_templates') || ! Schema::hasTable('cms_layouts')) {
            return;
        }

        Schema::table('cms_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('cms_templates', 'layout_id')) {
                $table->foreignId('layout_id')
                    ->nullable()
                    ->after('translated_from_template_id')
                    ->constrained('cms_layouts')
                    ->nullOnDelete();
            }
        });

        DB::table('cms_templates')
            ->whereNull('layout_id')
            ->orderBy('id')
            ->get(['id', 'locale'])
            ->each(function (object $template): void {
                $layoutId = DB::table('cms_layouts')
                    ->where('locale', $template->locale)
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->value('id');

                if ($layoutId) {
                    DB::table('cms_templates')
                        ->where('id', $template->id)
                        ->update(['layout_id' => $layoutId]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cms_templates') || ! Schema::hasColumn('cms_templates', 'layout_id')) {
            return;
        }

        Schema::table('cms_templates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('layout_id');
        });
    }
};
