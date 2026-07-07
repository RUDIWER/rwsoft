<?php

use App\Support\PublicSite\CmsPublicTextCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_public_texts') || ! Schema::hasTable('cms_public_text_translations')) {
            return;
        }

        $now = now();

        foreach ($this->texts() as $text) {
            DB::table('cms_public_texts')->updateOrInsert(
                [
                    'group' => $text['group'],
                    'key' => $text['key'],
                ],
                [
                    'label' => $text['label'],
                    'default_value' => $text['default_value'],
                    'type' => 'text',
                    'is_system' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $textId = (int) DB::table('cms_public_texts')
                ->where('group', $text['group'])
                ->where('key', $text['key'])
                ->value('id');

            foreach ($text['translations'] as $locale => $value) {
                DB::table('cms_public_text_translations')->updateOrInsert(
                    [
                        'cms_public_text_id' => $textId,
                        'locale' => $locale,
                    ],
                    [
                        'value' => $value,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }

        app(CmsPublicTextCache::class)->flush();
    }

    public function down(): void
    {
        // Public text changes are content updates and are intentionally not reverted.
    }

    /**
     * @return array<int, array{group: string, key: string, label: string, default_value: string, translations: array<string, string>}>
     */
    private function texts(): array
    {
        return [
            [
                'group' => 'post_index',
                'key' => 'title',
                'label' => 'Blog index titel',
                'default_value' => 'Blogs',
                'translations' => ['nl' => 'Blogs', 'en' => 'Blogs', 'fr' => 'Blogs'],
            ],
            [
                'group' => 'post_index',
                'key' => 'lead',
                'label' => 'Blog index intro',
                'default_value' => 'Laatste gepubliceerde blogs en updates.',
                'translations' => [
                    'nl' => 'Laatste gepubliceerde blogs en updates.',
                    'en' => 'Latest published blogs and updates.',
                    'fr' => 'Derniers blogs et mises a jour publies.',
                ],
            ],
            [
                'group' => 'post_index',
                'key' => 'seo_title',
                'label' => 'Blog index SEO titel',
                'default_value' => 'Blogs',
                'translations' => ['nl' => 'Blogs', 'en' => 'Blogs', 'fr' => 'Blogs'],
            ],
            [
                'group' => 'post_index',
                'key' => 'seo_description',
                'label' => 'Blog index SEO omschrijving',
                'default_value' => 'Laatste gepubliceerde blogs.',
                'translations' => [
                    'nl' => 'Laatste gepubliceerde blogs.',
                    'en' => 'Latest published blogs.',
                    'fr' => 'Derniers blogs publies.',
                ],
            ],
            [
                'group' => 'post_index',
                'key' => 'empty',
                'label' => 'Geen blogs tekst',
                'default_value' => 'Er zijn nog geen gepubliceerde blogs.',
                'translations' => [
                    'nl' => 'Er zijn nog geen gepubliceerde blogs.',
                    'en' => 'No published blogs yet.',
                    'fr' => 'Aucun blog publie pour le moment.',
                ],
            ],
            [
                'group' => 'breadcrumb',
                'key' => 'posts',
                'label' => 'Breadcrumb blogs',
                'default_value' => 'Blogs',
                'translations' => ['nl' => 'Blogs', 'en' => 'Blogs', 'fr' => 'Blogs'],
            ],
        ];
    }
};
