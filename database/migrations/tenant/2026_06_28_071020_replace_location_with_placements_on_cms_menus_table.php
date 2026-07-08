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
        if (! Schema::hasColumn('cms_menus', 'placements')) {
            Schema::table('cms_menus', function (Blueprint $table): void {
                $table->json('placements')->nullable()->after('title');
            });
        }

        if (Schema::hasColumn('cms_menus', 'location')) {
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
        }

        if (Schema::hasColumn('cms_menus', 'location')) {
            Schema::table('cms_menus', function (Blueprint $table): void {
                if ($this->hasIndex('cms_menus', 'cms_menus_location_unique')) {
                    $table->dropUnique($this->indexName('cms_menus_location_unique'));
                }

                if ($this->hasIndex('cms_menus', 'cms_menus_location_index')) {
                    $table->dropIndex($this->indexName('cms_menus_location_index'));
                }

                $table->dropColumn('location');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('cms_menus', 'location')) {
            Schema::table('cms_menus', function (Blueprint $table): void {
                $table->string('location')->nullable()->index()->after('title');
            });
        }

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

        Schema::table('cms_menus', function (Blueprint $table): void {
            if (! $this->hasIndex('cms_menus', 'cms_menus_location_unique')) {
                $table->unique('location');
            }

            if (Schema::hasColumn('cms_menus', 'placements')) {
                $table->dropColumn('placements');
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return Schema::hasIndex($table, $index) || $this->hasPrefixedIndex($table, $index);
    }

    private function hasPrefixedIndex(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        return $prefix !== '' && $connection->selectOne(
            'select 1 from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$connection->getDatabaseName(), $prefix.$table, $prefix.$index],
        ) !== null;
    }

    private function indexName(string $index): string
    {
        $prefix = Schema::getConnection()->getTablePrefix();

        return $prefix !== '' && $this->hasPrefixedIndex('cms_menus', $index)
            ? $prefix.$index
            : $index;
    }
};
