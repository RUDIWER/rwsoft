<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        if (Schema::connection($this->connection)->hasTable('cms_pages')
            && Schema::connection($this->connection)->hasColumn('cms_pages', 'excerpt')
            && ! Schema::connection($this->connection)->hasColumn('cms_pages', 'short_description')) {
            Schema::connection($this->connection)->table('cms_pages', function (Blueprint $table): void {
                $table->renameColumn('excerpt', 'short_description');
            });
        }

        $this->cleanupTemplateContracts('page.excerpt', 'page.short_description');
        $this->cleanupDynamicFieldBlocks('page.excerpt', 'page.short_description');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->cleanupTemplateContracts('page.short_description', 'page.excerpt', restorePageContent: true);
        $this->cleanupDynamicFieldBlocks('page.short_description', 'page.excerpt');

        if (Schema::connection($this->connection)->hasTable('cms_pages')
            && Schema::connection($this->connection)->hasColumn('cms_pages', 'short_description')
            && ! Schema::connection($this->connection)->hasColumn('cms_pages', 'excerpt')) {
            Schema::connection($this->connection)->table('cms_pages', function (Blueprint $table): void {
                $table->renameColumn('short_description', 'excerpt');
            });
        }
    }

    private function cleanupTemplateContracts(string $fromField, string $toField, bool $restorePageContent = false): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_templates')
            || ! Schema::connection($this->connection)->hasColumn('cms_templates', 'data_contract')) {
            return;
        }

        DB::connection($this->connection)
            ->table('cms_templates')
            ->where('template_key', 'page.detail')
            ->orderBy('id')
            ->chunkById(100, function ($templates) use ($fromField, $toField, $restorePageContent): void {
                foreach ($templates as $template) {
                    $contract = $this->decodeJson($template->data_contract);

                    if (! is_array($contract)) {
                        continue;
                    }

                    unset($contract['template_fields']);

                    $systemFields = [];

                    foreach ((array) ($contract['system_fields'] ?? []) as $field) {
                        if (! is_array($field)) {
                            continue;
                        }

                        $key = (string) ($field['key'] ?? '');

                        if ($key === 'page.content') {
                            if (! $restorePageContent) {
                                continue;
                            }
                        }

                        if ($key === $fromField) {
                            $field['key'] = $toField;
                        }

                        if (($field['key'] ?? '') !== '') {
                            $systemFields[(string) $field['key']] = $field;
                        }
                    }

                    $contract['system_fields'] = array_values($systemFields);

                    DB::connection($this->connection)
                        ->table('cms_templates')
                        ->where('id', $template->id)
                        ->update(['data_contract' => json_encode($contract, JSON_THROW_ON_ERROR)]);
                }
            });
    }

    private function cleanupDynamicFieldBlocks(string $fromField, string $toField): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_blocks')
            || ! Schema::connection($this->connection)->hasColumn('cms_blocks', 'content')) {
            return;
        }

        DB::connection($this->connection)
            ->table('cms_blocks')
            ->orderBy('id')
            ->chunkById(100, function ($blocks) use ($fromField, $toField): void {
                foreach ($blocks as $block) {
                    $content = $this->decodeJson($block->content);

                    if (! is_array($content) || ! array_key_exists('field_key', $content)) {
                        continue;
                    }

                    if ($content['field_key'] === $fromField) {
                        $content['field_key'] = $toField;
                    } elseif ($content['field_key'] === 'page.content') {
                        unset($content['field_key']);
                    } else {
                        continue;
                    }

                    DB::connection($this->connection)
                        ->table('cms_blocks')
                        ->where('id', $block->id)
                        ->update(['content' => json_encode($content, JSON_THROW_ON_ERROR)]);
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
