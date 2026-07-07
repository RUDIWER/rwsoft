<?php

namespace App\Actions\Platform;

use App\Actions\Admin\Cms\CopySystemCountryFlagToTenantMediaAction;
use App\Actions\Admin\Cms\SyncPublicTextKeysAction;
use App\Models\Platform\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedTenantCmsDefaultsAction
{
    public function __construct(
        private readonly ConfigureTenantDatabaseAction $configureTenantDatabase,
        private readonly SyncPublicTextKeysAction $syncPublicTextKeys,
        private readonly CopySystemCountryFlagToTenantMediaAction $copySystemCountryFlagToTenantMedia,
    ) {}

    public function handle(Site $site): void
    {
        $this->configureTenantDatabase->handle($site);

        if (! $this->tenantCmsTablesExist()) {
            return;
        }

        DB::connection('tenant')->transaction(function () use ($site): void {
            $locale = (string) config('app.locale', 'nl');
            $homepageId = $this->ensureHomepage($site, $locale);

            $this->upsertSetting('general', 'site_name', 'Sitenaam', 'text', $site->name, 10);
            $this->upsertSetting('general', 'site_tagline', 'Tagline', 'text', null, 20);
            $this->upsertSetting('general', 'default_locale', 'Standaardtaal', 'text', $locale, 30);
            $this->upsertSetting('general', 'homepage_id', 'Homepage', 'number', $homepageId, 40);
            $this->upsertSetting('seo', 'default_title', 'SEO standaardtitel', 'text', $site->name, 50);
            $this->upsertSetting('seo', 'default_description', 'SEO standaardomschrijving', 'textarea', null, 60);
            $this->upsertSetting('seo', 'global_noindex', 'Globale noindex', 'boolean', false, 70);

            if (Schema::connection('tenant')->hasTable('cms_media_folders')) {
                $this->copySystemCountryFlagToTenantMedia->ensureRootFolder();
            }

            $this->syncPublicTextKeys->handle();
        });
    }

    private function tenantCmsTablesExist(): bool
    {
        return Schema::connection('tenant')->hasTable('cms_pages')
            && Schema::connection('tenant')->hasTable('cms_settings');
    }

    private function ensureHomepage(Site $site, string $locale): int
    {
        $homepageId = DB::connection('tenant')
            ->table('cms_pages')
            ->where('locale', $locale)
            ->where('is_home', true)
            ->value('id');

        if ($homepageId) {
            return (int) $homepageId;
        }

        return (int) DB::connection('tenant')->table('cms_pages')->insertGetId([
            'parent_id' => null,
            'author_id' => null,
            'title' => 'Home',
            'slug' => 'home',
            'locale' => $locale,
            'status' => 'published',
            'template' => null,
            'short_description' => null,
            'content_blocks' => json_encode([
                [
                    'type' => 'text',
                    'title' => 'Welkom bij '.$site->name,
                    'text' => 'Deze site is aangemaakt en klaar om te beheren.',
                ],
            ]),
            'seo_title' => $site->name,
            'seo_description' => null,
            'canonical_url' => null,
            'og_image_path' => null,
            'noindex' => false,
            'is_home' => true,
            'is_searchable' => true,
            'sort_order' => 0,
            'published_at' => now(),
            'settings' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertSetting(string $group, string $key, string $label, string $type, mixed $value, int $sortOrder): void
    {
        DB::connection('tenant')->table('cms_settings')->updateOrInsert(
            ['group' => $group, 'key' => $key],
            [
                'label' => $label,
                'type' => $type,
                'value' => json_encode(['value' => $value]),
                'is_public' => true,
                'sort_order' => $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
