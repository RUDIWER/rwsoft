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
    private array $pageDetailFields = [
        'page.title',
        'page.short_description',
        'page.slug',
        'page.locale',
        'page.url',
        'page.seo_title',
        'page.seo_description',
        'page.published_at',
        'page.updated_at',
        'page.breadcrumbs',
    ];

    /**
     * @var array<int, string>
     */
    private array $removedPageDetailFields = [
        'page.excerpt',
        'page.content',
        'page.created_at',
        'page.canonical_url',
        'page.noindex',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('cms_templates')
            || ! Schema::connection($this->connection)->hasColumn('cms_templates', 'data_contract')) {
            return;
        }

        DB::connection($this->connection)
            ->table('cms_templates')
            ->where('template_key', 'page.detail')
            ->orderBy('id')
            ->chunkById(100, function ($templates): void {
                foreach ($templates as $template) {
                    $contract = $this->decodeJson($template->data_contract) ?? [];
                    unset($contract['template_fields']);

                    $systemFields = [];

                    foreach ((array) ($contract['system_fields'] ?? []) as $field) {
                        if (! is_array($field)) {
                            continue;
                        }

                        $key = (string) ($field['key'] ?? '');

                        if ($key === '' || in_array($key, $this->removedPageDetailFields, true)) {
                            continue;
                        }

                        if (in_array($key, $this->pageDetailFields, true)) {
                            $systemFields[$key] = [
                                'key' => $key,
                                'enabled' => (bool) ($field['enabled'] ?? true),
                            ];
                        }
                    }

                    foreach ($this->pageDetailFields as $key) {
                        $systemFields[$key] ??= ['key' => $key, 'enabled' => true];
                    }

                    $contract['system_fields'] = array_values(array_replace(array_flip($this->pageDetailFields), $systemFields));

                    DB::connection($this->connection)
                        ->table('cms_templates')
                        ->where('id', $template->id)
                        ->update(['data_contract' => json_encode($contract, JSON_THROW_ON_ERROR)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: this syncs page.detail templates to the current system-field contract.
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
