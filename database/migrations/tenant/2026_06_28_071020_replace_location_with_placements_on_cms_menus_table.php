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
        Schema::table('cms_menus', function (Blueprint $table) {
            $table->json('placements')->nullable()->after('title');
        });

        DB::table('cms_menus')
            ->select(['id', 'location'])
            ->orderBy('id')
            ->each(function (object $menu): void {
                $allowedPlacements = array_keys((array) config('cms_menus.placements', []));
                $location = trim((string) ($menu->location ?? ''));
                $placements = in_array($location, $allowedPlacements, true) ? [$location] : [];

                DB::table('cms_menus')
                    ->where('id', $menu->id)
                    ->update([
                        'placements' => json_encode($placements),
                    ]);
            });

        Schema::table('cms_menus', function (Blueprint $table) {
            $table->dropUnique('cms_menus_location_unique');
            $table->dropIndex('cms_menus_location_index');
            $table->dropColumn('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_menus', function (Blueprint $table) {
            $table->string('location')->nullable()->index()->after('title');
        });

        DB::table('cms_menus')
            ->select(['id', 'placements'])
            ->orderBy('id')
            ->each(function (object $menu): void {
                $placements = json_decode((string) ($menu->placements ?? '[]'), true);
                $location = is_array($placements) ? (string) ($placements[0] ?? '') : '';

                DB::table('cms_menus')
                    ->where('id', $menu->id)
                    ->update(['location' => $location !== '' ? $location : null]);
            });

        Schema::table('cms_menus', function (Blueprint $table) {
            $table->unique('location');
            $table->dropColumn('placements');
        });
    }
};
