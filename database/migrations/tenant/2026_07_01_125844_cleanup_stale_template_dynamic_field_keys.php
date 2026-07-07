<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

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
            ->where(function ($query): void {
                $query
                    ->where('type', 'dynamic_field')
                    ->orWhereIn('cms_placeable_block_id', function ($subQuery): void {
                        $subQuery
                            ->select('id')
                            ->from('cms_placeable_blocks')
                            ->where('renderer_key', 'dynamic_field')
                            ->orWhere('key', 'dynamic_field');
                    });
            })
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(content, '$.field_key')) LIKE 'template.%'")
            ->orderBy('id')
            ->chunkById(100, function ($blocks): void {
                foreach ($blocks as $block) {
                    $content = $this->decodeJson($block->content) ?? [];
                    $content['field_key'] = null;

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
        // Intentionally irreversible: template.* dynamic fields are no longer part of the current template contract.
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
