<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('cms_menus') && Schema::hasColumn('cms_menus', 'slug')) {
            Schema::table('cms_menus', function (Blueprint $table): void {
                $table->dropUnique('cms_menus_slug_unique');
                $table->dropColumn('slug');
            });
        }

        if (! Schema::hasTable('cms_menu_translations')) {
            Schema::create('cms_menu_translations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_menu_id')->constrained('cms_menus')->cascadeOnDelete();
                $table->string('locale', 12);
                $table->string('title')->nullable();
                $table->timestamps();

                $table->unique(['cms_menu_id', 'locale']);
                $table->index('locale');
            });
        }

        $this->backfillMenuTranslations();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_menu_translations');

        if (Schema::hasTable('cms_menus') && ! Schema::hasColumn('cms_menus', 'slug')) {
            Schema::table('cms_menus', function (Blueprint $table): void {
                $table->string('slug')->nullable()->after('title');
            });

            DB::table('cms_menus')
                ->orderBy('id')
                ->get(['id'])
                ->each(function ($menu): void {
                    DB::table('cms_menus')
                        ->where('id', $menu->id)
                        ->update(['slug' => 'menu-'.$menu->id]);
                });

            Schema::table('cms_menus', function (Blueprint $table): void {
                $table->unique('slug');
            });
        }
    }

    private function backfillMenuTranslations(): void
    {
        $now = now();
        $defaultLocale = $this->defaultLocale();

        DB::table('cms_menus')
            ->orderBy('id')
            ->get(['id', 'title'])
            ->each(function ($menu) use ($defaultLocale, $now): void {
                DB::table('cms_menu_translations')->updateOrInsert(
                    ['cms_menu_id' => $menu->id, 'locale' => $defaultLocale],
                    [
                        'title' => $menu->title,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            });
    }

    private function defaultLocale(): string
    {
        $setting = DB::table('cms_settings')
            ->where('group', 'general')
            ->where('key', 'default_locale')
            ->value('value');

        if (is_string($setting)) {
            $decoded = json_decode($setting, true);

            if (is_array($decoded) && filled($decoded['value'] ?? null)) {
                return (string) $decoded['value'];
            }
        }

        return (string) config('app.locale', 'nl');
    }
};
