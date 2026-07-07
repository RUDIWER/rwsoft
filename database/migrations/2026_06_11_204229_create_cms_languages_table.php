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
        Schema::create('cms_languages', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 12)->unique();
            $table->string('name');
            $table->string('native_name');
            $table->string('direction', 3)->default('ltr');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $this->seedDefaults();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_languages');
    }

    private function seedDefaults(): void
    {
        $now = now();

        foreach ($this->defaultLanguages() as $language) {
            DB::table('cms_languages')->insert(array_merge($language, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    /**
     * @return array<int, array{locale: string, name: string, native_name: string, direction: string, is_active: bool, sort_order: int}>
     */
    private function defaultLanguages(): array
    {
        return [
            ['locale' => 'nl', 'name' => 'Nederlands', 'native_name' => 'Nederlands', 'direction' => 'ltr', 'is_active' => true, 'sort_order' => 10],
            ['locale' => 'en', 'name' => 'Engels', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'sort_order' => 20],
            ['locale' => 'fr', 'name' => 'Frans', 'native_name' => 'Français', 'direction' => 'ltr', 'is_active' => true, 'sort_order' => 30],
            ['locale' => 'de', 'name' => 'Duits', 'native_name' => 'Deutsch', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 40],
            ['locale' => 'es', 'name' => 'Spaans', 'native_name' => 'Español', 'direction' => 'ltr', 'is_active' => false, 'sort_order' => 50],
        ];
    }
};
