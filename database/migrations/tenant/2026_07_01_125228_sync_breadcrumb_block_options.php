<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * @var array<int, string>
     */
    private array $fields = [
        'show_current',
        'show_on_home',
        'compact',
        'home_icon',
        'separator',
    ];

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
     * @var array<int, array<string, mixed>>
     */
    private array $editorFields = [
        ['name' => 'show_current', 'type' => 'checkbox', 'label_key' => 'components.block_editor.show_current_page'],
        ['name' => 'show_on_home', 'type' => 'checkbox', 'label_key' => 'components.block_editor.show_on_home'],
        ['name' => 'compact', 'type' => 'checkbox', 'label_key' => 'components.block_editor.compact_display'],
        ['name' => 'home_icon', 'type' => 'text', 'label_key' => 'components.block_editor.home_icon', 'placeholder_key' => 'components.block_editor.home_icon_placeholder'],
        ['name' => 'separator', 'type' => 'select', 'label_key' => 'components.block_editor.breadcrumb_separator', 'options' => [
            ['value' => '›', 'label_key' => 'components.block_editor.separator_chevron', 'label' => '›'],
            ['value' => '>', 'label_key' => 'components.block_editor.separator_greater_than', 'label' => '>'],
            ['value' => '/', 'label_key' => 'components.block_editor.separator_slash', 'label' => '/'],
            ['value' => '•', 'label_key' => 'components.block_editor.separator_dot', 'label' => '•'],
        ]],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_placeable_blocks')) {
            return;
        }

        $blockIds = DB::connection($this->connection)
            ->table('cms_placeable_blocks')
            ->where('renderer_key', 'breadcrumb')
            ->orWhere('key', 'breadcrumb')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($blockIds === []) {
            return;
        }

        $this->syncTable('cms_placeable_blocks', $blockIds);

        if (Schema::connection($this->connection)->hasTable('cms_placeable_block_revisions')) {
            $this->syncTable('cms_placeable_block_revisions', $blockIds, 'cms_placeable_block_id');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: this syncs breadcrumb blocks to the current editor contract.
    }

    /**
     * @param  array<int, int>  $blockIds
     */
    private function syncTable(string $table, array $blockIds, string $blockIdColumn = 'id'): void
    {
        DB::connection($this->connection)
            ->table($table)
            ->whereIn($blockIdColumn, $blockIds)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $schema = $this->decodeJson($row->schema) ?? [];
                    $schema['fields'] = $this->fields;
                    $schema['editor_fields'] = $this->editorFields;
                    $schema['preview'] = is_array($schema['preview'] ?? null) ? $schema['preview'] : ['title_field' => null];
                    $schema['category'] = $schema['category'] ?? 'content';
                    $schema['editor_visible'] = true;

                    $defaults = array_replace($this->defaults, $this->decodeJson($row->defaults) ?? []);

                    foreach ($this->defaults as $key => $value) {
                        if (! array_key_exists($key, $defaults) || $defaults[$key] === null || $defaults[$key] === '') {
                            $defaults[$key] = $value;
                        }
                    }

                    DB::connection($this->connection)
                        ->table($table)
                        ->where('id', $row->id)
                        ->update([
                            'schema' => json_encode($schema, JSON_THROW_ON_ERROR),
                            'defaults' => json_encode($defaults, JSON_THROW_ON_ERROR),
                        ]);
                }
            });
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
