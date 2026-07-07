<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cms_menu_items', function (Blueprint $table): void {
            $table->string('locale', 12)->nullable()->after('cms_menu_id')->index();
            $table->string('translation_key', 64)->nullable()->after('locale')->index();
            $table->foreignId('translated_from_menu_item_id')
                ->nullable()
                ->after('translation_key')
                ->constrained('cms_menu_items')
                ->nullOnDelete();

            $table->unique(
                ['cms_menu_id', 'translation_key', 'locale'],
                'cms_menu_items_menu_translation_locale_unique'
            );
        });

        $defaultLocale = $this->defaultLocale();

        DB::table('cms_menu_items')
            ->select(['id'])
            ->orderBy('cms_menu_items.id')
            ->get()
            ->each(function ($item) use ($defaultLocale): void {
                DB::table('cms_menu_items')
                    ->where('id', $item->id)
                    ->update([
                        'locale' => $defaultLocale,
                        'translation_key' => (string) Str::ulid(),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_menu_items', function (Blueprint $table): void {
            $table->dropUnique('cms_menu_items_menu_translation_locale_unique');
            $table->dropConstrainedForeignId('translated_from_menu_item_id');
            $table->dropColumn(['locale', 'translation_key']);
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
