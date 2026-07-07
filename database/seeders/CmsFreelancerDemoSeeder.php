<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CmsFreelancerDemoSeeder extends Seeder
{
    public function run(): void
    {
        $authorId = Schema::connection('central')->hasTable('users')
            ? (int) (DB::connection('central')->table('users')->orderBy('id')->value('id') ?? 0) ?: null
            : null;

        $this->seedSettings();
        $media = $this->seedMedia($authorId);
        $forms = $this->seedForms();
        $pages = $this->seedPages($authorId, $media, $forms);
        $posts = $this->seedPosts($authorId, $media);
        $this->seedMenus($pages, $posts);
        $this->seedSubmissions($forms, $pages);
    }

    private function seedSettings(): void
    {
        foreach ([
            ['general', 'site_name', 'Sitenaam', 'text', 'RW Software Studio'],
            ['general', 'site_tagline', 'Tagline', 'text', 'Laravel en Vue applicaties voor groeibedrijven'],
            ['general', 'default_locale', 'Standaardtaal', 'text', 'nl'],
            ['seo', 'default_title', 'SEO titel', 'text', 'RW Software Studio | Laravel, Vue en startup begeleiding'],
            ['seo', 'default_description', 'SEO omschrijving', 'textarea', 'Freelance softwareontwikkeling voor Laravel en Vue applicaties, MVP trajecten en technische begeleiding van startups.'],
            ['seo', 'global_noindex', 'Globale noindex', 'boolean', false],
        ] as [$group, $key, $label, $type, $value]) {
            $this->upsert('cms_settings', ['group' => $group, 'key' => $key], [
                'label' => $label,
                'type' => $type,
                'value' => json_encode(['value' => $value]),
                'is_public' => true,
                'sort_order' => 0,
            ]);
        }
    }

    /**
     * @return array<string, int>
     */
    private function seedMedia(?int $authorId): array
    {
        $folderId = $this->upsertGetId('cms_media_folders', ['parent_id' => null, 'slug' => 'demo'], [
            'name' => 'Demo',
            'sort_order' => 10,
            'settings' => json_encode(['demo' => true]),
        ]);

        $assets = [
            'hero' => ['cms/demo/hero-dashboard.webp', 'hero-dashboard.webp', 'Dashboard met maatwerk software'],
            'case' => ['cms/demo/case-saas-platform.webp', 'case-saas-platform.webp', 'SaaS platform interface'],
            'blog' => ['cms/demo/blog-laravel-vue.webp', 'blog-laravel-vue.webp', 'Laravel en Vue code op scherm'],
        ];

        $media = [];

        foreach ($assets as $key => [$path, $filename, $altText]) {
            $this->ensureDemoMediaFile($path);

            $media[$key] = $this->upsertGetId('cms_media_assets', ['path' => $path], [
                'folder_id' => $folderId,
                'uploaded_by' => $authorId,
                'disk' => 'public',
                'visibility' => 'public',
                'filename' => $filename,
                'original_filename' => $filename,
                'mime_type' => 'image/webp',
                'extension' => 'webp',
                'size_bytes' => 245000,
                'width' => 1600,
                'height' => 900,
                'hash' => hash('sha256', $path),
                'alt_text' => $altText,
                'caption' => 'Demo afbeelding',
                'metadata' => json_encode(['demo' => true, 'source' => 'placeholder']),
                'sort_order' => 0,
            ]);
        }

        return $media;
    }

    private function ensureDemoMediaFile(string $path): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return;
        }

        $disk->put($path, base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA', true));
    }

    /**
     * @return array<string, int>
     */
    private function seedForms(): array
    {
        $forms = [
            'contactformulier' => ['Contactformulier', 'Vertel kort waarmee ik kan helpen.', 'Bericht verzonden. Ik neem snel contact op.'],
            'project-intake' => ['Project intake', 'Een korte intake voor nieuwe Laravel/Vue of automatiseringsprojecten.', 'Bedankt. Ik bekijk je project en stuur een voorstel voor de volgende stap.'],
        ];

        $formIds = [];

        foreach ($forms as $key => [$title, $description, $successMessage]) {
            $formIds[$key] = $this->upsertGetId('cms_forms', ['key' => $key, 'locale' => 'nl'], [
                'title' => $title,
                'translation_key' => (string) Str::ulid(),
                'description' => $description,
                'notification_email' => 'hello@rwsoftware-studio.test',
                'submit_button_label' => 'Verzenden',
                'success_message' => $successMessage,
                'is_active' => true,
                'settings' => json_encode(['demo' => true]),
            ]);
        }

        foreach ([
            ['naam', 'Naam', 'text', true, 10, []],
            ['email', 'E-mail', 'email', true, 20, []],
            ['bedrijf', 'Bedrijf', 'text', false, 30, []],
            ['bericht', 'Bericht', 'textarea', true, 90, []],
            ['privacy', 'Akkoord met verwerking van mijn gegevens', 'checkbox', true, 100, []],
        ] as $field) {
            $this->seedFormField($formIds['contactformulier'], ...$field);
        }

        foreach ([
            ['naam', 'Naam', 'text', true, 10, []],
            ['email', 'E-mail', 'email', true, 20, []],
            ['projecttype', 'Projecttype', 'select', true, 40, [
                ['key' => 'laravel-app', 'label' => 'Laravel applicatie'],
                ['key' => 'vue-inertia-frontend', 'label' => 'Vue/Inertia frontend'],
                ['key' => 'saas-mvp', 'label' => 'SaaS MVP'],
                ['key' => 'automation', 'label' => 'Automatisering'],
            ]],
            ['bericht', 'Korte projectomschrijving', 'textarea', true, 90, []],
            ['privacy', 'Akkoord met verwerking van mijn gegevens', 'checkbox', true, 100, []],
        ] as $field) {
            $this->seedFormField($formIds['project-intake'], ...$field);
        }

        return $formIds;
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $options
     */
    private function seedFormField(int $formId, string $key, string $label, string $type, bool $required, int $sortOrder, array $options): void
    {
        $this->upsert('cms_form_fields', ['cms_form_id' => $formId, 'key' => $key], [
            'type' => $type,
            'translation_key' => (string) Str::ulid(),
            'label' => $label,
            'placeholder' => $type === 'textarea' ? 'Beschrijf kort je context...' : null,
            'help_text' => null,
            'options' => $options === [] ? null : json_encode($options),
            'validation_rules' => json_encode($required ? ['required'] : ['nullable']),
            'sort_order' => $sortOrder,
            'is_required' => $required,
            'is_active' => true,
            'width' => in_array($key, ['naam', 'email'], true) ? 'half' : 'full',
            'settings' => json_encode(['demo' => true]),
        ]);
    }

    /**
     * @param  array<string, int>  $media
     * @param  array<string, int>  $forms
     * @return array<string, int>
     */
    private function seedPages(?int $authorId, array $media, array $forms): array
    {
        $pages = [
            'home' => [null, 'Home', true, 'Maatwerk software die startups sneller vooruit helpt.', 'Laravel, Vue en productbegeleiding voor founders en teams die van idee naar schaalbaar platform willen.'],
            'diensten' => [null, 'Diensten', false, 'Van MVP tot schaalbare applicatie.', 'Ontwikkeling, architectuur, automatisering en begeleiding op maat.'],
            'laravel-vue-development' => ['diensten', 'Laravel & Vue Development', false, 'Robuuste applicaties met Laravel, Vue en Inertia.', 'Ik bouw maatwerkplatformen met duidelijke domeinlogica en snelle interfaces.'],
            'startup-begeleiding' => ['diensten', 'Startup begeleiding', false, 'Technische sparring voor founders.', 'Ik help startups met MVP-scope, technische keuzes, roadmap en bouwritme.'],
            'portfolio' => [null, 'Portfolio', false, 'Voorbeelden van maatwerk softwaretrajecten.', 'Cases rond SaaS, interne tools en automatisering voor teams die sneller willen groeien.'],
            'blog' => [null, 'Blog', false, 'Inzichten over Laravel, Vue en productontwikkeling.', 'Artikels over softwarearchitectuur, MVP trajecten en technische groei.'],
            'contact' => [null, 'Contact', false, 'Vertel kort waar je naartoe wil.', 'Plan een kennismaking of stuur je projectvraag door.'],
        ];

        $pageIds = [];

        foreach ($pages as $slug => [$parentSlug, $title, $isHome, $excerpt, $text]) {
            $blocks = [
                ['type' => 'text', 'title' => $title, 'text' => $text],
            ];

            if ($slug === 'home') {
                $blocks[] = ['type' => 'image', 'media_asset_id' => $media['hero'], 'caption' => 'Maatwerk dashboard'];
                $blocks[] = ['type' => 'button', 'label' => 'Start je project', 'url' => '/contact'];
            }

            if ($slug === 'portfolio') {
                $blocks[] = ['type' => 'image', 'media_asset_id' => $media['case'], 'caption' => 'SaaS platform case'];
                $blocks[] = ['type' => 'button', 'label' => 'Bespreek je case', 'url' => '/contact'];
            }

            if ($slug === 'contact') {
                $blocks[] = ['type' => 'form', 'form_key' => 'project-intake'];
            }

            $pageIds[$slug] = $this->upsertGetId('cms_pages', ['locale' => 'nl', 'slug' => $slug], [
                'parent_id' => $parentSlug ? $pageIds[$parentSlug] : null,
                'author_id' => $authorId,
                'title' => $title,
                'status' => 'published',
                'template' => $isHome ? 'home' : 'default',
                'excerpt' => $excerpt,
                'content_blocks' => json_encode($blocks),
                'seo_title' => $title.' | RW Software Studio',
                'seo_description' => $excerpt,
                'canonical_url' => $isHome ? '/' : '/'.$slug,
                'noindex' => false,
                'is_home' => $isHome,
                'is_searchable' => true,
                'sort_order' => count($pageIds) * 10,
                'published_at' => now()->subDays(14),
                'settings' => json_encode(['demo' => true]),
            ]);
        }

        $this->upsert('cms_settings', ['group' => 'general', 'key' => 'homepage_id'], [
            'label' => 'Homepage',
            'type' => 'page',
            'value' => json_encode(['value' => $pageIds['home']]),
            'is_public' => true,
            'sort_order' => 0,
        ]);

        return $pageIds;
    }

    /**
     * @param  array<string, int>  $media
     * @return array<string, int>
     */
    private function seedPosts(?int $authorId, array $media): array
    {
        $categoryId = $this->upsertGetId('cms_categories', ['type' => 'post', 'locale' => 'nl', 'slug' => 'insights'], [
            'parent_id' => null,
            'title' => 'Insights',
            'description' => 'Artikels over softwarearchitectuur en productontwikkeling.',
            'sort_order' => 10,
            'is_active' => true,
            'settings' => json_encode(['demo' => true]),
        ]);

        $tagId = $this->upsertGetId('cms_tags', ['locale' => 'nl', 'slug' => 'laravel'], [
            'title' => 'Laravel',
            'description' => null,
            'is_active' => true,
            'settings' => json_encode(['demo' => true]),
        ]);

        $posts = [
            'waarom-laravel-en-vue-sterk-zijn-voor-maatwerk-saas' => 'Waarom Laravel en Vue sterk zijn voor maatwerk SaaS',
            'van-startup-idee-naar-mvp-in-zes-weken' => 'Van startup idee naar MVP in 6 weken',
        ];

        $postIds = [];
        $daysAgo = 14;

        foreach ($posts as $slug => $title) {
            $postIds[$slug] = $this->upsertGetId('cms_posts', ['locale' => 'nl', 'slug' => $slug], [
                'author_id' => $authorId,
                'featured_media_asset_id' => $media['blog'] ?? null,
                'title' => $title,
                'status' => 'published',
                'excerpt' => 'Demo-artikel over Laravel, Vue en pragmatische productontwikkeling.',
                'content_blocks' => json_encode([
                    ['type' => 'text', 'title' => $title, 'text' => 'Sterke software start bij duidelijke productkeuzes. Laravel en Vue maken het mogelijk om snel te bouwen zonder onderhoudbaarheid te verliezen.'],
                    ['type' => 'quote', 'text' => 'De beste oplossing is helder, onderhoudbaar en precies goed genoeg voor de volgende fase.', 'source' => 'RW Software Studio'],
                ]),
                'seo_title' => $title,
                'seo_description' => 'Demo-artikel over Laravel, Vue en productontwikkeling.',
                'canonical_url' => '/posts/'.$slug,
                'noindex' => false,
                'is_featured' => $daysAgo === 14,
                'is_searchable' => true,
                'published_at' => now()->subDays($daysAgo),
                'settings' => json_encode(['demo' => true]),
            ]);

            $this->upsert('cms_post_category', ['cms_post_id' => $postIds[$slug], 'cms_category_id' => $categoryId], []);
            $this->upsert('cms_post_tag', ['cms_post_id' => $postIds[$slug], 'cms_tag_id' => $tagId], []);

            $daysAgo -= 5;
        }

        return $postIds;
    }

    /**
     * @param  array<string, int>  $pages
     * @param  array<string, int>  $posts
     */
    private function seedMenus(array $pages, array $posts): void
    {
        $headerId = $this->upsertGetId('cms_menus', ['location' => 'header'], [
            'title' => 'Header',
            'location' => 'header',
            'is_active' => true,
            'settings' => json_encode(['demo' => true]),
        ]);
        $footerId = $this->upsertGetId('cms_menus', ['location' => 'footer'], [
            'title' => 'Footer',
            'location' => 'footer',
            'is_active' => true,
            'settings' => json_encode(['demo' => true]),
        ]);
        $this->upsert('cms_menu_translations', ['cms_menu_id' => $headerId, 'locale' => 'nl'], ['title' => 'Header']);
        $this->upsert('cms_menu_translations', ['cms_menu_id' => $footerId, 'locale' => 'nl'], ['title' => 'Footer']);

        $dienstenItemId = $this->menuItem($headerId, null, 'Diensten', 'page', 20, pageId: $pages['diensten']);
        $this->menuItem($headerId, null, 'Home', 'page', 10, pageId: $pages['home']);
        $this->menuItem($headerId, $dienstenItemId, 'Laravel & Vue', 'page', 21, pageId: $pages['laravel-vue-development']);
        $this->menuItem($headerId, $dienstenItemId, 'Startup begeleiding', 'page', 22, pageId: $pages['startup-begeleiding']);
        $this->menuItem($headerId, null, 'Portfolio', 'page', 30, pageId: $pages['portfolio']);
        $this->menuItem($headerId, null, 'Blog', 'page', 40, pageId: $pages['blog']);
        $this->menuItem($headerId, null, 'Contact', 'page', 50, pageId: $pages['contact']);

        $this->menuItem($footerId, null, 'Contact', 'page', 10, pageId: $pages['contact']);
        $this->menuItem($footerId, null, 'Nieuwste artikel', 'post', 20, postId: reset($posts) ?: null);
    }

    private function menuItem(int $menuId, ?int $parentId, string $label, string $type, int $sortOrder, ?int $pageId = null, ?int $postId = null): int
    {
        return $this->upsertGetId('cms_menu_items', [
            'cms_menu_id' => $menuId,
            'label' => $label,
        ], [
            'parent_id' => $parentId,
            'cms_page_id' => $pageId,
            'cms_post_id' => $postId,
            'type' => $type,
            'url' => null,
            'target' => null,
            'rel' => null,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'metadata' => json_encode(['demo' => true]),
        ]);
    }

    /**
     * @param  array<string, int>  $forms
     * @param  array<string, int>  $pages
     */
    private function seedSubmissions(array $forms, array $pages): void
    {
        $form = DB::table('cms_forms')->where('id', $forms['project-intake'])->first(['locale', 'translation_key']);

        $submissionId = $this->upsertGetId('cms_form_submissions', ['cms_form_id' => $forms['project-intake'], 'ip_address' => '203.0.113.10'], [
            'cms_page_id' => $pages['contact'] ?? null,
            'locale' => (string) $form->locale,
            'form_translation_key' => (string) $form->translation_key,
            'status' => 'new',
            'submitted_at' => now()->subDays(2),
            'user_agent' => 'Demo Browser',
            'metadata' => json_encode(['demo' => true, 'source' => 'website']),
        ]);

        foreach ([
            'naam' => 'Lotte Janssens',
            'email' => 'lotte@example.test',
            'projecttype' => 'saas-mvp',
            'bericht' => 'We willen een MVP bouwen voor workflowautomatisering bij kleine teams.',
            'privacy' => '1',
        ] as $fieldKey => $value) {
            $this->submissionValue($submissionId, $forms['project-intake'], $fieldKey, $value);
        }
    }

    private function submissionValue(int $submissionId, int $formId, string $fieldKey, string $value): void
    {
        $field = DB::table('cms_form_fields')
            ->where('cms_form_id', $formId)
            ->where('key', $fieldKey)
            ->first(['id', 'translation_key']);

        $this->upsert('cms_form_submission_values', ['cms_form_submission_id' => $submissionId, 'field_key' => $fieldKey], [
            'cms_form_field_id' => $field?->id,
            'field_translation_key' => (string) $field?->translation_key,
            'value' => $value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function upsert(string $table, array $match, array $values): void
    {
        if (Schema::hasColumn($table, 'created_at')) {
            $values['created_at'] = $values['created_at'] ?? now();
        }

        if (Schema::hasColumn($table, 'updated_at')) {
            $values['updated_at'] = now();
        }

        DB::table($table)->updateOrInsert($match, $values);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function upsertGetId(string $table, array $match, array $values): int
    {
        $this->upsert($table, $match, $values);

        $query = DB::table($table);
        foreach ($match as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, $value);
            }
        }

        return (int) $query->value('id');
    }
}
