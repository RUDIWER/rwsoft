<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_templates')) {
            return;
        }

        DB::transaction(function (): void {
            $templates = DB::table('cms_templates')
                ->where('import_key', 'like', 'cms.default-template.%')
                ->whereNull('deleted_at')
                ->orderBy('template_class')
                ->orderBy('template_key')
                ->orderBy('locale')
                ->get(['id', 'locale', 'template_key', 'import_key']);

            collect($templates)
                ->groupBy(fn (object $template): string => $template->template_key)
                ->each(function ($group): void {
                    $source = collect($group)->firstWhere('locale', 'nl')
                        ?? collect($group)->sortBy('id')->first();

                    if (! $source) {
                        return;
                    }

                    $translationKey = implode('.', ['cms', 'default-template', $source->template_key]);

                    foreach ($group as $template) {
                        DB::table('cms_templates')
                            ->where('id', $template->id)
                            ->update([
                                'translation_key' => $translationKey,
                                'translated_from_template_id' => $template->id === $source->id ? null : $source->id,
                            ]);
                    }
                });
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cms_templates')) {
            return;
        }

        DB::table('cms_templates')
            ->where('import_key', 'like', 'cms.default-template.%')
            ->whereNull('deleted_at')
            ->update([
                'translation_key' => DB::raw('import_key'),
                'translated_from_template_id' => null,
            ]);
    }
};
