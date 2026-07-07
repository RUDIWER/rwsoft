<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_menu_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_menu_id')->constrained('cms_menus')->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('title')->nullable();
            $table->timestamps();

            $table->unique(['cms_menu_id', 'locale']);
            $table->index('locale');
        });

        Schema::create('cms_setting_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_setting_id')->constrained('cms_settings')->cascadeOnDelete();
            $table->string('locale', 12);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['cms_setting_id', 'locale']);
            $table->index('locale');
        });

        $this->backfillMenuTranslations();
        $this->backfillSettingTranslations();
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_setting_translations');
        Schema::dropIfExists('cms_menu_translations');
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

    private function backfillSettingTranslations(): void
    {
        $now = now();
        $defaultLocale = $this->defaultLocale();
        $translatable = [
            'general.site_name',
            'general.site_tagline',
            'seo.default_title',
            'seo.default_description',
        ];

        DB::table('cms_settings')
            ->get()
            ->each(function ($setting) use ($defaultLocale, $now, $translatable): void {
                if (! in_array($setting->group.'.'.$setting->key, $translatable, true)) {
                    return;
                }

                DB::table('cms_setting_translations')->updateOrInsert(
                    ['cms_setting_id' => $setting->id, 'locale' => $defaultLocale],
                    [
                        'value' => $setting->value,
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
