<?php

use Illuminate\Database\Migrations\Migration;
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
        if (! Schema::hasTable('cms_templates')) {
            return;
        }

        $now = now();
        $locales = $this->activeLocales();
        $templates = [
            'error.default' => 'Default error',
            'error.403' => '403 forbidden',
            'error.404' => '404 not found',
            'error.419' => '419 page expired',
            'error.500' => '500 server error',
            'error.503' => '503 unavailable',
        ];

        foreach ($templates as $templateKey => $name) {
            $translationKey = 'cms-error-template-'.$templateKey;

            foreach ($locales as $locale) {
                $importKey = "cms.error_templates.{$templateKey}.{$locale}";

                if (DB::table('cms_templates')->where('import_key', $importKey)->exists()) {
                    continue;
                }

                DB::table('cms_templates')->insert([
                    'import_key' => $importKey,
                    'name' => $name,
                    'locale' => $locale,
                    'translation_key' => $translationKey,
                    'translated_from_template_id' => null,
                    'layout_id' => $this->defaultLayoutId($locale),
                    'template_class' => 'error',
                    'template_key' => $templateKey,
                    'module_key' => null,
                    'is_default' => false,
                    'is_active' => false,
                    'cache_strategy' => 'inherit',
                    'settings' => json_encode(['system_seeded' => true], JSON_THROW_ON_ERROR),
                    'data_contract' => json_encode($this->dataContract(), JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left empty: templates may have been customized after creation.
    }

    /**
     * @return array<int, string>
     */
    private function activeLocales(): array
    {
        if (Schema::hasTable('cms_languages')) {
            $locales = DB::table('cms_languages')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('locale')
                ->pluck('locale')
                ->map(fn (mixed $locale): string => (string) $locale)
                ->filter()
                ->values()
                ->all();

            if ($locales !== []) {
                return $locales;
            }
        }

        $defaultLocale = 'en';

        if (Schema::hasTable('cms_settings')) {
            $setting = DB::table('cms_settings')
                ->where('group', 'general')
                ->where('key', 'default_locale')
                ->value('value');
            $value = is_string($setting) ? json_decode($setting, true) : null;
            $defaultLocale = (string) ($value['value'] ?? $defaultLocale);
        }

        return [Str::of($defaultLocale)->replace('-', '_')->toString()];
    }

    private function defaultLayoutId(string $locale): ?int
    {
        if (! Schema::hasTable('cms_layouts')) {
            return null;
        }

        $layoutId = DB::table('cms_layouts')
            ->where('locale', $locale)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->value('id');

        return $layoutId !== null ? (int) $layoutId : null;
    }

    /**
     * @return array{system_fields: array<int, array{key: string, enabled: bool}>, template_fields: array<int, mixed>}
     */
    private function dataContract(): array
    {
        return [
            'system_fields' => collect([
                'error.status_code',
                'error.title',
                'error.message',
                'error.request_path',
                'error.home_url',
            ])->map(fn (string $key): array => ['key' => $key, 'enabled' => true])->all(),
            'template_fields' => [],
        ];
    }
};
