<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * @var array<string, mixed>
     */
    private array $defaults = [
        'show_current' => true,
        'show_on_home' => true,
        'compact' => false,
        'home_icon' => 'mdi-home',
        'separator' => '›',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_blocks')) {
            return;
        }

        DB::connection($this->connection)
            ->table('cms_blocks')
            ->where('type', 'breadcrumb')
            ->orWhereIn('cms_placeable_block_id', function ($query): void {
                $query
                    ->select('id')
                    ->from('cms_placeable_blocks')
                    ->where('renderer_key', 'breadcrumb')
                    ->orWhere('key', 'breadcrumb');
            })
            ->orderBy('id')
            ->chunkById(100, function ($blocks): void {
                foreach ($blocks as $block) {
                    $content = array_replace($this->defaults, $this->decodeJson($block->content) ?? []);

                    foreach ($this->defaults as $key => $value) {
                        if (! array_key_exists($key, $content) || $content[$key] === null || $content[$key] === '') {
                            $content[$key] = $value;
                        }
                    }

                    DB::connection($this->connection)
                        ->table('cms_blocks')
                        ->where('id', $block->id)
                        ->update(['content' => json_encode($content, JSON_THROW_ON_ERROR)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: this fills missing breadcrumb instance defaults for the current block contract.
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
};
